<?php
session_start();
require_once __DIR__ . '/utils.php';
$config = loadEnv();
initializeDatabaseSchema($config);

$userId = $_SESSION['email_verification_pending'] ?? $_SESSION['pending_verification_user_id'] ?? null;

if (empty($userId)) {
    header('Location: index.php');
    exit;
}

$user = getUserById($config, (int)$userId);
if (empty($user)) {
    header('Location: index.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$resent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        setFlash('danger', ['Invalid form submission. Please refresh and try again.']);
        header('Location: email-verification-pending.php');
        exit;
    }

    if (!empty($user['email'])) {
        try {
            $verificationToken = createEmailVerification($config, $user['id']);
            $mailResult = sendEmailVerificationEmail($user['email'], $user['username'], $verificationToken, $config);
            if ($mailResult) {
                $resent = true;
                setFlash('success', ['Verification email sent! Check your inbox.']);
            } else {
                setFlash('danger', ['Failed to send verification email. Check your email configuration in .env and visit /test-email.php to diagnose.']);
            }
        } catch (Exception $e) {
            setFlash('danger', ['An error occurred: ' . $e->getMessage()]);
        }
        header('Location: email-verification-pending.php');
        exit;
    }
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Verify Email - SecureAuth</title>
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
            <span style="color: var(--text-secondary);" class="ms-lg-3">Email Verification</span>
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
                            <i class="fas fa-envelope" style="color: var(--brand-purple);"></i>
                        </div>
                        <h1 class="h3 mb-2">Verify Your Email</h1>
                        <p class="subtitle">Complete your account setup</p>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <?php if (!empty($flash)): ?>
                                <div class="alert alert-<?= htmlspecialchars($flash['type'] ?? 'info') ?> alert-dismissible fade show" role="alert">
                                    <?php foreach ((array)$flash['messages'] as $message): ?>
                                        <div><i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'danger' ? 'exclamation-circle' : 'info-circle') ?>" style="margin-right: 0.5rem;"></i><?= htmlspecialchars($message) ?></div>
                                    <?php endforeach; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>

                            <?php if ((int)$user['email_verified'] === 1): ?>
                                <div class="alert alert-success mb-4">
                                    <i class="fas fa-check-circle"></i> Your email has already been verified. You can now <a href="login.php" class="alert-link">sign in</a>.
                                </div>
                            <?php else: ?>
                                <div class="info-card mb-4">
                                    <div class="info-card-title">
                                        <i class="fas fa-envelope"></i>
                                        Email Address
                                    </div>
                                    <div class="info-card-content">
                                        <?= htmlspecialchars($user['email'] ?? 'No email address on file') ?>
                                    </div>
                                </div>

                                <p class="text-muted mb-4">
                                    We've sent a verification email to <strong><?= htmlspecialchars($user['email'] ?? 'your email address') ?></strong>. 
                                    Click the link in the email to verify your account. The link expires in 24 hours.
                                </p>

                                <div class="alert alert-info mb-4">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Didn't receive the email?</strong>
                                    <ul style="list-style: none; padding: 0; margin-top: 0.5rem;">
                                        <li>Check your spam or junk folder</li>
                                        <li>Wait a few minutes for the email to arrive</li>
                                        <li>Request a new verification email below</li>
                                    </ul>
                                </div>

                                <form method="post" action="email-verification-pending.php" novalidate class="mb-3">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <button type="submit" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-redo"></i> Resend Verification Email
                                    </button>
                                </form>

                                <div class="divider"></div>

                                <div class="nav-links">
                                    <a href="login.php">
                                        <i class="fas fa-sign-in-alt"></i> Back to Sign In
                                    </a>
                                    <a href="index.php">
                                        <i class="fas fa-home"></i> Home
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <p class="text-muted" style="font-size: 0.85rem;">
                            <i class="fas fa-lock"></i> Email verification helps us keep your account secure<br>
                            <i class="fas fa-check"></i> You'll need to verify your email to access your account
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
