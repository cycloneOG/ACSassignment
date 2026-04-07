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
    $errors = [];
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = 'Invalid CSRF token. Please refresh the registration page and try again.';
    }
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $captchaToken = $_POST['cf-turnstile-response'] ?? '';
    if ($username === '') {
        $errors[] = 'Username is required.';
    } elseif (!validateUsername($username)) {
        $errors[] = 'Username must be 4-64 characters and may only contain letters, numbers, underscores, dots, and hyphens.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address or leave it blank.';
    }
    if ($password === '') {
        $errors[] = 'Password is required.';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Password and confirmation do not match.';
    }
    $strength = evaluatePasswordStrength($password, $username);
    if ($strength['score'] < 4) {
        $errors[] = 'Password is too weak. ' . $strength['label'] . '. ' . implode(' ', $strength['advice']);
    }
    if ($captchaToken === '') {
        $errors[] = 'CAPTCHA verification is required.';
    } elseif (!verifyTurnstile($captchaToken, $config)) {
        $errors[] = 'CAPTCHA verification failed. Please complete the challenge again.';
    }
    if (count($errors) === 0) {
        try {
            $pdo = dbConnect($config);
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
            $stmt->execute(['username' => $username]);
            if ($stmt->fetch()) {
                $errors[] = 'This username is already taken. Choose a different username.';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $insert = $pdo->prepare('INSERT INTO users (username, email, password_hash, last_password_change, created_at, is_active, email_verified) VALUES (:username, :email, :password_hash, NOW(), NOW(), 1, 0)');
                $insert->execute([
                    'username' => $username,
                    'email' => $email ?: null,
                    'password_hash' => $passwordHash,
                ]);
                $userId = $pdo->lastInsertId();
                $history = $pdo->prepare('INSERT INTO password_history (user_id, password_hash, changed_at) VALUES (:user_id, :password_hash, NOW())');
                $history->execute(['user_id' => $userId, 'password_hash' => $passwordHash]);
                if ($email !== '') {
                    $verificationToken = createEmailVerification($config, $userId);
                    sendEmailVerificationEmail($email, $username, $verificationToken, $config);
                    $_SESSION['email_verification_pending'] = $userId;
                    setFlash('success', ['Account created! Check your email to verify your address.']);
                    header('Location: email-verification-pending.php');
                } else {
                    $_SESSION['email_verification_pending'] = $userId;
                    setFlash('info', ['Account created! Email verification is optional.']);
                    header('Location: email-verification-pending.php');
                }
                exit;
            }
        } catch (Exception $e) {
            $errors[] = 'An unexpected error occurred during registration. Please try again later.';
        }
    }
    setFlash('danger', $errors);
    header('Location: register.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Create Account - SecureAuth</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
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
            <span style="color: var(--text-secondary);" class="ms-lg-3">Create Account</span>
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
                            <i class="fas fa-user-plus" style="color: var(--brand-purple);"></i>
                        </div>
                        <h1 class="h3 mb-2">Create Your Account</h1>
                        <p class="subtitle">Secure registration with password strength validation</p>
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

                            <form id="registerForm" method="post" action="register.php" novalidate>
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

                                <div class="mb-3">
                                    <label for="username" class="form-label">
                                        <i class="fas fa-user"></i> Username
                                    </label>
                                    <input type="text" class="form-control" id="username" name="username" minlength="4" maxlength="64" placeholder="Choose a unique username" required>
                                    <small class="form-text">4-64 characters: letters, numbers, underscore, dot, hyphen</small>
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label">
                                        <i class="fas fa-envelope"></i> Email Address
                                    </label>
                                    <input type="email" class="form-control" id="email" name="email" maxlength="255" placeholder="your@email.com (optional)">
                                    <small class="form-text">Optional - helps with account recovery</small>
                                </div>

                                <div class="divider"></div>

                                <div class="mb-3">
                                    <label for="password" class="form-label">
                                        <i class="fas fa-lock"></i> Password
                                    </label>
                                    <div class="password-input-wrapper">
                                        <input type="password" class="form-control" id="password" name="password" minlength="12" placeholder="Minimum 12 characters" required>
                                        <button type="button" class="password-toggle-btn" data-toggle-id="password" onclick="togglePasswordVisibility('password')">👁️ Show</button>
                                    </div>
                                    <small class="form-text">Use uppercase, lowercase, numbers, and symbols for better security</small>
                                </div>

                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">
                                        <i class="fas fa-check"></i> Confirm Password
                                    </label>
                                    <div class="password-input-wrapper">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="12" placeholder="Re-enter your password" required>
                                        <button type="button" class="password-toggle-btn" data-toggle-id="confirm_password" onclick="togglePasswordVisibility('confirm_password')">👁️ Show</button>
                                    </div>
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

                                <div class="mb-4 p-3 bg-gradient rounded">
                                    <label class="form-label mb-2">
                                        <i class="fas fa-robot"></i> Verify You're Human
                                    </label>
                                    <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars($config['TURNSTILE_SITE_KEY'] ?? '0x4AAAAAAC1pPHCXWXGIeOyD') ?>"></div>
                                </div>

                                <button type="submit" class="btn btn-primary w-100 mb-3">
                                    <i class="fas fa-user-plus"></i> Create Account
                                </button>
                            </form>

                            <div class="divider"></div>

                            <div class="text-center">
                                <p class="text-muted mb-0">
                                    Already have an account? <a href="login.php"><strong>Sign in here</strong></a>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <p class="text-muted" style="font-size: 0.85rem;">
                            <i class="fas fa-shield-alt"></i> Your password is encrypted and never stored in plain text<br>
                            <i class="fas fa-lock"></i> Protected by CSRF tokens and CAPTCHA verification
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
