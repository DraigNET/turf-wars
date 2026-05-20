<?php
require_once 'includes/bootstrap.php';

$pdo = db();

$s = $pdo->prepare("SELECT COUNT(*) FROM characters WHERE city_id = 1");
$s->execute();
$lsPlayers = (int)$s->fetchColumn();

$s = $pdo->prepare("
    SELECT COUNT(*) FROM turf_wars tw
    JOIN turfs t ON t.id = tw.turf_id
    WHERE tw.status = 'active' AND t.city_id = 1
");
$s->execute();
$lsWars = (int)$s->fetchColumn();

$s = $pdo->prepare("SELECT COUNT(*) FROM turfs WHERE city_id = 1");
$s->execute();
$lsTurfs = (int)$s->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>The Streets: Turf Wars</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <meta name="google" content="notranslate">
    <link rel="icon" type="image/png" href="favicon.png">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body {
            height: 100%;
            margin: 0;
            background: #070709;
            color: #fff;
            font-family: system-ui, sans-serif;
            overflow-x: hidden;
        }

        .rj { font-family: 'Rajdhani', sans-serif; }

        /* ── TOP BAR ── */
        .topbar {
            height: 48px;
            background: rgba(0,0,0,0.7);
            border-bottom: 1px solid rgba(255,255,255,0.07);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            position: sticky;
            top: 0;
            z-index: 50;
            backdrop-filter: blur(8px);
        }

        /* ── HERO ── */
        .hero {
            text-align: center;
            padding: 52px 20px 36px;
        }
        .hero-eyebrow {
            font-family: 'Rajdhani', sans-serif;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3em;
            color: #52525b;
            margin-bottom: 12px;
        }
        .hero-title {
            font-family: 'Rajdhani', sans-serif;
            font-size: clamp(30px, 5vw, 52px);
            font-weight: 700;
            line-height: 1.05;
            margin: 0 0 14px;
        }
        .hero-sub {
            font-size: 14px;
            color: #71717a;
            max-width: 520px;
            margin: 0 auto 28px;
            line-height: 1.65;
        }

        /* ── CITY CARDS ── */
        .cities-grid {
            display: grid;
            grid-template-columns: minmax(0, 480px);
            gap: 20px;
            max-width: 540px;
            margin: 0 auto;
            padding: 0 24px 48px;
        }

        .city-card {
            position: relative;
            border-radius: 14px;
            padding: 28px 24px 24px;
            display: flex;
            flex-direction: column;
            gap: 0;
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

        /* Los Santos */
        .city-ls {
            background: linear-gradient(145deg, rgba(249,115,22,0.10) 0%, rgba(10,10,13,0.95) 55%);
            box-shadow: 0 0 0 1px rgba(249,115,22,0.18), 0 8px 40px rgba(249,115,22,0.08);
        }
        .city-ls:hover { box-shadow: 0 0 0 1px rgba(249,115,22,0.35), 0 16px 60px rgba(249,115,22,0.18); }
        .city-ls .city-accent { color: #f97316; }
        .city-ls .city-btn { background: #ea6a10; }
        .city-ls .city-btn:hover { background: #f97316; }
        .city-ls .city-glow {
            position: absolute;
            top: -40px; right: -40px;
            width: 180px; height: 180px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(249,115,22,0.20), transparent 70%);
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
            margin-bottom: 8px;
        }
        .city-tagline {
            font-size: 12.5px;
            color: #71717a;
            line-height: 1.6;
            margin-bottom: 20px;
            flex: 1;
        }
        .city-stats {
            display: flex;
            gap: 16px;
            margin-bottom: 20px;
            padding: 12px 0;
            border-top: 1px solid rgba(255,255,255,0.06);
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .city-stat-item { flex: 1; text-align: center; }
        .city-stat-val {
            font-family: 'Rajdhani', sans-serif;
            font-size: 20px;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 2px;
        }
        .city-stat-lbl {
            font-size: 10px;
            color: #52525b;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .city-factions {
            font-size: 11px;
            color: #52525b;
            margin-bottom: 18px;
            line-height: 1.5;
        }
        .city-btn {
            font-family: 'Rajdhani', sans-serif;
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            text-align: center;
            padding: 10px;
            border-radius: 8px;
            color: #fff;
            display: block;
            transition: all 0.18s;
            text-decoration: none;
        }

        /* ── FEATURES STRIP ── */
        .features-strip {
            border-top: 1px solid rgba(255,255,255,0.06);
            padding: 28px 24px;
            display: flex;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .feature-pill {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 6px;
            padding: 7px 14px;
            font-size: 11.5px;
            display: flex;
            align-items: center;
            gap: 6px;
            color: #a1a1aa;
        }

        /* ── CTA BUTTONS ── */
        .cta-btn {
            font-family: 'Rajdhani', sans-serif;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            transition: all 0.18s;
            font-size: 13px;
            padding: 7px 18px;
            border-radius: 7px;
            text-decoration: none;
        }
        .cta-primary {
            background: #f97316;
            color: #fff;
            box-shadow: 0 0 16px rgba(249,115,22,0.35);
        }
        .cta-primary:hover { background: #ea6a10; transform: translateY(-1px); }
        .cta-secondary {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.13);
            color: #d4d4d8;
        }
        .cta-secondary:hover { background: rgba(255,255,255,0.14); }

        /* ── FOOTER ── */
        .site-footer {
            text-align: center;
            padding: 18px;
            font-size: 11px;
            color: #3f3f46;
            border-top: 1px solid rgba(255,255,255,0.05);
        }
    </style>
</head>
<body>

<!-- TOP BAR -->
<div class="topbar">
    <img src="assets/img/logo.png" style="height:26px;width:auto;">
    <div style="display:flex;gap:8px;">
        <a href="login.php"    class="cta-btn cta-secondary">Sign In</a>
        <a href="register.php" class="cta-btn cta-primary">Register</a>
    </div>
</div>

<!-- HERO -->
<div class="hero">
    <p class="hero-eyebrow">The Streets: Turf Wars</p>
    <h1 class="hero-title">
        Los Santos.<br>
        <span style="color:#f97316;">One Underworld.</span>
    </h1>
    <p class="hero-sub">
        A persistent browser-based gang war game. Pick your faction and fight for control of the streets — one turf at a time.
    </p>
    <div style="display:flex;gap:10px;justify-content:center;">
        <a href="register.php" class="cta-btn cta-primary" style="padding:9px 28px;font-size:14px;">Create Character</a>
        <a href="login.php"    class="cta-btn cta-secondary" style="padding:9px 28px;font-size:14px;">Sign In</a>
    </div>
</div>

<!-- CITY CARDS -->
<div class="cities-grid">

    <!-- LOS SANTOS -->
    <div class="city-card city-ls">
        <div class="city-glow"></div>
        <span class="city-year">Est. 1992</span>
        <div class="city-name city-accent">Los Santos</div>
        <p class="city-tagline">
            Sun-baked streets, rival gangs, and a war for every block. Grove Street, the Ballas, LSPD — only one crew ends up on top.
        </p>
        <div class="city-stats">
            <div class="city-stat-item">
                <div class="city-stat-val city-accent"><?= $lsPlayers ?></div>
                <div class="city-stat-lbl">Players</div>
            </div>
            <div class="city-stat-item">
                <div class="city-stat-val city-accent"><?= $lsTurfs ?></div>
                <div class="city-stat-lbl">Turfs</div>
            </div>
            <div class="city-stat-item">
                <div class="city-stat-val city-accent"><?= $lsWars ?></div>
                <div class="city-stat-lbl">Active Wars</div>
            </div>
        </div>
        <div class="city-factions">
            Grove Street &middot; Ballas &middot; Vagos &middot; Aztecas &middot; Triads &middot; LSPD
        </div>
        <a href="register.php" class="city-btn">Enter Los Santos &rarr;</a>
    </div>

</div>

<!-- FEATURES STRIP -->
<div class="features-strip">
    <div class="feature-pill">🏴 Capture Turf</div>
    <div class="feature-pill">⚔️ Faction Wars</div>
    <div class="feature-pill">💰 Run Crimes</div>
    <div class="feature-pill">📈 Level Up</div>
    <div class="feature-pill">🗺 Live Map</div>
    <div class="feature-pill">💬 City Chat</div>
    <div class="feature-pill">🔫 Weapons</div>
    <div class="feature-pill">🏆 Leaderboards</div>
</div>

<!-- FOOTER -->
<div class="site-footer">
    The Streets v0.7 &middot; DraigNET Studios
</div>

</body>
</html>
