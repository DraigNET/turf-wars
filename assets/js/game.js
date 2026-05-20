function moveToTurf(turfId) {

    // Persist chat channel
    const savedChannel = localStorage.getItem('chat_channel');
    if (savedChannel) {
        localStorage.setItem('chat_channel', savedChannel);
    }

    document.querySelectorAll('.move-btn').forEach(btn => btn.disabled = true);

    fetch('/move.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'turf_id=' + turfId + '&_csrf=' + encodeURIComponent(window.CSRF_TOKEN)
    })
    .then(res => res.text())
    .then(text => {
        let data;

        try {
            data = JSON.parse(text);
        } catch {
            console.error('Invalid JSON:', text);
            alert('Server error');
            location.reload();
            return;
        }

        if (!data.success) {
            alert(data.error || 'Move failed');
            location.reload();
            return;
        }

        location.reload();
    })
    .catch(err => {
        console.error(err);
        alert('Server error');
        location.reload();
    });
}

document.addEventListener('DOMContentLoaded', () => {

    // =========================
    // MOVE BUTTONS
    // =========================
    document.querySelectorAll('.move-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const turfId = btn.dataset.turfId;
            moveToTurf(turfId);
        });
    });

    // =========================
    // TURF MAP SYSTEM
    // =========================
    const container = document.getElementById('turf-map');
    if (!container) return;

    const stage = document.getElementById('turf-stage');
    const tooltip = document.getElementById('turf-tooltip');

    const IMG_SIZE = 6144;

    let scale = 1;
    let minScale = 1;
    const maxScale = 2.5;

    let x = 0;
    let y = 0;

    let isPanning = false;
    let dragOccurred = false;
    let startX, startY, startClientX, startClientY;

    function clamp() {
        const cw = container.clientWidth;
        const ch = container.clientHeight;

        const sw = IMG_SIZE * scale;
        const sh = IMG_SIZE * scale;

        if (sw <= cw) x = (cw - sw) / 2;
        else x = Math.max(cw - sw, Math.min(0, x));

        if (sh <= ch) y = (ch - sh) / 2;
        else y = Math.max(ch - sh, Math.min(0, y));
    }

    function apply() {
        clamp();
        stage.style.transform = `translate(${x}px, ${y}px) scale(${scale})`;
    }

    function resetView() {
        minScale = Math.max(
            container.clientWidth / IMG_SIZE,
            container.clientHeight / IMG_SIZE
        );

        scale = minScale;
        x = (container.clientWidth - IMG_SIZE * scale) / 2;
        y = (container.clientHeight - IMG_SIZE * scale) / 2;

        apply();
    }

    function zoomAt(factor, clientX, clientY) {
        const rect = container.getBoundingClientRect();
        const mx = clientX - rect.left;
        const my = clientY - rect.top;

        const oldScale = scale;
        scale = Math.max(minScale, Math.min(maxScale, scale * factor));

        const dx = mx - x;
        const dy = my - y;

        x = mx - dx * (scale / oldScale);
        y = my - dy * (scale / oldScale);

        apply();
    }

    // PAN
    container.addEventListener('pointerdown', (e) => {
        e.preventDefault();

        if (e.button !== 0) return;

        isPanning = true;
        dragOccurred = false;
        startClientX = e.clientX;
        startClientY = e.clientY;
        startX = x;
        startY = y;

        container.setPointerCapture(e.pointerId);
    });

    container.addEventListener('pointermove', (e) => {
        if (!isPanning) return;

        if (Math.abs(e.clientX - startClientX) > 4 || Math.abs(e.clientY - startClientY) > 4) {
            dragOccurred = true;
        }

        x = startX + (e.clientX - startClientX);
        y = startY + (e.clientY - startClientY);

        apply();
    });

    container.addEventListener('pointerup', (e) => {
        isPanning = false;
        if (!dragOccurred) {
            const el = document.elementFromPoint(e.clientX, e.clientY);
            if (el) el.dispatchEvent(new CustomEvent('map-tap', { bubbles: true }));
        }
    });

    // ZOOM
    container.addEventListener('wheel', (e) => {
        e.preventDefault();
        zoomAt(e.deltaY < 0 ? 1.1 : 0.9, e.clientX, e.clientY);
    }, { passive: false });

    document.getElementById('zoom-in')?.addEventListener('click', () => {
        const rect = container.getBoundingClientRect();
        zoomAt(1.2, rect.left + rect.width / 2, rect.top + rect.height / 2);
    });

    document.getElementById('zoom-out')?.addEventListener('click', () => {
        const rect = container.getBoundingClientRect();
        zoomAt(1 / 1.2, rect.left + rect.width / 2, rect.top + rect.height / 2);
    });

    const infoPanel = document.getElementById('turf-info');

    document.querySelectorAll('.turf-rect').forEach(el => {

        el.addEventListener('mouseenter', () => {
            if (!infoPanel) return;

            const name = el.dataset.name;
            const factions = JSON.parse(el.dataset.factions || '{}');
            const sorted = Object.values(factions).sort((a, b) => b.percent - a.percent);

            let html = `
                <div class="font-bold text-white text-lg mb-2">${name.toUpperCase()}</div>
                <div class="border-b border-zinc-700 mb-2"></div>
            `;

            for (const f of sorted) {
                html += `
                    <div class="mb-2">
                        <div class="flex items-center gap-2 text-sm" style="color: ${f.color}">
                            <img src="/${f.icon}" class="w-4 h-4 object-contain">
                            <span>${f.name}</span>
                            <span class="ml-auto text-zinc-400">${f.percent}%</span>
                        </div>
                        <div class="w-full h-1 bg-zinc-800 rounded mt-1">
                            <div style="width: ${f.percent}%; background: ${f.color}" class="h-1 rounded"></div>
                        </div>
                    </div>
                `;
            }

            infoPanel.innerHTML = html;
        });

        el.addEventListener('mouseleave', () => {
            if (!infoPanel) return;
            infoPanel.innerHTML = `<div class="text-zinc-400">Hover over a zone</div>`;
        });

    });

    resetView();
    window.addEventListener('resize', resetView);

});

