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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        setFlash('danger', ['Invalid form submission.']);
        header('Location: forgot.php');
        exit;
    }
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        setFlash('danger', ['Please provide a valid email address.']);
        header('Location: forgot.php');
        exit;
    }
    $user = getUserByEmail($config, $email);
    if (!empty($user) && !empty($user['email'])) {
        $token = createPasswordResetRequest($config, $user['id']);
        sendPasswordResetEmail($user['email'], $user['username'], $token, $config);
    }
    setFlash('success', ['If an account with that email exists, password reset instructions have been sent.']);
    header('Location: forgot.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Reset Password - SecureAuth</title>
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
            <span style="color: var(--text-secondary);" class="ms-lg-3">Reset Password</span>
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
                            <i class="fas fa-key" style="color: var(--brand-purple);"></i>
                        </div>
                        <h1 class="h3 mb-2">Reset Your Password</h1>
                        <p class="subtitle">We'll send you instructions to reset your password</p>
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

                            <form method="post" action="forgot.php" novalidate>
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">
                                        <i class="fas fa-envelope"></i> Email Address
                                    </label>
                                    <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email address" required>
                                    <small class="form-text">We'll send password reset instructions to this address</small>
                                </div>

                                <button type="submit" class="btn btn-primary w-100 mb-3">
                                    <i class="fas fa-paper-plane"></i> Send Reset Instructions
                                </button>
                            </form>

                            <div class="divider"></div>

                            <div class="nav-links">
                                <a href="login.php">
                                    <i class="fas fa-sign-in-alt"></i> Back to Sign In
                                </a>
                                <a href="index.php">
                                    <i class="fas fa-user-plus"></i> Create Account
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <p class="text-muted" style="font-size: 0.85rem;">
                            <i class="fas fa-lock"></i> Your security is our priority<br>
                            Password reset links are secure and expire after a short time
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
</body>
</html>
