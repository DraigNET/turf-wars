<?php
require_once 'includes/bootstrap.php';

require_guest();

$error = null;

// Brute-force protection: max 10 failures per 10 minutes per session
$_SESSION['login_fails']      = $_SESSION['login_fails']      ?? 0;
$_SESSION['login_locked_until'] = $_SESSION['login_locked_until'] ?? 0;

$lockedUntil = (int)$_SESSION['login_locked_until'];
$isLocked    = $lockedUntil > time();

if (is_post()) {
    verify_csrf();

    if ($isLocked) {
        $wait  = ceil(($lockedUntil - time()) / 60);
        $error = "Too many failed attempts. Try again in {$wait} minute(s).";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = db()->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $_SESSION['login_fails']++;
            if ($_SESSION['login_fails'] >= 10) {
                $_SESSION['login_locked_until'] = time() + 600; // 10 min lockout
                $_SESSION['login_fails']        = 0;
                $error = "Too many failed attempts. Try again in 10 minutes.";
            } else {
                $error = "Invalid login";
            }
        } elseif ($config['app']['maintenance'] && !$user['is_admin']) {
            $error = "The game is currently under maintenance. Check back soon.";
        } else {
            $_SESSION['login_fails']        = 0;
            $_SESSION['login_locked_until'] = 0;
            login($user['id']);
            $_SESSION['is_admin'] = (bool)$user['is_admin'];
            redirect('dashboard.php');
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>The Streets: Turf Wars - Login</title>

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Your CSS -->
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <meta name="google" content="notranslate">
</head>
<body class="text-white min-h-screen flex items-center justify-center">

<div class="w-full max-w-md px-4">

    <div class="panel rounded-xl p-6">

        <!-- Logo -->
        <div style="display:flex; justify-content:center; align-items:center;">
            <img src="/assets/img/logo.png" style="height:200px; display:block;">
        </div>

        <!-- Title -->
        <h1 class="text-xl font-bold text-center mb-5">Login</h1>

        <?php if ($error): ?>
            <div class="bg-red-500/20 border border-red-500/40 text-red-300 text-sm px-3 py-2 rounded mb-4">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" class="space-y-4">
            <?= csrf_input() ?>

            <div>
                <input 
                    name="username" 
                    placeholder="Username"
                    class="w-full bg-black/40 border border-white/10 rounded px-3 py-2 text-sm focus:outline-none focus:border-orange-500"
                >
            </div>

            <div>
                <input 
                    name="password" 
                    type="password" 
                    placeholder="Password"
                    class="w-full bg-black/40 border border-white/10 rounded px-3 py-2 text-sm focus:outline-none focus:border-orange-500"
                >
            </div>

            <button 
                class="w-full bg-orange-500 hover:bg-orange-600 text-black font-semibold py-2 rounded action-btn">
                Login
            </button>
        </form>

        <p class="text-xs text-center text-gray-400 mt-4">
            No account? <a href="register.php" class="text-orange-400 hover:underline">Register</a>
        </p>

    </div>

</div>

</body>
</html>