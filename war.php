<?php
require_once 'includes/bootstrap.php';
require_login();

$pdo = db();

$char = get_character();

if (!$char || !$char['turf_id']) {
    redirect('actions.php');
}

// =========================
// LOAD ACTIVE WAR
// =========================
$stmt = $pdo->prepare("
    SELECT * 
    FROM turf_wars
    WHERE turf_id = ? AND status = 'active'
    LIMIT 1
");
$stmt->execute([$char['turf_id']]);
$war = $stmt->fetch();

if (!$war) {
    redirect('actions.php');
}

// =========================
// FACTION INFO
// =========================
$stmtFactions = $pdo->prepare("
    SELECT id, name, color 
    FROM factions 
    WHERE id IN (?, ?)
");
$stmtFactions->execute([
    $war['attacker_faction_id'],
    $war['defender_faction_id']
]);
$factions = $stmtFactions->fetchAll(PDO::FETCH_UNIQUE);

// =========================
// PLAYER STATE
// =========================
$stmtPlayer = $pdo->prepare("
    SELECT * 
    FROM turf_war_participants
    WHERE war_id = ? AND char_id = ?
    LIMIT 1
");
$stmtPlayer->execute([$war['id'], $char['id']]);
$player = $stmtPlayer->fetch();

if (!$player) {
    redirect('actions.php');
}

// =========================
// ALIVE / DEAD COUNTS
// =========================
$stmtCounts = $pdo->prepare("
    SELECT faction_id,
           SUM(is_alive = 1) as alive,
           SUM(is_alive = 0) as dead
    FROM turf_war_participants
    WHERE war_id = ?
    GROUP BY faction_id
");
$stmtCounts->execute([$war['id']]);
$countRows = $stmtCounts->fetchAll();

$counts = [];
foreach ($countRows as $row) {
    $counts[$row['faction_id']] = $row;
}

// =========================
// WAR LOG
// =========================
$stmtLogs = $pdo->prepare("
    SELECT message, created_at
    FROM turf_war_logs
    WHERE war_id = ?
    ORDER BY id DESC
    LIMIT 20
");
$stmtLogs->execute([$war['id']]);
$logs = $stmtLogs->fetchAll();

$attFid   = $war['attacker_faction_id'];
$defFid   = $war['defender_faction_id'];
$attScore = $war['attacker_score'];
$defScore = $war['defender_score'];
$target   = $war['target_score'];
$mySide   = ($char['faction_id'] == $attFid) ? 'attacker' : 'defender';
$csrfToken = csrf_token();
?>

<?php require_once 'templates/header.php'; ?>

<style>
@keyframes floatUp {
    0%   { opacity: 1; transform: translateY(0) scale(1); }
    100% { opacity: 0; transform: translateY(-70px) scale(1.3); }
}
.point-popup {
    position: fixed;
    pointer-events: none;
    font-weight: 800;
    font-size: 1.6rem;
    z-index: 9999;
    animation: floatUp 1.4s ease-out forwards;
}
@keyframes flashGreen {
    0%,100% { background: transparent; }
    30%     { background: rgba(34,197,94,0.25); }
}
@keyframes flashRed {
    0%,100% { background: transparent; }
    30%     { background: rgba(239,68,68,0.25); }
}
.flash-green { animation: flashGreen 0.6s ease-out; }
.flash-red   { animation: flashRed 0.6s ease-out; }
</style>

<div class="max-w-5xl mx-auto p-6">

    <h1 class="text-3xl font-bold mb-6">⚔️ Turf War</h1>

    <!-- SCOREBOARD -->
    <div class="grid grid-cols-2 gap-4 mb-6">

        <?php foreach (['attacker_faction_id', 'defender_faction_id'] as $side): ?>
        <?php
            $fid   = $war[$side];
            $f     = $factions[$fid] ?? ['name' => 'Unknown', 'color' => '#999999'];
            $score = ($side === 'attacker_faction_id') ? $attScore : $defScore;
            $alive = $counts[$fid]['alive'] ?? 0;
            $dead  = $counts[$fid]['dead']  ?? 0;
            $pct   = $target > 0 ? min(100, round(($score / $target) * 100)) : 0;
            $isMe  = ($char['faction_id'] == $fid);
            $scoreId = ($side === 'attacker_faction_id') ? 'attacker-score' : 'defender-score';
            $aliveId = ($side === 'attacker_faction_id') ? 'attacker-alive' : 'defender-alive';
            $deadId  = ($side === 'attacker_faction_id') ? 'attacker-dead'  : 'defender-dead';
            $barId   = ($side === 'attacker_faction_id') ? 'attacker-bar'   : 'defender-bar';
        ?>
        <div class="panel p-4 rounded <?= $isMe ? 'ring-1 ring-orange-500/50' : '' ?>">
            <div class="text-sm text-zinc-400">Faction <?= $isMe ? '<span class="text-orange-400 text-xs">(You)</span>' : '' ?></div>
            <div class="text-xl font-bold" style="color: <?= e($f['color']) ?>">
                <?= e($f['name']) ?>
            </div>
            <div class="mt-2 text-lg font-bold" id="<?= $scoreId ?>">
                <?= $score ?> / <?= $target ?>
            </div>
            <div class="w-full bg-zinc-800 rounded h-2 mt-2">
                <div id="<?= $barId ?>" class="h-2 rounded transition-all duration-500"
                    style="width: <?= $pct ?>%; background: <?= e($f['color']) ?>"></div>
            </div>
            <div class="text-sm text-zinc-400 mt-2">
                Alive: <span id="<?= $aliveId ?>"><?= $alive ?></span>
                | Dead: <span id="<?= $deadId ?>"><?= $dead ?></span>
            </div>
        </div>
        <?php endforeach; ?>

    </div>

    <!-- ACTION PANEL -->
    <div class="panel p-5 rounded-xl mb-6" id="combat-panel">

        <h2 class="text-xl font-bold mb-2">Combat</h2>

        <div id="combat-feedback" class="text-sm mb-3 hidden"></div>

        <?php if (!$player['is_alive']): ?>
            <div id="dead-msg" class="text-red-400 font-semibold">You are dead in this war.</div>
        <?php else: ?>

            <?php
                $myHp    = (int)($player['current_hp'] ?? 100);
                $myMaxHp = (int)($player['max_hp'] ?? 100);
                $hpPct   = $myMaxHp > 0 ? min(100, round($myHp / $myMaxHp * 100)) : 100;
                $hpColor = $hpPct > 50 ? '#22c55e' : ($hpPct > 25 ? '#f59e0b' : '#ef4444');
            ?>
            <div class="mb-4">
                <div class="flex justify-between text-xs text-zinc-400 mb-1">
                    <span>HP</span>
                    <span><span id="my-hp"><?= $myHp ?></span> / <span id="my-max-hp"><?= $myMaxHp ?></span></span>
                </div>
                <div class="w-full bg-zinc-800 rounded h-3">
                    <div id="my-hp-bar" class="h-3 rounded transition-all duration-300"
                         style="width: <?= $hpPct ?>%; background: <?= $hpColor ?>"></div>
                </div>
            </div>

            <form id="attack-form" method="post" action="attack.php">
                <?= csrf_input() ?>
                <button id="attack-btn" class="w-full py-3 rounded font-bold text-lg bg-red-600 hover:bg-red-500 transition-colors">
                    Attack Turf
                </button>
            </form>
            <div id="cooldown-bar-wrap" class="w-full bg-zinc-800 rounded h-1 mt-2 hidden">
                <div id="cooldown-bar" class="h-1 rounded bg-orange-500 transition-none" style="width:100%"></div>
            </div>

        <?php endif; ?>

    </div>

    <!-- WAR LOG -->
    <div class="panel p-5 rounded-xl">

        <h2 class="text-xl font-bold mb-4">War Activity</h2>

        <div id="war-log" class="space-y-1 text-sm">
            <?php if (!$logs): ?>
                <div class="text-zinc-400">No activity yet.</div>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <div class="text-zinc-300"><?= e($log['message']) ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

</div>

<script>
(function() {
    const WAR_ID     = <?= $war['id'] ?>;
    const TARGET     = <?= $target ?>;
    const MY_SIDE    = '<?= $mySide ?>';
    const CSRF       = <?= json_encode($csrfToken) ?>;
    const ATT_COLOR  = <?= json_encode($factions[$attFid]['color']) ?>;
    const DEF_COLOR  = <?= json_encode($factions[$defFid]['color']) ?>;
    const COOLDOWN   = 5;

    const attackForm = document.getElementById('attack-form');
    const attackBtn  = document.getElementById('attack-btn');
    const feedback   = document.getElementById('combat-feedback');
    const logEl      = document.getElementById('war-log');

    // ── Floating point popup ──────────────────────────────────────
    function showPointPopup(damage, killed, targetName) {
        if (!attackBtn) return;
        const rect = attackBtn.getBoundingClientRect();
        const el = document.createElement('div');
        el.className = 'point-popup';
        el.style.left = (rect.left + rect.width / 2 - 30) + 'px';
        el.style.top  = (rect.top + window.scrollY - 10) + 'px';
        el.style.color = killed ? '#f87171' : '#fb923c';
        el.textContent = killed
            ? `-${damage}HP 💀 ${targetName}`
            : (damage > 0 ? `-${damage}HP` : 'Miss!');
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 1500);
    }

    // ── Update own HP bar ─────────────────────────────────────────
    function updateMyHp(hp, maxHp) {
        const hpEl  = document.getElementById('my-hp');
        const barEl = document.getElementById('my-hp-bar');
        if (!hpEl || !barEl) return;
        hpEl.textContent = hp;
        const pct = maxHp > 0 ? Math.min(100, Math.round(hp / maxHp * 100)) : 0;
        barEl.style.width = pct + '%';
        barEl.style.background = pct > 50 ? '#22c55e' : (pct > 25 ? '#f59e0b' : '#ef4444');
    }

    // ── Cooldown bar ──────────────────────────────────────────────
    function runCooldown(seconds) {
        if (!attackBtn) return;
        attackBtn.disabled = true;
        attackBtn.classList.add('opacity-50', 'cursor-not-allowed');

        const wrap = document.getElementById('cooldown-bar-wrap');
        const bar  = document.getElementById('cooldown-bar');
        wrap.classList.remove('hidden');
        bar.style.transition = 'none';
        bar.style.width = '100%';

        requestAnimationFrame(() => {
            bar.style.transition = `width ${seconds}s linear`;
            bar.style.width = '0%';
        });

        setTimeout(() => {
            attackBtn.disabled = false;
            attackBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            wrap.classList.add('hidden');
        }, seconds * 1000);
    }

    // ── Update scores ─────────────────────────────────────────────
    function updateScores(data) {
        const attScore = document.getElementById('attacker-score');
        const defScore = document.getElementById('defender-score');
        const attBar   = document.getElementById('attacker-bar');
        const defBar   = document.getElementById('defender-bar');

        if (attScore) attScore.textContent = data.attacker_score + ' / ' + TARGET;
        if (defScore) defScore.textContent = data.defender_score + ' / ' + TARGET;
        if (attBar)   attBar.style.width   = Math.min(100, Math.round(data.attacker_score / TARGET * 100)) + '%';
        if (defBar)   defBar.style.width   = Math.min(100, Math.round(data.defender_score / TARGET * 100)) + '%';
    }

    // ── Update alive counts ───────────────────────────────────────
    function updateCounts(data) {
        const el = (id) => document.getElementById(id);
        if (el('attacker-alive')) el('attacker-alive').textContent = data.attacker_alive;
        if (el('attacker-dead'))  el('attacker-dead').textContent  = data.attacker_dead;
        if (el('defender-alive')) el('defender-alive').textContent = data.defender_alive;
        if (el('defender-dead'))  el('defender-dead').textContent  = data.defender_dead;
    }

    // ── Update war log ────────────────────────────────────────────
    let lastLogs = [];
    function updateLog(logs) {
        if (!logs || !logs.length) return;
        const topMsg = logs[0];
        if (topMsg === lastLogs[0]) return;
        lastLogs = logs;
        logEl.innerHTML = logs.map(m =>
            `<div class="text-zinc-300">${escHtml(m)}</div>`
        ).join('');
    }

    function escHtml(s) {
        return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    // ── Show feedback message ─────────────────────────────────────
    function showFeedback(msg, type) {
        if (!feedback) return;
        feedback.textContent = msg;
        feedback.className = 'text-sm mb-3 px-3 py-2 rounded ' +
            (type === 'kill' ? 'text-red-300 bg-red-900/30' : 'text-orange-300 bg-orange-900/20');
        feedback.classList.remove('hidden');
    }

    // ── Handle war end ────────────────────────────────────────────
    function handleWarEnd() {
        if (attackBtn) {
            attackBtn.disabled = true;
            attackBtn.textContent = 'War Over';
            attackBtn.className = 'w-full py-3 rounded font-bold text-lg bg-zinc-700 cursor-not-allowed';
        }
        showFeedback('War has ended! Returning to actions…', 'info');
        setTimeout(() => { window.location.href = 'actions.php'; }, 3000);
    }

    // ── AJAX Attack ───────────────────────────────────────────────
    if (attackForm) {
        attackForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            if (attackBtn.disabled) return;

            runCooldown(COOLDOWN);

            try {
                const fd = new FormData(attackForm);
                const res = await fetch('attack.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd
                });
                const data = await res.json();

                updateScores(data);
                showPointPopup(data.damage, data.killed, data.target_name);

                if (data.killed && data.target_name) {
                    showFeedback(`-${data.damage}HP — ${data.target_name} eliminated! (+${data.points} pts)`, 'kill');
                    document.getElementById(MY_SIDE === 'attacker' ? 'defender-alive' : 'attacker-alive')
                        ?.classList.add('flash-red');
                } else {
                    showFeedback(data.damage > 0 ? `-${data.damage}HP (+${data.points} pts)` : `+${data.points} pts`, 'hit');
                    document.getElementById(MY_SIDE === 'attacker' ? 'attacker-score' : 'defender-score')
                        ?.parentElement.classList.add('flash-green');
                }

                // Prepend new log entry
                if (data.message && logEl) {
                    const div = document.createElement('div');
                    div.className = 'text-zinc-300';
                    div.textContent = data.message;
                    logEl.prepend(div);
                    while (logEl.children.length > 20) logEl.lastChild.remove();
                }

                if (data.war_ended) {
                    handleWarEnd();
                }

            } catch (err) {
                console.error(err);
            }
        });
    }

    // ── Auto-poll ─────────────────────────────────────────────────
    setInterval(async () => {
        try {
            const res  = await fetch(`war_poll.php?_=${Date.now()}`);
            const data = await res.json();

            if (data.war_ended) { handleWarEnd(); return; }

            updateScores(data);
            updateCounts(data);
            updateLog(data.logs);

            if (data.my_hp !== undefined) {
                updateMyHp(data.my_hp, data.my_max_hp);
            }

            if (!data.my_alive) {
                const panel = document.getElementById('combat-panel');
                if (panel && !document.getElementById('dead-msg')) {
                    panel.innerHTML = '<h2 class="text-xl font-bold mb-2">Combat</h2>' +
                        '<div class="text-red-400 font-semibold">You are dead in this war.</div>';
                }
            }
        } catch(e) {}
    }, 3000);

})();
</script>

<?php require_once 'templates/footer.php'; ?>