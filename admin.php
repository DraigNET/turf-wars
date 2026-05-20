<?php
require_once 'includes/bootstrap.php';
require_login();

if (empty($_SESSION['is_admin'])) {
    redirect('dashboard.php');
}

$pdo = db();

$tab         = $_GET['tab'] ?? 'factions';
$city        = 1;
$cityNames   = [1 => 'Los Santos'];
$message     = null;
$messageType = 'success';

// =========================
// POST HANDLERS
// =========================
if (is_post()) {
    verify_csrf();

    $action = $_POST['action'] ?? '';

    $city = 1;

    // SAVE EXISTING FACTION
    if ($action === 'save_faction') {
        $id    = (int)$_POST['faction_id'];
        $name  = trim($_POST['name'] ?? '');
        $type  = ($_POST['type'] ?? '') === 'law' ? 'law' : 'gang';
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#aaaaaa';
        $icon  = trim($_POST['icon_path'] ?? '');

        if ($name === '') {
            $message = 'Name cannot be empty.';
            $messageType = 'error';
        } else {
            $pdo->prepare("UPDATE factions SET name=?, type=?, color=?, icon_path=? WHERE id=?")
                ->execute([$name, $type, $color, $icon, $id]);
            $message = "Faction updated.";
        }
        $tab = 'factions';
    }

    // ADD NEW FACTION
    if ($action === 'add_faction') {
        $name  = trim($_POST['name'] ?? '');
        $type  = ($_POST['type'] ?? '') === 'law' ? 'law' : 'gang';
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#aaaaaa';
        $icon  = trim($_POST['icon_path'] ?? '');

        if ($name === '') {
            $message = 'Name cannot be empty.';
            $messageType = 'error';
        } else {
            $pdo->prepare("INSERT INTO factions (name, type, color, icon_path, city_id) VALUES (?, ?, ?, ?, ?)")
                ->execute([$name, $type, $color, $icon, $city]);
            $newId   = $pdo->lastInsertId();
            $message = "Faction '{$name}' created (ID: {$newId}).";
        }
        $tab = 'factions';
    }

    // SAVE CHARACTER
    if ($action === 'save_character') {
        $id        = (int)$_POST['char_id'];
        $name      = trim($_POST['name'] ?? '');
        $factionId = (int)$_POST['faction_id'];
        $money     = max(0, (int)$_POST['money']);

        if ($name === '') {
            $message = 'Name cannot be empty.';
            $messageType = 'error';
        } else {
            $pdo->prepare("UPDATE characters SET name=?, faction_id=?, money=? WHERE id=?")
                ->execute([$name, $factionId, $money, $id]);
            $message = "Character updated.";
        }
        $tab = 'characters';
    }
}

// =========================
// LOAD DATA
// =========================
$stmtFactions = $pdo->prepare("SELECT * FROM factions WHERE city_id = ? ORDER BY id ASC");
$stmtFactions->execute([$city]);
$factions = $stmtFactions->fetchAll();

