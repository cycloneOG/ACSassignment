<?php
require_once __DIR__ . '/utils.php';
$config = loadEnv();

// Diagnostic mode
$diagnostics = [];
$step = 1;

// Step 1: Check .env file
$diagnostics[$step++] = [
    'name' => '.env File Exists',
    'pass' => file_exists(__DIR__ . '/.env'),
    'details' => file_exists(__DIR__ . '/.env') ? 'Found at ' . __DIR__ . '/.env' : 'Not found',
    'fix' => !file_exists(__DIR__ . '/.env') ? 'Copy .env.example to .env and update with your settings' : ''
];

// Step 2: Check config keys
$diagnostics[$step++] = [
    'name' => 'MAIL_USERNAME Set',
    'pass' => !empty($config['MAIL_USERNAME']),
    'details' => !empty($config['MAIL_USERNAME']) ? 'Value: ' . substr($config['MAIL_USERNAME'], 0, 10) . '...' : 'Not set in .env',
    'fix' => empty($config['MAIL_USERNAME']) ? 'Add MAIL_USERNAME=your-email@gmail.com to .env' : ''
];

$diagnostics[$step++] = [
    'name' => 'MAIL_PASSWORD Set',
    'pass' => !empty($config['MAIL_PASSWORD']),
    'details' => !empty($config['MAIL_PASSWORD']) ? 'Length: ' . strlen($config['MAIL_PASSWORD']) . ' chars' : 'Not set in .env',
    'fix' => empty($config['MAIL_PASSWORD']) ? 'Add MAIL_PASSWORD=your-app-password to .env (use Gmail App Password, not regular password)' : ''
];

// Step 3: Check PHPMailer
$mailerPath = __DIR__ . '/vendor/autoload.php';
$diagnostics[$step++] = [
    'name' => 'PHPMailer Library',
    'pass' => file_exists($mailerPath),
    'details' => file_exists($mailerPath) ? 'Found' : 'Missing - run: composer install',
    'fix' => !file_exists($mailerPath) ? 'Open Command Prompt and run: cd c:\\xampp\\htdocs\\acs && composer install' : ''
];

// Step 4: Check PHP extensions
$diagnostics[$step++] = [
    'name' => 'OpenSSL Extension',
    'pass' => extension_loaded('openssl'),
    'details' => extension_loaded('openssl') ? 'Loaded' : 'Not loaded - needed for secure SMTP',
    'fix' => !extension_loaded('openssl') ? 'Uncomment extension=openssl in c:\\xampp\\php\\php.ini and restart Apache' : ''
];

$diagnostics[$step++] = [
    'name' => 'Sockets Extension',
    'pass' => extension_loaded('sockets'),
    'details' => extension_loaded('sockets') ? 'Loaded' : 'Not loaded - See: ENABLE_SOCKETS.md',
    'fix' => !extension_loaded('sockets') ? 'Uncomment extension=sockets in c:\\xampp\\php\\php.ini and restart Apache' : ''
];

// Step 5: Try to instantiate PHPMailer
$phpmailerTest = ['name' => 'PHPMailer Instantiation', 'pass' => false, 'details' => '', 'fix' => ''];
if (file_exists($mailerPath)) {
    try {
        require_once $mailerPath;
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $phpmailerTest['pass'] = true;
        $phpmailerTest['details'] = 'Successfully created PHPMailer instance';
    } catch (Exception $e) {
        $phpmailerTest['details'] = 'Failed: ' . $e->getMessage();
        $phpmailerTest['fix'] = 'Run: composer install to properly install PHPMailer';
    }
}
$diagnostics[$step++] = $phpmailerTest;

// Step 6: Try SMTP connection (only if all previous steps passed)
$smtpTest = ['name' => 'SMTP Connection Test', 'pass' => false, 'details' => 'Skipped', 'fix' => ''];
if ($diagnostics[4]['pass'] && $diagnostics[3]['pass']) {
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $config['MAIL_HOST'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = trim($config['MAIL_USERNAME'] ?? '');
        $mail->Password = trim($config['MAIL_PASSWORD'] ?? '');
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int)($config['MAIL_PORT'] ?? 587);
        $mail->Timeout = 5;
        
        // Try to connect
        $smtp = $mail->getSMTPInstance();
        if ($smtp->connect($mail->Host, $mail->Port, $mail->Timeout)) {
            $smtpTest['pass'] = true;
            $smtpTest['details'] = 'Connected to ' . $mail->Host . ':' . $mail->Port;
            $smtp->quit();
        } else {
            $smtpTest['details'] = 'Could not connect to ' . $mail->Host . ':' . $mail->Port;
            $smtpTest['fix'] = 'Port 587 is blocked? Try MAIL_PORT=465 with SMTP_SECURE=SMTPS in .env';
        }
    } catch (Exception $e) {
        $smtpTest['details'] = 'Connection failed: ' . $e->getMessage();
        $smtpTest['fix'] = 'Check MAIL_USERNAME and MAIL_PASSWORD in .env (use Gmail App Password, not regular password)';
    }
}
$diagnostics[$step++] = $smtpTest;

