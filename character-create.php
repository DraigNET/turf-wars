<?php
require_once 'includes/bootstrap.php';

require_login();

$pdo    = db();
$cityId = 1;

// If user already has a character, go to dashboard
$stmt = $pdo->prepare("SELECT id FROM characters WHERE user_id = ? AND city_id = 1");
$stmt->execute([user_id()]);
if ($stmt->fetch()) {
    redirect('dashboard.php');
}

$city = ['name' => 'Los Santos'];

// Load factions for this city
$factions = $pdo->prepare("SELECT * FROM factions WHERE city_id = ? ORDER BY id ASC");
$factions->execute([$cityId]);
$factions = $factions->fetchAll();

// Default starting turf for this city
$defaultTurf = $pdo->prepare("SELECT id FROM turfs WHERE city_id = ? ORDER BY id ASC LIMIT 1");
$defaultTurf->execute([$cityId]);
$defaultTurfId = $defaultTurf->fetchColumn() ?: 1;

// Default (free) weapon for this city
$defaultWeapon = $pdo->prepare("SELECT id FROM weapons WHERE city_id = ? AND price = 0 LIMIT 1");
$defaultWeapon->execute([$cityId]);
$defaultWeaponId = $defaultWeapon->fetchColumn() ?: 1;

$error = null;

if (is_post()) {
    verify_csrf();

    $name       = trim($_POST['name'] ?? '');
    $faction_id = (int)($_POST['faction_id'] ?? 0);

    if ($name === '') {
        $error = "Enter a character name.";
    } elseif (strlen($name) < 3 || strlen($name) > 24) {
        $error = "Name must be 3–24 characters.";
    } elseif (!preg_match('/^[A-Za-z0-9_ ]+$/', $name)) {
        $error = "Invalid characters in name.";
    } elseif (!$faction_id) {
        $error = "Select a faction.";
    } else {
        $nameCheck = $pdo->prepare("SELECT id FROM characters WHERE LOWER(name) = LOWER(?) AND city_id = ?");
        $nameCheck->execute([$name, $cityId]);
        if ($nameCheck->fetch()) {
            $error = "That character name is already taken.";
        } else {
            // Verify faction belongs to this city
            $fCheck = $pdo->prepare("SELECT id FROM factions WHERE id = ? AND city_id = ?");
            $fCheck->execute([$faction_id, $cityId]);
            if (!$fCheck->fetch()) {
                $error = "Invalid faction selection.";
            } else {
                $pdo->prepare("
                    INSERT INTO characters
                        (user_id, city_id, name, faction_id, turf_id, weapon_id, energy_updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ")->execute([user_id(), $cityId, $name, $faction_id, $defaultTurfId, $defaultWeaponId]);

                redirect('dashboard.php');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>The Streets: Turf Wars - Create Character</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <meta name="google" content="notranslate">
</head>
<body class="text-white min-h-screen flex items-center justify-center">

<div class="w-full max-w-md px-4">
    <div class="panel rounded-xl p-6">

        <div style="display:flex; justify-content:center; align-items:center;">
            <img src="/assets/img/logo.png" style="height:160px; display:block;">
        </div>

        <h1 class="text-xl font-bold text-center mb-1">Create Character</h1>
        <p class="text-center text-sm text-gray-400 mb-5">
            <?= e($city['name']) ?>
        </p>

        <?php if ($error): ?>
            <div class="bg-red-500/20 border border-red-500/40 text-red-300 text-sm px-3 py-2 rounded mb-4">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" class="space-y-4">
            <?= csrf_input() ?>

            <input
                name="name"
                placeholder="Character Name"
                class="w-full bg-black/40 border border-white/10 rounded px-3 py-2 text-sm focus:outline-none focus:border-orange-500"
            >

            <div>
                <h3 class="panel-title mb-2">Select Faction</h3>
                <div class="space-y-2">
                    <?php foreach ($factions as $f): ?>
                        <label class="flex items-center gap-3 p-2 rounded cursor-pointer border border-white/5 hover:border-white/20 bg-white/5">
                            <input type="radio" name="faction_id" value="<?= $f['id'] ?>" class="accent-orange-500">
                            <img src="<?= e($f['icon_path']) ?>" class="w-6 h-6" onerror="this.style.display='none'">
                            <span style="color:<?= e($f['color']) ?>; font-weight:600;">
                                <?= e($f['name']) ?>
                            </span>
                            <?php if ($f['type'] === 'law'): ?>
                                <span class="text-xs text-blue-400 ml-auto">Law</span>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <button class="w-full bg-orange-500 hover:bg-orange-600 text-black font-semibold py-2 rounded action-btn">
                Create Character
            </button>
        </form>

        <p class="text-center text-xs text-gray-500 mt-4">
            <a href="/login.php" class="hover:text-gray-300">← Back to login</a>
        </p>

    </div>
</div>

</body>
</html>