$stmtChars = $pdo->prepare("
    SELECT c.id, c.name, c.faction_id, c.money, c.level, f.name AS faction_name, f.color
    FROM characters c
    JOIN factions f ON f.id = c.faction_id
    WHERE c.city_id = ?
    ORDER BY c.level DESC, c.name ASC
");
$stmtChars->execute([$city]);
$characters = $stmtChars->fetchAll();

$char = get_character();
if (!$char) { redirect('character-create.php'); }

require_once 'templates/header.php';
?>

<div class="max-w-4xl mx-auto p-6">

    <div class="flex items-center gap-3 mb-5">
        <h1 class="text-2xl font-bold">⚙️ Admin Panel</h1>
        <span class="text-xs text-zinc-500 font-semibold bg-zinc-800 px-2 py-0.5 rounded">
            <?= $cityNames[$city] ?> &mdash; <?= count($factions) ?> factions &middot; <?= count($characters) ?> characters
        </span>
    </div>

    <?php if ($message): ?>
        <div class="mb-4 p-3 rounded-lg text-sm font-semibold
            <?= $messageType === 'error'
                ? 'bg-red-900/30 border border-red-700 text-red-300'
                : 'bg-green-900/30 border border-green-700 text-green-300' ?>">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <!-- MAIN TABS -->
    <div class="flex gap-2 mb-3">
        <a href="?tab=factions&city=<?= $city ?>"
           class="px-4 py-1.5 rounded-lg text-sm font-semibold transition-colors
               <?= $tab === 'factions' ? 'text-white' : 'bg-zinc-800 hover:bg-zinc-700 text-zinc-300' ?>"
           style="<?= $tab === 'factions' ? 'background:var(--accent)' : '' ?>">
            Factions
        </a>
        <a href="?tab=characters&city=<?= $city ?>"
           class="px-4 py-1.5 rounded-lg text-sm font-semibold transition-colors
               <?= $tab === 'characters' ? 'text-white' : 'bg-zinc-800 hover:bg-zinc-700 text-zinc-300' ?>"
           style="<?= $tab === 'characters' ? 'background:var(--accent)' : '' ?>">
            Characters
        </a>
    </div>

    <?php if ($tab === 'factions'): ?>

    <!-- ===========================
         FACTIONS TAB
    =========================== -->
    <div class="space-y-2 mb-6">
        <?php foreach ($factions as $f): ?>
        <div class="panel rounded-xl overflow-hidden">

            <!-- ROW HEADER (click to expand) -->
            <div class="flex items-center gap-3 p-4 cursor-pointer select-none admin-toggle"
                 data-target="faction-form-<?= $f['id'] ?>">
                <div class="w-3 h-3 rounded-full flex-shrink-0" style="background:<?= e($f['color']) ?>"></div>
                <?php if ($f['icon_path']): ?>
                    <img src="/<?= e($f['icon_path']) ?>" class="w-6 h-6 object-contain flex-shrink-0">
                <?php endif; ?>
                <span class="font-semibold text-sm flex-1"><?= e($f['name']) ?></span>
                <span class="text-xs px-2 py-0.5 rounded font-semibold
                    <?= $f['type'] === 'law' ? 'bg-blue-900/40 text-blue-300' : 'bg-red-900/40 text-red-300' ?>">
                    <?= $f['type'] ?>
                </span>
                <span class="text-zinc-600 text-xs">ID <?= $f['id'] ?></span>
                <span class="admin-chevron text-zinc-500 text-sm">&#x25BE;</span>
            </div>

            <!-- EDIT FORM (hidden by default) -->
            <form method="post" class="admin-form hidden border-t border-zinc-700/40 px-4 pb-4 pt-3"
                  id="faction-form-<?= $f['id'] ?>">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="save_faction">
                <input type="hidden" name="faction_id" value="<?= $f['id'] ?>">
                <input type="hidden" name="city" value="<?= $city ?>">

                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="text-xs text-zinc-400 block mb-1">Name</label>
                        <input name="name" value="<?= e($f['name']) ?>" class="input text-sm w-full">
                    </div>
                    <div>
                        <label class="text-xs text-zinc-400 block mb-1">Type</label>
                        <select name="type" class="input text-sm w-full">
                            <option value="gang" <?= $f['type'] === 'gang' ? 'selected' : '' ?>>Gang</option>
                            <option value="law"  <?= $f['type'] === 'law'  ? 'selected' : '' ?>>Law Enforcement</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-zinc-400 block mb-1">Colour</label>
                        <div class="flex gap-2 items-center">
                            <input type="color" value="<?= e($f['color']) ?>"
                                class="h-9 w-14 rounded cursor-pointer border border-zinc-700 bg-zinc-800 p-0.5 flex-shrink-0"
                                oninput="this.nextElementSibling.value=this.value">
                            <input type="text" name="color" value="<?= e($f['color']) ?>"
                                class="input text-sm font-mono flex-1"
                                oninput="if(/^#[0-9a-fA-F]{6}$/.test(this.value))this.previousElementSibling.value=this.value">
                        </div>
                    </div>
                    <div>
                        <label class="text-xs text-zinc-400 block mb-1">Icon Path</label>
                        <div class="flex gap-2 items-center">
                            <input name="icon_path" value="<?= e($f['icon_path']) ?>"
                                class="input text-sm flex-1"
                                placeholder="assets/img/icons/..."
                                oninput="updateIconPreview(this)">
                            <img id="icon-preview-<?= $f['id'] ?>"
                                src="/<?= e($f['icon_path']) ?>"
                                class="w-7 h-7 object-contain flex-shrink-0"
                                onerror="this.style.opacity='0.2'"
                                style="<?= $f['icon_path'] ? '' : 'opacity:0.2' ?>">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button class="action-btn px-5 py-1.5 rounded font-semibold text-sm text-white"
                            style="background:var(--accent)">
                        Save
                    </button>
                </div>
            </form>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ADD NEW FACTION -->
    <div class="panel rounded-xl overflow-hidden">
        <div class="flex items-center gap-2 cursor-pointer select-none admin-toggle p-4"
             data-target="add-faction-form">
            <span class="panel-title" style="margin:0">+ Add New Faction</span>
            <span class="admin-chevron text-zinc-500 text-sm ml-auto">&#x25BE;</span>
        </div>

        <form method="post" class="admin-form hidden border-t border-zinc-700/40 px-4 pb-4 pt-3"
              id="add-faction-form">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="add_faction">
            <input type="hidden" name="city" value="<?= $city ?>">

            <div class="grid grid-cols-2 gap-3 mb-3">
                <div>
                    <label class="text-xs text-zinc-400 block mb-1">Name</label>
                    <input name="name" class="input text-sm w-full" placeholder="e.g. Grove Street Families">
                </div>
                <div>
                    <label class="text-xs text-zinc-400 block mb-1">Type</label>
                    <select name="type" class="input text-sm w-full">
                        <option value="gang">Gang</option>
                        <option value="law">Law Enforcement</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-zinc-400 block mb-1">Colour</label>
                    <div class="flex gap-2 items-center">
                        <input type="color" value="#aaaaaa"
                            class="h-9 w-14 rounded cursor-pointer border border-zinc-700 bg-zinc-800 p-0.5 flex-shrink-0"
                            oninput="this.nextElementSibling.value=this.value">
                        <input type="text" name="color" value="#aaaaaa"
                            class="input text-sm font-mono flex-1"
                            oninput="if(/^#[0-9a-fA-F]{6}$/.test(this.value))this.previousElementSibling.value=this.value">
                    </div>
                </div>
                <div>
                    <label class="text-xs text-zinc-400 block mb-1">Icon Path</label>
                    <input name="icon_path" class="input text-sm w-full" placeholder="assets/img/icons/...">
                </div>
            </div>

            <div class="flex justify-end">
                <button class="action-btn px-5 py-1.5 rounded font-semibold text-sm bg-green-700 hover:bg-green-600">
                    Create Faction
                </button>
            </div>
        </form>
    </div>

    <?php endif; ?>

    <?php if ($tab === 'characters'): ?>

    <!-- ===========================
         CHARACTERS TAB
    =========================== -->
    <div class="mb-4">
        <input type="text" placeholder="Search by name..."
               class="input text-sm w-64"
               oninput="filterChars(this.value)">
        <span class="text-xs text-zinc-500 ml-3"><?= count($characters) ?> characters</span>
    </div>

    <div class="space-y-2" id="char-list">
        <?php foreach ($characters as $c): ?>
        <div class="panel rounded-xl overflow-hidden char-row"
             data-name="<?= strtolower(e($c['name'])) ?>">

            <!-- ROW HEADER -->
            <div class="flex items-center gap-3 p-3 cursor-pointer select-none admin-toggle"
                 data-target="char-form-<?= $c['id'] ?>">
                <div>
                    <span class="font-semibold text-sm"><?= e($c['name']) ?></span>
                    <span class="ml-2 text-xs font-semibold" style="color:<?= e($c['color']) ?>">
                        <?= e($c['faction_name']) ?>
                    </span>
                </div>
                <div class="ml-auto flex items-center gap-4 text-xs text-zinc-500">
                    <span>Lv <?= $c['level'] ?></span>
                    <span class="text-green-400 font-semibold">$<?= number_format($c['money']) ?></span>
                    <span>ID <?= $c['id'] ?></span>
                </div>
                <span class="admin-chevron text-zinc-500 text-sm">&#x25BE;</span>
            </div>

            <!-- EDIT FORM -->
            <form method="post" class="admin-form hidden border-t border-zinc-700/40 px-4 pb-4 pt-3"
                  id="char-form-<?= $c['id'] ?>">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="save_character">
                <input type="hidden" name="char_id" value="<?= $c['id'] ?>">
                <input type="hidden" name="city" value="<?= $city ?>">

                <div class="grid grid-cols-3 gap-3 mb-3">
                    <div>
                        <label class="text-xs text-zinc-400 block mb-1">Name</label>
                        <input name="name" value="<?= e($c['name']) ?>" class="input text-sm w-full">
                    </div>
                    <div>
                        <label class="text-xs text-zinc-400 block mb-1">Faction</label>
                        <select name="faction_id" class="input text-sm w-full">
                            <?php foreach ($factions as $f): ?>
                                <option value="<?= $f['id'] ?>"
                                    <?= $f['id'] == $c['faction_id'] ? 'selected' : '' ?>>
                                    <?= e($f['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-zinc-400 block mb-1">Money</label>
                        <input name="money" type="number" value="<?= (int)$c['money'] ?>"
                               min="0" class="input text-sm w-full">
                    </div>
                </div>

                <div class="flex justify-end">
                    <button class="action-btn px-5 py-1.5 rounded font-semibold text-sm text-white"
                            style="background:var(--accent)">
                        Save
                    </button>
                </div>
            </form>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>

</div>

<script>
// Expand / collapse edit forms
document.querySelectorAll('.admin-toggle').forEach(toggle => {
    toggle.addEventListener('click', () => {
        const form    = document.getElementById(toggle.dataset.target);
        const chevron = toggle.querySelector('.admin-chevron');
        if (!form) return;
        const opening = form.classList.toggle('hidden') === false;
        if (chevron) chevron.innerHTML = opening ? '&#x25B4;' : '&#x25BE;';
    });
});

// Character name search filter
function filterChars(q) {
    q = q.toLowerCase();
    document.querySelectorAll('.char-row').forEach(row => {
        row.style.display = row.dataset.name.includes(q) ? '' : 'none';
    });
}

// Icon path preview update
function updateIconPreview(input) {
    const formId  = input.closest('form').id;
    const id      = formId.replace('faction-form-', '');
    const preview = document.getElementById('icon-preview-' + id);
    if (!preview) return;
    preview.src = '/' + input.value;
    preview.style.opacity = '1';
    preview.onerror = () => preview.style.opacity = '0.2';
}
</script>

<?php require_once 'templates/footer.php'; ?>
