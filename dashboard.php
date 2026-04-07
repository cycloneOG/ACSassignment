<?php
session_start();
require_once __DIR__ . '/utils.php';
$config = loadEnv();
requireLogin();
$user = getCurrentUser($config);
$unusedBackupCodes = 0;
if (!empty($user['2fa_enabled'])) {
    $unusedBackupCodes = countUnusedBackupCodes($config, $user['id']);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Dashboard - SecureAuth</title>
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
                    <a class="nav-link" href="change_password.php">
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
    <div class="container-lg py-5">
        <!-- Welcome Section -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div style="font-size: 2.5rem; color: var(--brand-purple);">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <div>
                                <h1 class="h4 mb-1">Welcome, <?= htmlspecialchars($user['username']) ?></h1>
                                <p class="text-muted mb-0">You are signed in to your SecureAuth account</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body text-center">
                        <div style="font-size: 1.5rem; color: var(--brand-purple); margin-bottom: 0.75rem;">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h5>Account Status</h5>
                        <div class="status-badge status-active">
                            <span class="status-dot active"></span>
                            Active
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Account Details -->
        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-user" style="margin-right: 0.5rem; color: var(--brand-purple);"></i>
                            Account Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted">Username</small>
                            <div class="h6 mb-0"><?= htmlspecialchars($user['username']) ?></div>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">Email Address</small>
                            <div class="h6 mb-0"><?= htmlspecialchars($user['email'] ?? 'Not set') ?></div>
                        </div>
                        <div>
                            <small class="text-muted">Account Created</small>
                            <div class="h6 mb-0"><?= htmlspecialchars($user['created_at']) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-lock" style="margin-right: 0.5rem; color: var(--brand-purple);"></i>
                            Security Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted">Last Password Change</small>
                            <div class="h6 mb-0"><?= htmlspecialchars($user['last_password_change']) ?></div>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">Two-Factor Authentication</small>
                            <div class="mb-0">
                                <?php if (!empty($user['2fa_enabled'])): ?>
                                    <span class="badge badge-success">
                                        <i class="fas fa-check-circle"></i> Enabled
                                    </span>
                                    <div class="small text-muted mt-2">Unused backup codes: <strong><?= htmlspecialchars((string)$unusedBackupCodes) ?></strong></div>
                                <?php else: ?>
                                    <span class="badge badge-danger">
                                        <i class="fas fa-times-circle"></i> Disabled
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <a href="change_password.php" class="card text-decoration-none" style="border: 1px solid rgba(124, 58, 237, 0.2); transition: all 0.3s; cursor: pointer;">
                    <div class="card-body text-center p-4">
                        <div style="font-size: 2rem; color: var(--brand-purple); margin-bottom: 1rem;">
                            <i class="fas fa-key"></i>
                        </div>
                        <h5 class="card-title mb-2">Change Password</h5>
                        <p class="card-text text-muted mb-0">Update your password to keep your account secure</p>
                    </div>
                </a>
            </div>
            <div class="col-md-6">
                <a href="two_factor_setup.php" class="card text-decoration-none" style="border: 1px solid rgba(124, 58, 237, 0.2); transition: all 0.3s; cursor: pointer;">
                    <div class="card-body text-center p-4">
                        <div style="font-size: 2rem; color: var(--brand-purple); margin-bottom: 1rem;">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h5 class="card-title mb-2">Manage Two-Factor Auth</h5>
                        <p class="card-text text-muted mb-0">
                            <?php if (!empty($user['2fa_enabled'])): ?>
                                Edit or disable 2FA settings
                            <?php else: ?>
                                Enable additional security with 2FA
                            <?php endif; ?>
                        </p>
                    </div>
                </a>
            </div>
        </div>

        <!-- Security Notice -->
        <div class="card" style="border: 1px solid rgba(52, 211, 153, 0.2); background: rgba(16, 185, 129, 0.05);">
            <div class="card-body">
                <div class="d-flex gap-3">
                    <div style="font-size: 1.5rem; color: var(--success-color);">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div>
                        <h5 class="card-title mb-2">Security Best Practices</h5>
                        <ul style="list-style: none; padding: 0; margin: 0;">
                            <li class="mb-2"><i class="fas fa-check" style="color: var(--success-color); margin-right: 0.5rem;"></i>Use a strong, unique password</li>
                            <li class="mb-2"><i class="fas fa-check" style="color: var(--success-color); margin-right: 0.5rem;"></i>Enable two-factor authentication for extra security</li>
                            <li class="mb-2"><i class="fas fa-check" style="color: var(--success-color); margin-right: 0.5rem;"></i>Never share your credentials with anyone</li>
                            <li><i class="fas fa-check" style="color: var(--success-color); margin-right: 0.5rem;"></i>Keep your recovery codes in a safe place</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Footer -->
<footer>
    <div class="container-lg">
        <div class="row">
            <div class="col-md-6 mb-3 mb-md-0">
                <small>
                    <strong>SecureAuth Dashboard</strong><br>
                    Enterprise-class authentication system
                </small>
            </div>
            <div class="col-md-6 text-md-end">
                <small>
                    <a href="#">Privacy Policy</a> | <a href="#">Security</a> | <a href="#">Terms</a>
                </small>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
