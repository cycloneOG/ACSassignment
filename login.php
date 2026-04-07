<?php
session_start();
require_once __DIR__ . '/utils.php';
$config = loadEnv();
initializeDatabaseSchema($config);
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = 'Invalid form submission. Please refresh and try again.';
    }
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        $errors[] = 'Username and password are required.';
    } elseif (!validateUsername($username)) {
        $errors[] = 'Invalid username format.';
    }
    if (empty($errors)) {
        $user = getUserByUsername($config, $username);
        if (empty($user) || !password_verify($password, $user['password_hash'])) {
            $errors[] = 'Invalid username or password.';
        } elseif ((int)$user['is_active'] !== 1) {
            $errors[] = 'This account is not active.';
        } elseif (!empty($user['email']) && (int)$user['email_verified'] !== 1) {
            $_SESSION['pending_verification_user_id'] = $user['id'];
            setFlash('warning', ['Please verify your email address to continue.']);
            header('Location: email-verification-pending.php');
            exit;
        } elseif (!empty($user['2fa_enabled']) && !empty($user['2fa_secret'])) {
            $_SESSION['pending_2fa_user_id'] = $user['id'];
            setFlash('info', ['Two-factor authentication is required. Enter your verification code.']);
            header('Location: login_2fa.php');
            exit;
        } else {
            loginUser($user);
            setFlash('success', ['Welcome back, ' . htmlspecialchars($user['username']) . '!']);
            header('Location: dashboard.php');
            exit;
        }
    }
    if (!empty($errors)) {
        setFlash('danger', $errors);
        header('Location: login.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Sign In - SecureAuth</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">
<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container-lg">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-shield-alt"></i> SecureAuth
        </a>
        <div class="d-none d-lg-block">
            <div class="nav-divider"></div>
        </div>
        <div class="collapse navbar-collapse" id="navbarNav">
            <span style="color: var(--text-secondary);" class="ms-lg-3">Sign In</span>
        </div>
    </div>
</nav>

<main>
    <div class="page-container">
        <div class="container-lg">
            <div class="row justify-content-center">
                <div class="col-lg-5 col-md-6 col-sm-8">
                    <div class="text-center mb-4">
                        <div style="font-size: 2.5rem; margin-bottom: 1rem;">
                            <i class="fas fa-sign-in-alt" style="color: var(--brand-purple);"></i>
                        </div>
                        <h1 class="h3 mb-2">Welcome Back</h1>
                        <p class="subtitle">Sign in to access your account</p>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <?php if (!empty($flash)): ?>
                                <div class="alert alert-<?= htmlspecialchars($flash['type'] ?? 'info') ?> alert-dismissible fade show" role="alert">
                                    <?php foreach ((array)$flash['messages'] as $message): ?>
                                        <div><?= htmlspecialchars($message) ?></div>
                                    <?php endforeach; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>

                            <form method="post" action="login.php" novalidate>
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                
                                <div class="mb-3">
                                    <label for="username" class="form-label">
                                        <i class="fas fa-user"></i> Username
                                    </label>
                                    <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username" required>
                                </div>

                                <div class="mb-3">
                                    <label for="password" class="form-label">
                                        <i class="fas fa-lock"></i> Password
                                    </label>
                                    <div class="password-input-wrapper">
                                        <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                                        <button type="button" class="password-toggle-btn" data-toggle-id="password" onclick="togglePasswordVisibility('password')">👁️ Show</button>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary w-100 mb-3">
                                    <i class="fas fa-sign-in-alt"></i> Sign In
                                </button>
                            </form>

                            <div class="divider"></div>

                            <div class="nav-links">
                                <a href="forgot.php">
                                    <i class="fas fa-key"></i> Forgot Password?
                                </a>
                                <a href="index.php">
                                    <i class="fas fa-user-plus"></i> Create Account
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <p class="text-muted" style="font-size: 0.85rem;">
                            <i class="fas fa-info-circle"></i> Secure sign-in with encrypted credentials<br>
                            Protected by CSRF tokens and secure session management
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Footer -->
<footer>
    <div class="container-lg">
        <div class="text-center">
            <small>© 2024 SecureAuth. All rights reserved. | <a href="#">Security</a> | <a href="#">Privacy</a></small>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
