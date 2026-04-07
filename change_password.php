<?php
session_start();
require_once __DIR__ . '/utils.php';
$config = loadEnv();
initializeDatabaseSchema($config);
requireLogin();
$user = getCurrentUser($config);
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = 'Invalid CSRF token. Please refresh the form and try again.';
    }
    if ($currentPassword === '') {
        $errors[] = 'Current password is required.';
    } elseif (!password_verify($currentPassword, $user['password_hash'])) {
        $errors[] = 'Current password is incorrect.';
    }
    if ($newPassword === '') {
        $errors[] = 'New password is required.';
    }
    if ($newPassword !== $confirmPassword) {
        $errors[] = 'New password and confirmation do not match.';
    }
    $strength = evaluatePasswordStrength($newPassword, $user['username']);
    if ($strength['score'] < 4) {
        $errors[] = 'New password is too weak: ' . $strength['label'] . '. ' . implode(' ', $strength['advice']);
    }
    if ($currentPassword !== '' && $newPassword !== '' && password_verify($currentPassword, $user['password_hash']) && password_verify($newPassword, $user['password_hash'])) {
        $errors[] = 'Your new password must be different from your current password.';
    }
    if (empty($errors)) {
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        updateUserPassword($config, $user['id'], $newHash);
        if (!empty($user['email'])) {
            sendPasswordResetConfirmationEmail($user['email'], $user['username'], $config);
        }
        setFlash('success', ['Your password has been changed successfully.']);
        header('Location: dashboard.php');
        exit;
    }
    setFlash('danger', $errors);
    header('Location: change_password.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Change Password - SecureAuth</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">
<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container-lg">
        <a class="navbar-brand" href="dashboard.php">
            <i class="fas fa-shield-alt"></i> SecureAuth
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link active" href="change_password.php">
                        <i class="fas fa-key"></i> Change Password
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="two_factor_setup.php">
                        <i class="fas fa-mobile-alt"></i> 2FA
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> Sign Out
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main>
    <div class="page-container">
        <div class="container-lg">
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-8 col-sm-10">
                    <div class="text-center mb-4">
                        <div style="font-size: 2.5rem; margin-bottom: 1rem;">
                            <i class="fas fa-lock" style="color: var(--brand-purple);"></i>
                        </div>
                        <h1 class="h3 mb-2">Change Your Password</h1>
                        <p class="subtitle">Update your password to secure your account</p>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <?php if (!empty($flash)): ?>
                                <div class="alert alert-<?= htmlspecialchars($flash['type'] ?? 'info') ?> alert-dismissible fade show" role="alert">
                                    <?php foreach ((array)$flash['messages'] as $message): ?>
                                        <div><i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>" style="margin-right: 0.5rem;"></i><?= htmlspecialchars($message) ?></div>
                                    <?php endforeach; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>

                            <form id="changePasswordForm" method="post" action="change_password.php" novalidate>
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <input type="hidden" id="username" value="<?= htmlspecialchars($user['username']) ?>">

                                <div class="mb-3">
                                    <label for="current_password" class="form-label">
                                        <i class="fas fa-lock"></i> Current Password
                                    </label>
                                    <div class="password-input-wrapper">
                                        <input type="password" class="form-control" id="current_password" name="current_password" placeholder="Enter your current password" required>
                                        <button type="button" class="password-toggle-btn" data-toggle-id="current_password" onclick="togglePasswordVisibility('current_password')">👁️ Show</button>
                                    </div>
                                    <small class="form-text">We need to verify your current password to proceed</small>
                                </div>

                                <div class="divider"></div>

                                <div class="mb-3">
                                    <label for="new_password" class="form-label">
                                        <i class="fas fa-key"></i> New Password
                                    </label>
                                    <div class="password-input-wrapper">
                                        <input type="password" class="form-control" id="new_password" name="new_password" minlength="12" placeholder="Minimum 12 characters" required>
                                        <button type="button" class="password-toggle-btn" data-toggle-id="new_password" onclick="togglePasswordVisibility('new_password')">👁️ Show</button>
                                    </div>
                                    <small class="form-text">Use uppercase, lowercase, numbers, and symbols</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-chart-line"></i> Password Strength
                                    </label>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span id="strengthText" class="small text-muted">Awaiting input...</span>
                                        <span id="strengthScore" class="badge badge-secondary">0%</span>
                                    </div>
                                    <div id="strengthBar" style="height: 6px; background: rgba(255,255,255,0.1); border-radius: 4px; overflow: hidden;">
                                        <div style="height: 100%; width: 0%; background: linear-gradient(90deg, var(--brand-purple) 0%, var(--brand-purple-light) 100%); transition: width 0.3s ease;"></div>
                                    </div>
                                    <ul id="strengthAdvice" class="mt-2 small text-muted" style="list-style: none; padding: 0; margin: 0;"></ul>
                                </div>

                                <div class="mb-4">
                                    <label for="confirm_password" class="form-label">
                                        <i class="fas fa-check"></i> Confirm New Password
                                    </label>
                                    <div class="password-input-wrapper">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="12" placeholder="Re-enter your new password" required>
                                        <button type="button" class="password-toggle-btn" data-toggle-id="confirm_password" onclick="togglePasswordVisibility('confirm_password')">👁️ Show</button>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary w-100 mb-3">
                                    <i class="fas fa-save"></i> Save New Password
                                </button>
                            </form>

                            <div class="divider"></div>

                            <div class="text-center">
                                <a href="dashboard.php" class="text-muted">
                                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <p class="text-muted" style="font-size: 0.85rem;">
                            <i class="fas fa-info-circle"></i> Password changes are securely recorded<br>
                            <i class="fas fa-envelope"></i> A confirmation email will be sent to your account
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
