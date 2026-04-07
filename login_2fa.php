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
$userId = $_SESSION['pending_2fa_user_id'] ?? null;
if (empty($userId)) {
    header('Location: login.php');
    exit;
}
$user = getUserById($config, (int)$userId);
if (empty($user) || empty($user['2fa_enabled']) || empty($user['2fa_secret'])) {
    header('Location: login.php');
    exit;
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = 'Invalid form submission. Please refresh and try again.';
    }
    $code = trim($_POST['two_factor_code'] ?? '');
    $backupCode = trim($_POST['backup_code'] ?? '');
    if ($code === '' && $backupCode === '') {
        $errors[] = 'Enter either your authenticator code or a backup recovery code.';
    }
    $authenticated = false;
    if (empty($errors)) {
        if ($code !== '' && verifyTotpCode($user['2fa_secret'], $code)) {
            $authenticated = true;
        } elseif ($backupCode !== '' && verifyBackupCode($config, $user['id'], $backupCode)) {
            $authenticated = true;
            setFlash('warning', ['A backup recovery code was used. Regenerate codes after signing in if possible.']);
        }
        if (!$authenticated) {
            $errors[] = 'The authentication code is incorrect or the backup code is invalid.';
        }
    }
    if (empty($errors) && $authenticated) {
        unset($_SESSION['pending_2fa_user_id']);
        loginUser($user);
        setFlash('success', ['Two-factor authentication successful. Welcome back, ' . htmlspecialchars($user['username']) . '!']);
        header('Location: dashboard.php');
        exit;
    }
    setFlash('danger', $errors);
    header('Location: login_2fa.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Verify Identity - SecureAuth</title>
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
            <span style="color: var(--text-secondary);" class="ms-lg-3">Verify Identity</span>
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
                            <i class="fas fa-mobile-alt" style="color: var(--brand-purple);"></i>
                        </div>
                        <h1 class="h3 mb-2">Verify Your Identity</h1>
                        <p class="subtitle">Enter the code from your authenticator app</p>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <?php if (!empty($flash)): ?>
                                <div class="alert alert-<?= htmlspecialchars($flash['type'] ?? 'info') ?> alert-dismissible fade show" role="alert">
                                    <?php foreach ((array)$flash['messages'] as $message): ?>
                                        <div><i class="fas fa-<?= $flash['type'] === 'warning' ? 'exclamation-triangle' : ($flash['type'] === 'success' ? 'check-circle' : 'info-circle') ?>" style="margin-right: 0.5rem;"></i><?= htmlspecialchars($message) ?></div>
                                    <?php endforeach; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>

                            <form method="post" action="login_2fa.php" novalidate>
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                                <div class="mb-4">
                                    <label for="two_factor_code" class="form-label">
                                        <i class="fas fa-key"></i> Authenticator Code
                                    </label>
                                    <input type="text" class="form-control form-control-lg text-center font-monospace" id="two_factor_code" name="two_factor_code" pattern="\d{6}" maxlength="6" placeholder="000000" style="letter-spacing: 0.5rem; font-size: 1.5rem;">
                                    <small class="form-text">6-digit code from your authenticator app</small>
                                </div>

                                <div class="divider"></div>

                                <div class="mb-4">
                                    <label for="backup_code" class="form-label">
                                        <i class="fas fa-key"></i> Or Use Backup Code
                                    </label>
                                    <input type="text" class="form-control" id="backup_code" name="backup_code" maxlength="16" placeholder="XXXX-XXXX-XXXX-XXXX" autocomplete="one-time-code">
                                    <small class="form-text">Use a backup code if you don't have access to your authenticator app</small>
                                </div>

                                <button type="submit" class="btn btn-primary w-100 mb-3">
                                    <i class="fas fa-sign-in-alt"></i> Verify & Sign In
                                </button>
                            </form>

                            <div class="divider"></div>

                            <div class="nav-links">
                                <a href="login.php">
                                    <i class="fas fa-sign-in-alt"></i> Back to Sign In
                                </a>
                                <a href="forgot.php">
                                    <i class="fas fa-key"></i> Forgot Password?
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <p class="text-muted" style="font-size: 0.85rem;">
                            <i class="fas fa-mobile-alt"></i> Your 2FA code is secure and never stored<br>
                            <i class="fas fa-lock"></i> This adds an extra layer of protection to your account
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