// =========================
// CHAT SYSTEM
// =========================

document.addEventListener('DOMContentLoaded', () => {

    let currentChannel = localStorage.getItem('chat_channel') || 'global';
    let lastMessageId = 0;

    const box = document.getElementById('chat-box');
    const input = document.getElementById('chat-input');
    const count = document.getElementById('chat-count');
    const sendBtn = document.getElementById('chat-send');

    if (!box) return;

    // =========================
    // APPLY SAVED TAB STATE
    // =========================
    document.querySelectorAll('.chat-tab').forEach(btn => {

        if (btn.dataset.channel === currentChannel) {
            btn.classList.remove('bg-zinc-700');
            btn.classList.add('bg-orange-600');
        } else {
            btn.classList.remove('bg-orange-600');
            btn.classList.add('bg-zinc-700');
        }

    });

    // =========================
    // CHARACTER COUNTER + LIMIT
    // =========================
    input.addEventListener('input', () => {

        let value = input.value;

        // Hard limit (extra safety)
        if (value.length > 200) {
            value = value.substring(0, 200);
            input.value = value;
        }

        count.textContent = value.length;

        if (value.length > 180) {
            count.classList.add('text-red-400');
        } else {
            count.classList.remove('text-red-400');
        }

        // ✅ ADD THIS PART AT THE BOTTOM
        const errorBox = document.getElementById('chat-error');
        if (errorBox) {
            errorBox.classList.add('hidden');
        }

    });

    // =========================
    // EMOJI PARSER
    // =========================
    function parseEmojis(text) {
        const emojiMap = {
            ":)": "😊", ":-)": "😊",
            ":(": "☹️", ":-(": "☹️",
            ":D": "😄", ":-D": "😄",
            ";)": "😉", ";-)": "😉",
            ":P": "😛", ":-P": "😛",
            ":p": "😛", ":-p": "😛",
            ":O": "😮", ":-O": "😮",
            ":o": "😮", ":-o": "😮",
            ":|": "😐", ":-|": "😐",
            ":'(": "😢",
            ":/": "😕", ":-/": "😕",
            ":\\": "😕", ":-\\": "😕",

            "<3": "❤️", "</3": "💔",
            ":*": "😘", ":-*": "😘",

            "xd": "😂", "XD": "😂", "xD": "😂", "Xd": "😂",
            ":skull:": "💀", ":fire:": "🔥", ":100:": "💯", ":clap:": "👏",

            ":thumbsup:": "👍", ":thumbsdown:": "👎",
            ":ok:": "👌", ":wave:": "👋", ":eyes:": "👀",

            ":cop:": "🚓", ":gun:": "🔫",
            ":money:": "💰", ":cash:": "💵", ":bank:": "🏦",

            ":star:": "⭐", ":check:": "✅",
            ":x:": "❌", ":warning:": "⚠️", ":idea:": "💡"
        };

        for (const key in emojiMap) {
            const escaped = key.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const regex = new RegExp(escaped, 'g');
            text = text.replace(regex, emojiMap[key]);
        }

        return text;
    }

    // =========================
    // FETCH CHAT
    // =========================
    function fetchChat() {

        fetch(`/chat_fetch.php?channel=${currentChannel}&last_id=${lastMessageId}`)
            .then(res => res.json())
            .then(data => {

                if (!data.messages) return;

                data.messages.forEach(msg => {

                    lastMessageId = msg.id;

                    const el = document.createElement('div');
                    el.className = 'mb-1 flex items-start gap-2';

                    // ICON
                    if (msg.icon_path) {
                        const icon = document.createElement('img');
                        icon.src = '/' + msg.icon_path;
                        icon.className = 'w-4 h-4 mt-[2px]';
                        el.appendChild(icon);
                    }

                    // TEXT WRAPPER
                    const content = document.createElement('div');
                    content.className = 'leading-tight';
                    content.style.wordBreak = 'break-word';

                    // NAME
                    const name = document.createElement('span');
                    name.textContent = msg.name;
                    name.style.color = msg.color || '#ffffff';
                    name.style.fontWeight = '600';

                    // COLON
                    const colon = document.createElement('span');
                    colon.textContent = ': ';
                    colon.className = 'text-zinc-400';

                    // MESSAGE
                    const text = document.createElement('span');
                    text.textContent = parseEmojis(msg.message);

                    content.appendChild(name);
                    content.appendChild(colon);
                    content.appendChild(text);

                    el.appendChild(content);

                    box.appendChild(el);
                });

                box.scrollTop = box.scrollHeight;
            });
    }

    // =========================
    // INITIAL LOAD
    // =========================
    fetchChat();
    setInterval(fetchChat, 2000);

    // =========================
    // SEND MESSAGE
    // =========================
    sendBtn.addEventListener('click', async () => {

        let msg = input.value.trim();
        msg = parseEmojis(msg);

        if (!msg) return;

        const errorBox = document.getElementById('chat-error');
        errorBox.classList.add('hidden');
        errorBox.textContent = '';

        try {
            const response = await fetch('/chat_send.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'message=' + encodeURIComponent(msg)
                    + '&channel=' + currentChannel
                    + '&csrf_token=' + encodeURIComponent(window.CSRF_TOKEN)
            });

            // 🔴 Handle errors (mute, csrf, etc.)
            if (!response.ok) {

                let text = '';

                try {
                    const data = await response.json();
                    text = data.error || 'Unable to send message.';
                } catch {
                    text = await response.text();
                }

                errorBox.textContent = text || 'Message failed.';
                errorBox.classList.remove('hidden');

                return;
            }

            // ✅ Success
            input.value = '';
            count.textContent = 0;

        } catch (err) {
            console.error(err);

            errorBox.textContent = 'Connection error.';
            errorBox.classList.remove('hidden');
        }

    });

    input.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') sendBtn.click();
    });

    // =========================
    // CHANNEL SWITCHING
    // =========================
    document.querySelectorAll('.chat-tab').forEach(btn => {

        btn.addEventListener('click', () => {

            currentChannel = btn.dataset.channel;
            lastMessageId = 0;

            localStorage.setItem('chat_channel', currentChannel);

            box.innerHTML = '';

            document.querySelectorAll('.chat-tab').forEach(b => {
                b.classList.remove('bg-orange-600');
                b.classList.add('bg-zinc-700');
            });

            btn.classList.remove('bg-zinc-700');
            btn.classList.add('bg-orange-600');

            fetchChat();
        });

    });

});