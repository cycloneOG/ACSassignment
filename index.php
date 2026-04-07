<?php
session_start();
require_once __DIR__ . '/utils.php';
$config = loadEnv();
$schemaStatus = '';
$schemaClass = 'info';
if (initializeDatabaseSchema($config)) {
    $schemaStatus = 'Database schema is ready. You may register or log in.';
    $schemaClass = 'success';
} else {
    $schemaStatus = 'Database schema initialization failed. Please ensure your configuration is correct and db.sql is present.';
    $schemaClass = 'danger';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Secure Registration System</title>
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
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="#features">Features</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#security">Security</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="login.php">Sign In</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main>
    <!-- Hero Section -->
    <div class="page-container">
        <div class="container-lg">
            <div class="row justify-content-center">
                <div class="col-lg-8 col-xl-7">
                    <div class="text-center mb-5">
                        <div style="font-size: 3.5rem; margin-bottom: 1.5rem;">
                            <i class="fas fa-lock" style="color: var(--brand-purple);"></i>
                        </div>
                        <h1 class="h2 mb-3">Enterprise-Grade Authentication</h1>
                        <p class="subtitle mb-4">Secure registration, two-factor authentication, and password management for modern applications.</p>
                        <div class="alert alert-<?= htmlspecialchars($schemaClass) ?>" style="margin-bottom: 2rem;">
                            <i class="fas fa-<?= $schemaClass === 'success' ? 'check-circle' : 'exclamation-circle' ?>" style="margin-right: 0.5rem;"></i>
                            <?= htmlspecialchars($schemaStatus) ?>
                        </div>
                        <div class="d-grid gap-3 d-sm-flex justify-content-sm-center">
                            <a href="register.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-user-plus"></i> Create Account
                            </a>
                            <a href="login.php" class="btn btn-outline-secondary btn-lg">
                                <i class="fas fa-sign-in-alt"></i> Sign In
                            </a>
                        </div>
                    </div>

                    <!-- Features Section -->
                    <div id="features" style="margin-top: 4rem;">
                        <h2 class="h4 text-center mb-4">Key Features</h2>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="info-card">
                                    <div class="info-card-title">
                                        <i class="fas fa-lock"></i> Secure Authentication
                                    </div>
                                    <div class="info-card-content">
                                        Hashed passwords and secure session management

                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-card">
                                    <div class="info-card-title">
                                        <i class="fas fa-mobile-alt"></i> Two-Factor Auth
                                    </div>
                                    <div class="info-card-content">
                                        TOTP-based 2FA with backup codes
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-card">
                                    <div class="info-card-title">
                                        <i class="fas fa-key"></i> Password Reset
                                    </div>
                                    <div class="info-card-content">
                                        Secure password recovery via email
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-card">
                                    <div class="info-card-title">
                                        <i class="fas fa-shield-alt"></i> CSRF Protection
                                    </div>
                                    <div class="info-card-content">
                                        Token-based CSRF defense on all forms
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Security Section -->
                    <div id="security" style="margin-top: 4rem;">
                        <h2 class="h4 text-center mb-4">Security Best Practices</h2>
                        <div class="card">
                            <div class="card-body">
                                <ul style="list-style: none; padding: 0;">
                                    <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--border);">
                                        <i class="fas fa-check" style="color: var(--success-color); margin-right: 0.75rem;"></i>
                                        Password hashing with bcrypt algorithm
                                    </li>
                                    <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--border);">
                                        <i class="fas fa-check" style="color: var(--success-color); margin-right: 0.75rem;"></i>
                                        Secure session management with HTTPOnly cookies
                                    </li>
                                    <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--border);">
                                        <i class="fas fa-check" style="color: var(--success-color); margin-right: 0.75rem;"></i>
                                        CSRF token protection on all state-changing requests
                                    </li>
                                    <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--border);">
                                        <i class="fas fa-check" style="color: var(--success-color); margin-right: 0.75rem;"></i>
                                        Email validation and verification
                                    </li>
                                    <li style="padding: 0.75rem 0;">
                                        <i class="fas fa-check" style="color: var(--success-color); margin-right: 0.75rem;"></i>
                                        Time-based One-Time Password (TOTP) support
                                    </li>
                                </ul>
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
        <div class="row">
            <div class="col-md-6 mb-3 mb-md-0">
                <p style="margin: 0;">
                    <strong>SecureAuth</strong><br>
                    Enterprise-class authentication system demo
                </p>
            </div>
            <div class="col-md-6 text-md-end">
                <p style="margin: 0;">
                    <small>© 2024 Secure Registration System. All rights reserved.</small><br>
                    <small><a href="#">Privacy Policy</a> | <a href="#">Security</a> | <a href="#">Terms</a></small>
                </p>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
