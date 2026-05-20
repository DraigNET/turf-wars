<?php
$_factionSlug = !empty($char['icon_path'])
    ? pathinfo(basename($char['icon_path']), PATHINFO_FILENAME)
    : null;

$factionBackground = $_factionSlug
    ? "/assets/img/bg/{$_factionSlug}.png"
    : '/assets/img/bg/default.png';

$factionTheme = $_factionSlug ? "theme-{$_factionSlug}" : 'theme-default';

// Override theme accent with live faction colour from DB
$accentStyle = '';
$hexColor = ltrim($char['color'] ?? '', '#');
if (strlen($hexColor) === 6 && ctype_xdigit($hexColor)) {
    $r = hexdec(substr($hexColor, 0, 2));
    $g = hexdec(substr($hexColor, 2, 2));
    $b = hexdec(substr($hexColor, 4, 2));
    $accentStyle = "--accent:#{$hexColor};--accent-soft:rgba({$r},{$g},{$b},0.15);--accent-glow:rgba({$r},{$g},{$b},0.40);";
}

// XP bar calculation for topbar
$hudXpNeeded = 100 + ((($char['level'] ?? 1) - 1) * 40);
$hudHpPct    = isset($char['health'], $char['health_max']) && $char['health_max'] > 0
    ? min(100, round(($char['health'] / $char['health_max']) * 100)) : 0;
$hudXpPct    = $hudXpNeeded > 0
    ? min(100, round((($char['xp'] ?? 0) / $hudXpNeeded) * 100)) : 0;

$navPage    = basename($_SERVER['PHP_SELF']);
$navIsAdmin = $_SESSION['is_admin'] ?? false;

$navCityName = 'Los Santos';

function nav_link(string $href, string $icon, string $label, string $current): string {
    $active = ($current === basename($href)) ? ' active' : '';
    return "<a href=\"{$href}\" class=\"nav-item{$active}\">
        <span class=\"nav-icon\">{$icon}</span>
        <span class=\"nav-label\">{$label}</span>
    </a>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>The Streets: Turf Wars</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="/assets/js/game.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <meta name="google" content="notranslate">
    <link rel="icon" type="image/png" href="/favicon.png">
    <script>window.CSRF_TOKEN = "<?= csrf_token() ?>";</script>
</head>

<body class="game-shell <?= $factionTheme ?>" style="<?= $accentStyle ?>">

<!-- Fixed faction background -->
<div class="fixed inset-0 z-0 pointer-events-none">
    <div class="absolute inset-0 bg-cover bg-center opacity-15"
         style="background-image: url('<?= $factionBackground ?>');"></div>
    <div class="absolute inset-0 bg-black opacity-70"></div>
</div>

<!-- TOP HUD BAR -->
<div class="game-topbar relative z-10">

    <!-- Logo -->
    <div class="topbar-logo">
        <img src="/assets/img/logo.png" alt="The Streets">
    </div>

    <div class="topbar-sep"></div>

    <?php if (!empty($char)): ?>

    <!-- HP Bar -->
    <div class="topbar-stat">
        <span class="lbl">Health</span>
        <div class="topbar-bar-row">
            <div class="topbar-track">
                <div class="topbar-fill hp" style="width:<?= $hudHpPct ?>%"></div>
            </div>
            <span class="topbar-val" id="topbar-hp-val"><?= $char['health'] ?>/<?= $char['health_max'] ?></span>
        </div>
    </div>

    <!-- XP Bar -->
    <div class="topbar-stat">
        <span class="lbl">XP</span>
        <div class="topbar-bar-row">
            <div class="topbar-track">
                <div class="topbar-fill xp" style="width:<?= $hudXpPct ?>%"></div>
            </div>
            <span class="topbar-val" id="topbar-xp-val"><?= $char['xp'] ?>/<?= $hudXpNeeded ?></span>
        </div>
    </div>

    <div class="topbar-sep"></div>

    <!-- Cash -->
    <div class="topbar-qs">
        <span class="ql">Cash</span>
        <span class="qv text-green-400" id="topbar-cash">$<?= number_format($char['money']) ?></span>
    </div>

    <!-- Energy -->
    <div class="topbar-qs">
        <span class="ql">Energy</span>
        <span class="qv text-yellow-300" id="topbar-energy"><?= $char['energy'] ?>/<?= $char['energy_max'] ?></span>
    </div>

    <!-- Food -->
    <div class="topbar-qs">
        <span class="ql">Food</span>
        <span class="qv <?= ($char['food'] < 25) ? 'text-red-400' : 'text-orange-400' ?>">
            <?= $char['food'] ?>/100
        </span>
    </div>

    <div class="topbar-sep"></div>

    <!-- Player badge -->
    <div class="topbar-player">
        <img src="<?= e($char['icon_path']) ?>" style="height:22px;width:auto;">
        <span class="topbar-name"><?= e($char['name']) ?></span>
        <span class="topbar-badge" style="color:<?= e($char['color']) ?>;border-color:<?= e($char['color']) ?>;background:<?= e($char['color']) ?>22;">
            <?= e($char['faction_name']) ?>
        </span>
        <span class="topbar-lvl" id="topbar-level">Lv.<?= $char['level'] ?></span>
    </div>

    <?php endif; ?>

</div>

<!-- GAME BODY -->
<div class="game-body relative z-10">

    <!-- LEFT NAV SIDEBAR -->
    <nav class="game-nav">
        <?= nav_link('/dashboard.php', '⌂',  'Dashboard',  $navPage) ?>
        <?= nav_link('/actions.php',   '⚡',  'Actions',    $navPage) ?>
        <?= nav_link('/turf.php',      '🗺',  'Turf Map',   $navPage) ?>
        <?= nav_link('/faction.php',   '👥', 'My Faction', $navPage) ?>
        <?= nav_link('/players.php',   '🏆', 'Players',    $navPage) ?>
        <?php if ($navIsAdmin): ?>
            <?= nav_link('/admin.php', '⚙️',  'Admin',      $navPage) ?>
            <?= nav_link('/logs.php',  '🔧', 'Dev Logs',   $navPage) ?>
        <?php endif; ?>
        <?= nav_link('/manual.php',    '📖', 'Manual',     $navPage) ?>

        <div class="nav-spacer"></div>

        <a href="/logout.php" class="nav-item nav-logout">
            <span class="nav-icon">↩</span>
            <span class="nav-label">Logout</span>
        </a>
        <div class="nav-version">The Streets v0.7<br><span style="font-size:10px;opacity:0.5"><?= e($navCityName) ?></span></div>
    </nav>

    <!-- MAIN CONTENT (opened here — closed in footer.php) -->
    <div class="game-main <?= $gameMainClass ?? '' ?>">
