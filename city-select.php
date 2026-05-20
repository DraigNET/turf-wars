<?php
require_once 'includes/bootstrap.php';
require_login();

$pdo = db();

// Load all cities
$cities = $pdo->query("SELECT * FROM cities ORDER BY id ASC")->fetchAll();

// Find which cities the user already has a character in
$stmt = $pdo->prepare("SELECT city_id, name FROM characters WHERE user_id = ?");
$stmt->execute([user_id()]);
$charRows = $stmt->fetchAll();
$existingCities = array_column($charRows, 'city_id');
$charNames = array_column($charRows, 'name', 'city_id');

// Handle city selection
if (is_post()) {
    verify_csrf();
    $cityId = (int)($_POST['city_id'] ?? 0);

    $valid = array_column($cities, 'id');
    if (!in_array($cityId, $valid)) {
        redirect('city-select.php');
    }

    $_SESSION['city_id'] = $cityId;

    if (in_array($cityId, $existingCities)) {
        redirect('dashboard.php');
    } else {
        redirect('character-create.php');
    }
}

$cityMeta = [
    1 => [
        'tagline'  => 'Sun-baked streets, rival gangs, and a war for every block. Grove Street, the Ballas, LSPD — only one crew ends up on top.',
        'accent'   => '#f97316',
        'year'     => 'Est. 1992',
        'subtitle' => 'Los Santos, San Andreas',
        'class'    => 'city-ls',
    ],
    2 => [
        'tagline'  => 'Neon lights, cocaine, and power plays on the strip. The Vercettis, Cubans, Haitians — paradise has never been so dangerous.',
        'accent'   => '#ec4899',
        'year'     => 'Est. 1986',
        'subtitle' => 'Vice City, Florida',
        'class'    => 'city-vc',
    ],
    3 => [
        'tagline'  => 'A cold, unforgiving city where the mob rules and the streets are split between families, triads, and the law.',
        'accent'   => '#60a5fa',
        'year'     => 'Est. 2001',
        'subtitle' => 'Liberty City, New York',
        'class'    => 'city-lc',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>The Streets: Turf Wars — Choose City</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/css/dashboard.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@600;700&display=swap" rel="stylesheet">
    <meta name="google" content="notranslate">
    <link rel="icon" type="image/png" href="/favicon.png">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body {
            margin: 0;
            background: #070709;
            color: #fff;
            font-family: system-ui, sans-serif;
        }

        .page-header {
            text-align: center;
            padding: 48px 16px 32px;
        }
        .page-header img {
            height: 80px;
            margin-bottom: 16px;
        }
        .page-header h1 {
            font-family: 'Rajdhani', sans-serif;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 1px;
            color: #fff;
            margin-bottom: 6px;
        }
        .page-header p {
            font-size: 14px;
            color: #71717a;
        }

        .city-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 24px 48px;
        }
        @media (max-width: 860px) {
            .city-grid { grid-template-columns: 1fr; max-width: 480px; }
        }

        .city-card {
            position: relative;
            border-radius: 14px;
            padding: 24px 22px 22px;
            display: flex;
            flex-direction: column;
            transition: transform 0.22s, box-shadow 0.22s;
            overflow: hidden;
        }
        .city-card::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            pointer-events: none;
            border: 1px solid rgba(255,255,255,0.08);
        }
        .city-card:hover { transform: translateY(-4px); }

        /* Los Santos — orange */
        .city-ls {
            background: linear-gradient(145deg, rgba(249,115,22,0.10) 0%, rgba(10,10,13,0.95) 55%);
            box-shadow: 0 0 0 1px rgba(249,115,22,0.18), 0 8px 40px rgba(249,115,22,0.08);
        }
        .city-ls:hover { box-shadow: 0 0 0 1px rgba(249,115,22,0.35), 0 16px 60px rgba(249,115,22,0.18); }
        .city-ls .city-accent { color: #f97316; }
        .city-ls .city-btn  { background: #ea6a10; }
        .city-ls .city-btn:hover { background: #f97316; }
        .city-ls .city-glow { background: radial-gradient(circle, rgba(249,115,22,0.20), transparent 70%); }

        /* Vice City — pink */
        .city-vc {
            background: linear-gradient(145deg, rgba(236,72,153,0.10) 0%, rgba(10,10,13,0.95) 55%);
            box-shadow: 0 0 0 1px rgba(236,72,153,0.18), 0 8px 40px rgba(236,72,153,0.08);
        }
        .city-vc:hover { box-shadow: 0 0 0 1px rgba(236,72,153,0.35), 0 16px 60px rgba(236,72,153,0.18); }
        .city-vc .city-accent { color: #ec4899; }
        .city-vc .city-btn  { background: #db2777; }
        .city-vc .city-btn:hover { background: #ec4899; }
        .city-vc .city-glow { background: radial-gradient(circle, rgba(236,72,153,0.20), transparent 70%); }

        /* Liberty City — blue */
        .city-lc {
            background: linear-gradient(145deg, rgba(59,130,246,0.10) 0%, rgba(10,10,13,0.95) 55%);
            box-shadow: 0 0 0 1px rgba(59,130,246,0.18), 0 8px 40px rgba(59,130,246,0.08);
        }
        .city-lc:hover { box-shadow: 0 0 0 1px rgba(59,130,246,0.35), 0 16px 60px rgba(59,130,246,0.18); }
        .city-lc .city-accent { color: #60a5fa; }
        .city-lc .city-btn  { background: #2563eb; }
        .city-lc .city-btn:hover { background: #3b82f6; }
        .city-lc .city-glow { background: radial-gradient(circle, rgba(59,130,246,0.20), transparent 70%); }

        .city-glow {
            position: absolute;
            top: -40px; right: -40px;
            width: 180px; height: 180px;
            border-radius: 50%;
            pointer-events: none;
        }

        .city-year {
            font-family: 'Rajdhani', sans-serif;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            padding: 3px 9px;
            border-radius: 20px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.10);
            color: #a1a1aa;
            display: inline-block;
            margin-bottom: 14px;
        }
        .city-name {
            font-family: 'Rajdhani', sans-serif;
            font-size: 28px;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 6px;
        }
        .city-sub {
            font-size: 12px;
            color: #52525b;
            margin-bottom: 10px;
        }
        .city-tagline {
            font-size: 12.5px;
            color: #71717a;
            line-height: 1.6;
            margin-bottom: 16px;
            flex: 1;
        }
        .city-status {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            padding: 7px 10px;
            border-radius: 6px;
            margin-bottom: 14px;
            border-top: 1px solid rgba(255,255,255,0.06);
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .city-status.has-char { color: #86efac; }
        .city-status.no-char  { color: #52525b; }

        .city-btn {
            width: 100%;
            padding: 10px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-family: 'Rajdhani', sans-serif;
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: #fff;
            transition: background 0.18s, transform 0.15s;
        }
        .city-btn:hover { transform: translateY(-1px); }

        .logout-link {
            display: block;
            text-align: center;
            padding: 16px 0 40px;
            font-size: 13px;
            color: #3f3f46;
        }
        .logout-link a { color: #71717a; text-decoration: underline; }
    </style>
</head>
<body class="text-white">

<div class="page-header">
    <img src="/assets/img/logo.png" alt="The Streets">
    <h1>Choose Your City</h1>
    <p>You can have one character per city. Cities are separate game worlds.</p>
</div>

<div class="city-grid">
    <?php foreach ($cities as $city):
        $id      = (int)$city['id'];
        $meta    = $cityMeta[$id] ?? ['tagline'=>'','accent'=>'#fff','year'=>'','subtitle'=>'','class'=>''];
        $hasChar  = in_array($id, $existingCities);
        $charName = $charNames[$id] ?? null;
    ?>
    <div class="city-card <?= $meta['class'] ?>">
        <div class="city-glow"></div>
        <span class="city-year"><?= $meta['year'] ?></span>
        <div class="city-name city-accent"><?= e($city['name']) ?></div>
        <div class="city-sub"><?= $meta['subtitle'] ?></div>
        <div class="city-tagline"><?= $meta['tagline'] ?></div>

        <div class="city-status <?= $hasChar ? 'has-char' : 'no-char' ?>">
            <?php if ($hasChar): ?>
                &#10003; <?= e($charName) ?> — continue playing
            <?php else: ?>
                + No character yet — create one to start
            <?php endif; ?>
        </div>

        <form method="post">
            <?= csrf_input() ?>
            <input type="hidden" name="city_id" value="<?= $id ?>">
            <button type="submit" class="city-btn">
                <?= $hasChar ? 'Enter ' . e($city['name']) : 'Create Character' ?>
            </button>
        </form>
    </div>
    <?php endforeach; ?>
</div>

<div class="logout-link">
    <a href="/logout.php">Logout</a>
</div>

</body>
</html>
