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
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$reset = null;
if ($token !== '') {
    $reset = findValidPasswordReset($config, $token);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        setFlash('danger', ['Invalid form submission.']);
        header('Location: reset.php?token=' . urlencode($token));
        exit;
    }
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    if ($token === '' || empty($reset)) {
        setFlash('danger', ['Invalid or expired password reset token.']);
        header('Location: forgot.php');
        exit;
    }
    if ($password === '' || $confirmPassword === '') {
        setFlash('danger', ['Please fill in both password fields.']);
        header('Location: reset.php?token=' . urlencode($token));
        exit;
    }
    if ($password !== $confirmPassword) {
        setFlash('danger', ['Passwords do not match.']);
        header('Location: reset.php?token=' . urlencode($token));
        exit;
    }
    $user = getUserById($config, $reset['user_id']);
    $strength = evaluatePasswordStrength($password, $user['username'] ?? '');
    if ($strength['score'] < 4) {
        setFlash('danger', ['Password is too weak: ' . $strength['label'] . '. Please follow the password advice.']);
        header('Location: reset.php?token=' . urlencode($token));
        exit;
    }
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    updateUserPassword($config, $reset['user_id'], $passwordHash);
    markPasswordResetUsed($config, $reset['reset_id']);
    if (!empty($user['email'])) {
        sendPasswordResetConfirmationEmail($user['email'], $user['username'], $config);
    }
    setFlash('success', ['Your password has been reset successfully. You may now sign in.']);
    header('Location: login.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Reset Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-xl-6 col-lg-7 col-md-9">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h3 mb-3 text-center">Reset Password</h1>
                    <?php if (!empty($flash)): ?>
                        <div class="alert alert-<?= htmlspecialchars($flash['type'] ?? 'info') ?> alert-dismissible fade show" role="alert">
                            <?php foreach ((array)$flash['messages'] as $message): ?>
                                <div><?= htmlspecialchars($message) ?></div>
                            <?php endforeach; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    <?php if (empty($reset)): ?>
                        <div class="alert alert-warning">This password reset link is invalid or has expired. <a href="forgot.php">Request another link</a>.</div>
                    <?php else: ?>
                        <form method="post" action="reset.php" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                            <div class="mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <div class="password-input-wrapper">
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <button type="button" class="password-toggle-btn" data-toggle-id="password" onclick="togglePasswordVisibility('password')">👁️ Show</button>
                                </div>
                                <div class="form-text">Use at least 12 characters with mixed letters, digits, and symbols.</div>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <div class="password-input-wrapper">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <button type="button" class="password-toggle-btn" data-toggle-id="confirm_password" onclick="togglePasswordVisibility('confirm_password')">👁️ Show</button>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Update password</button>
                        </form>
                    <?php endif; ?>
                    <div class="mt-3 d-flex justify-content-between">
                        <a href="login.php">Back to login</a>
                        <a href="index.php">Create account</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
