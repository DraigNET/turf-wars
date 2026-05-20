<?php
require_once 'includes/bootstrap.php';

require_guest();

$error = null;

if (is_post()) {
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($username === '' || $password === '' || $confirm === '') {
        $error = "Please fill in all fields.";
    } elseif (!preg_match('/^[A-Za-z0-9_]{3,24}$/', $username)) {
        $error = "Username must be 3–24 characters (letters, numbers, underscore).";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {

        $stmt = db()->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);

        if ($stmt->fetch()) {
            $error = "Username already taken.";
        } else {

            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = db()->prepare("
                INSERT INTO users (username, password_hash, created_at)
                VALUES (?, ?, NOW())
            ");

            $stmt->execute([$username, $hash]);

            $newId = (int)db()->lastInsertId();
            login($newId);
            $_SESSION['is_admin'] = false;
            redirect('dashboard.php');
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>The Streets: Turf Wars - Register</title>

    <script src="https://cdn.tailwindcss.com"></script>
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

        <h1 class="text-xl font-bold text-center mb-6">Create Account</h1>

        <?php if ($error): ?>
            <div class="bg-red-500/20 border border-red-500/40 text-red-300 text-sm px-3 py-2 rounded mb-4">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" class="space-y-4">
            <?= csrf_input() ?>

            <input 
                name="username"
                placeholder="Username"
                class="w-full bg-black/40 border border-white/10 rounded px-3 py-2 text-sm focus:outline-none focus:border-orange-500"
            >

            <input 
                name="password"
                type="password"
                placeholder="Password"
                class="w-full bg-black/40 border border-white/10 rounded px-3 py-2 text-sm focus:outline-none focus:border-orange-500"
            >

            <input 
                name="confirm_password"
                type="password"
                placeholder="Confirm Password"
                class="w-full bg-black/40 border border-white/10 rounded px-3 py-2 text-sm focus:outline-none focus:border-orange-500"
            >

            <button class="w-full bg-orange-500 hover:bg-orange-600 text-black font-semibold py-2 rounded action-btn">
                Create Account
            </button>
        </form>

        <p class="text-xs text-center text-gray-400 mt-4">
            Already have an account? 
            <a href="login.php" class="text-orange-400 hover:underline">Login</a>
        </p>

    </div>

</div>

</body>
</html>