if (empty($_POST)) {
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>Email Configuration Diagnostic</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="assets/css/style.css">
    </head>
    <body class="bg-light">
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-body">
                            <h1 class="h3 mb-4">📧 Email Configuration Diagnostic</h1>
                            
                            <div class="mb-4">
                                <?php 
                                $allPass = array_reduce($diagnostics, function($carry, $item) {
                                    return $carry && $item['pass'];
                                }, true);
                                ?>
                                <div class="alert alert-<?= $allPass ? 'success' : 'warning' ?>">
                                    <strong><?= $allPass ? '✅ All checks passed!' : '⚠️ Some issues detected' ?></strong>
                                    <p class="mb-0 mt-2">Review the diagnostics below:</p>
                                </div>
                            </div>

                            <div class="diagnostics">
                                <?php foreach ($diagnostics as $i => $diag): ?>
                                    <div class="diagnostic-item mb-3 p-3 border rounded" style="background: <?= $diag['pass'] ? 'rgba(52,211,153,0.1)' : 'rgba(248,113,113,0.1)' ?>;">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1">
                                                    <?= $diag['pass'] ? '✓' : '✗' ?> 
                                                    <?= htmlspecialchars($diag['name']) ?>
                                                </h6>
                                                <small class="text-muted"><?= htmlspecialchars($diag['details']) ?></small>
                                                <?php if (!empty($diag['fix'])): ?>
                                                    <div class="alert alert-warning alert-sm mt-2 mb-0 py-2 px-2">
                                                        <strong>Fix:</strong> <?= htmlspecialchars($diag['fix']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <span class="badge bg-<?= $diag['pass'] ? 'success' : 'danger' ?>">
                                                <?= $diag['pass'] ? 'PASS' : 'FAIL' ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <hr class="my-4">

                            <form method="post" action="test-email.php" novalidate>
                                <div class="mb-3">
                                    <label for="email" class="form-label">📬 Send Test Email</label>
                                    <input type="email" class="form-control" id="email" name="email" placeholder="your@email.com" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100" <?= !$allPass ? 'disabled' : '' ?>>
                                    Send Test Email
                                </button>
                                <?php if (!$allPass): ?>
                                    <small class="text-danger d-block mt-2">Fix the issues above before sending a test email</small>
                                <?php endif; ?>
                            </form>

                            <hr class="my-4">

                            <h6>💡 Quick Fix Guide:</h6>
                            <ul class="small">
                                <li><strong>❌ Sockets Extension not loaded?</strong> 
                                    <a href="ENABLE_SOCKETS.md" target="_blank">See detailed fix guide</a>
                                    — Uncomment <code>extension=sockets</code> in <code>c:\xampp\php\php.ini</code> and restart Apache
                                </li>
                                <li>Missing <strong>.env</strong>? Copy `.env.example` to `.env`</li>
                                <li>Missing <strong>PHPMailer</strong>? Run: <code class="small">composer install</code></li>
                                <li>Wrong <strong>MAIL_PASSWORD</strong>? Use your Gmail App Password (not your regular password)</li>
                                <li>Still failing? Check: <code class="small">c:\xampp\apache\logs\error.log</code></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
} else {
    $email = $_POST['email'] ?? '';
    $result = false;
    $message = '';
    
    if (empty($email)) {
        $message = 'Please provide an email address.';
    } else {
        $result = sendMailMessage($email, 'Test Email from SecureAuth', "This is a test email from your SecureAuth installation.\n\nIf you received this, your mail configuration is working correctly!", $config);
        $message = $result ? 'Test email sent successfully!' : 'Failed to send test email. Check PHP error logs at: c:\\xampp\\apache\\logs\\error.log';
    }
    
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>Email Test Result</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="assets/css/style.css">
    </head>
    <body class="bg-light">
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-body">
                            <h1 class="h3 mb-4">Email Test Result</h1>
                            <div class="alert alert-<?= $result ? 'success' : 'danger' ?>">
                                <?= htmlspecialchars($message) ?>
                            </div>
                            <a href="test-email.php" class="btn btn-outline-primary">Run Diagnostic Again</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
}
?>
