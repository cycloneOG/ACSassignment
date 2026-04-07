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
$pendingSecret = $_SESSION['pending_2fa_secret'] ?? null;
$showQr = false;
$qrUri = '';
$errors = [];
$success = null;
$backupCodes = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = 'Invalid form submission. Please refresh and try again.';
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'start_enable') {
        $pendingSecret = generateTotpSecret();
        $_SESSION['pending_2fa_secret'] = $pendingSecret;
        $showQr = true;
    } elseif ($action === 'confirm_enable') {
        $pendingSecret = $_SESSION['pending_2fa_secret'] ?? null;
        $code = trim($_POST['two_factor_code'] ?? '');
        if (empty($pendingSecret)) {
            $errors[] = 'The 2FA secret is missing. Please start setup again.';
        } elseif ($code === '') {
            $errors[] = 'Please enter the code from your authenticator app.';
        } elseif (!verifyTotpCode($pendingSecret, $code)) {
            $errors[] = 'The authentication code is incorrect.';
        } else {
            updateUserTwoFactor($config, $user['id'], true, $pendingSecret);
            unset($_SESSION['pending_2fa_secret']);
            $backupCodes = generateBackupCodes($config, $user['id']);
            $success = 'Two-factor authentication has been enabled successfully. Store your backup codes in a safe place.';
            $user = getCurrentUser($config);
        }
    } elseif ($action === 'regen_backup_codes') {
        $code = trim($_POST['two_factor_code'] ?? '');
        if ($code === '') {
            $errors[] = 'Please enter the current authentication code to regenerate backup codes.';
        } elseif (empty($user['2fa_secret']) || !verifyTotpCode($user['2fa_secret'], $code)) {
            $errors[] = 'The authentication code is incorrect.';
        } else {
            $backupCodes = generateBackupCodes($config, $user['id']);
            $success = 'Your backup recovery codes have been regenerated. Store the new codes securely.';
        }
    } elseif ($action === 'disable') {
        $code = trim($_POST['two_factor_code'] ?? '');
        if ($code === '') {
            $errors[] = 'Please enter the current authentication code to disable 2FA.';
        } elseif (empty($user['2fa_secret']) || !verifyTotpCode($user['2fa_secret'], $code)) {
            $errors[] = 'The authentication code is incorrect.';
        } else {
            updateUserTwoFactor($config, $user['id'], false, null);
            $success = 'Two-factor authentication has been disabled.';
            $user = getCurrentUser($config);
        }
    }
}
if ($pendingSecret !== null) {
    $showQr = true;
    $qrUri = getTotpUri($pendingSecret, $user['username']);
}
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
    <title>Two-Factor Authentication - SecureAuth</title>
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
                    <a class="nav-link active" href="two_factor_setup.php">
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
                            <i class="fas fa-mobile-alt" style="color: var(--brand-purple);"></i>
                        </div>
                        <h1 class="h3 mb-2">Two-Factor Authentication</h1>
                        <p class="subtitle">Add an extra layer of security to your account</p>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <?php if (!empty($flash)): ?>
                                <div class="alert alert-<?= htmlspecialchars($flash['type'] ?? 'info') ?> alert-dismissible fade show" role="alert">
                                    <?php foreach ((array)$flash['messages'] as $message): ?>
                                        <div><i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'info-circle' ?>" style="margin-right: 0.5rem;"></i><?= htmlspecialchars($message) ?></div>
                                    <?php endforeach; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?php foreach ($errors as $error): ?>
                                        <div><i class="fas fa-exclamation-circle" style="margin-right: 0.5rem;"></i><?= htmlspecialchars($error) ?></div>
                                    <?php endforeach; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($success)): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="fas fa-check-circle" style="margin-right: 0.5rem;"></i><?= htmlspecialchars($success) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>

                            <!-- Status Card -->
                            <div class="info-card mb-4">
                                <div class="info-card-title">
                                    <i class="fas fa-<?= !empty($user['2fa_enabled']) ? 'check-circle' : 'times-circle' ?>"></i>
                                    Current 2FA Status
                                </div>
                                <div class="info-card-content">
                                    <?php if (!empty($user['2fa_enabled'])): ?>
                                        <strong>Status:</strong> <span class="badge badge-success"><i class="fas fa-check"></i> Enabled</span><br>
                                        <strong>Backup Codes:</strong> <?= htmlspecialchars((string)$unusedBackupCodes) ?> unused
                                    <?php else: ?>
                                        <strong>Status:</strong> <span class="badge badge-danger"><i class="fas fa-times"></i> Disabled</span><br>
                                        <small>Protect your account by enabling two-factor authentication</small>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Enable 2FA Setup -->
                            <?php if (!$user['2fa_enabled']): ?>
                                <?php if ($showQr && $pendingSecret): ?>
                                    <!-- QR Code Display -->
                                    <div class="mb-4">
                                        <h5 class="mb-3">Step 1: Scan QR Code</h5>
                                        <div class="d-flex justify-content-center mb-3 p-3" style="background: rgba(255,255,255,0.05); border-radius: 8px;">
                                            <img src="<?= htmlspecialchars(getQrCodeImageUrl($qrUri, 200)) ?>" alt="2FA QR code" style="background: white; padding: 10px; border-radius: 4px;">
                                        </div>
                                        <p class="text-muted small"><i class="fas fa-info-circle"></i> Scan this code with your authenticator app (Google Authenticator, Authy, Microsoft Authenticator, etc.)</p>

                                        <div class="divider"></div>

                                        <p class="small mb-2"><strong>Or enter this code manually:</strong></p>
                                        <div class="input-group mb-3">
                                            <input type="text" class="form-control font-monospace" value="<?= htmlspecialchars($pendingSecret) ?>" readonly style="background: rgba(255,255,255,0.05);">
                                            <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($pendingSecret) ?>')">
                                                <i class="fas fa-copy"></i> Copy
                                            </button>
                                        </div>

                                        <div class="divider"></div>

                                        <h5 class="mb-3">Step 2: Enter Code</h5>
                                        <form method="post" action="two_factor_setup.php" novalidate>
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="action" value="confirm_enable">
                                            <div class="mb-3">
                                                <label for="two_factor_code" class="form-label">
                                                    <i class="fas fa-mobile-alt"></i> Enter 6-Digit Code
                                                </label>
                                                <input type="text" class="form-control form-control-lg text-center font-monospace" id="two_factor_code" name="two_factor_code" maxlength="6" pattern="\d{6}" placeholder="000000" style="letter-spacing: 0.5rem;" required>
                                                <small class="form-text">Enter the code shown in your authenticator app</small>
                                            </div>
                                            <button type="submit" class="btn btn-primary w-100 mb-2">
                                                <i class="fas fa-check-circle"></i> Enable Two-Factor
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <form method="post" action="two_factor_setup.php" novalidate class="mb-4">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="action" value="start_enable">
                                        <p class="text-muted mb-3">Two-factor authentication adds an extra layer of security by requiring a code from your phone in addition to your password.</p>
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="fas fa-shield-alt"></i> Enable Two-Factor Authentication
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <!-- Disable & Regenerate Options -->
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <form method="post" action="two_factor_setup.php" novalidate>
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="action" value="regen_backup_codes">
                                            <div class="mb-3">
                                                <label for="two_factor_code_regen" class="form-label">
                                                    <i class="fas fa-key"></i> Regenerate Codes
                                                </label>
                                                <input type="text" class="form-control form-control-sm text-center font-monospace" id="two_factor_code_regen" name="two_factor_code" maxlength="6" pattern="\d{6}" placeholder="000000" style="letter-spacing: 0.3rem;" required>
                                                <small class="form-text">Current auth code</small>
                                            </div>
                                            <button type="submit" class="btn btn-outline-warning btn-sm w-100">
                                                <i class="fas fa-sync"></i> Regenerate Backup Codes
                                            </button>
                                        </form>
                                    </div>
                                    <div class="col-md-6">
                                        <form method="post" action="two_factor_setup.php" novalidate>
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="action" value="disable">
                                            <div class="mb-3">
                                                <label for="two_factor_code_disable" class="form-label">
                                                    <i class="fas fa-times-circle"></i> Disable
                                                </label>
                                                <input type="text" class="form-control form-control-sm text-center font-monospace" id="two_factor_code_disable" name="two_factor_code" maxlength="6" pattern="\d{6}" placeholder="000000" style="letter-spacing: 0.3rem;" required>
                                                <small class="form-text">Current auth code</small>
                                            </div>
                                            <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                                <i class="fas fa-times-circle"></i> Disable 2FA
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Show Backup Codes if Generated -->
                            <?php if (!empty($backupCodes)): ?>
                                <div class="alert" style="background: rgba(245, 158, 11, 0.15); border-color: rgba(245, 158, 11, 0.3);">
                                    <h6 style="color: var(--warning-color);">
                                        <i class="fas fa-exclamation-triangle"></i> Important: Save Your Backup Codes
                                    </h6>
                                    <p class="mb-2"><small>Store these codes in a secure location. Use each code only once if you lose access to your authenticator app.</small></p>
                                    <div class="bg-dark rounded p-3 text-center font-monospace" style="background: rgba(0,0,0,0.3) !important; overflow-x: auto;">
                                        <?php foreach ($backupCodes as $i => $backupCode): ?>
                                            <div class="mb-2" style="letter-spacing: 0.2rem;">
                                                <strong><?= htmlspecialchars($backupCode) ?></strong>
                                                <?php if (($i + 1) % 2 === 0 && $i + 1 < count($backupCodes)): ?>
                                                    <br>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button class="btn btn-sm btn-outline-warning mt-3 w-100" onclick="window.print()">
                                        <i class="fas fa-print"></i> Print Codes
                                    </button>
                                </div>
                            <?php endif; ?>

                            <div class="divider"></div>

                            <div class="text-center">
                                <a href="dashboard.php" class="text-muted">
                                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                                </a>
                            </div>
                        </div>
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
