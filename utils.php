<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function loadEnv(string $path = __DIR__ . '/.env'): array
{
    $result = [];
    if (!file_exists($path)) {
        return $result;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key = trim($key);
        $value = trim($value);
        if ($key === '') {
            continue;
        }
        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
            $value = substr($value, 1, -1);
        }
        if (str_starts_with($value, "'") && str_ends_with($value, "'")) {
            $value = substr($value, 1, -1);
        }
        $result[$key] = $value;
        if (!isset($_ENV[$key])) {
            $_ENV[$key] = $value;
        }
    }
    return $result;
}

function dbConnect(array $config): PDO
{
    $host = $config['DB_HOST'] ?? '127.0.0.1';
    $db = $config['DB_NAME'] ?? 'acs_prototype';
    $user = $config['DB_USER'] ?? 'root';
    $pass = $config['DB_PASS'] ?? '';
    $charset = 'utf8mb4';
    $dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    return new PDO($dsn, $user, $pass, $options);
}

function initializeDatabaseSchema(array $config): bool
{
    $schemaFile = __DIR__ . '/db.sql';
    if (!file_exists($schemaFile)) {
        return false;
    }
    $host = $config['DB_HOST'] ?? '127.0.0.1';
    $user = $config['DB_USER'] ?? 'root';
    $pass = $config['DB_PASS'] ?? '';
    $charset = 'utf8mb4';
    $dsn = "mysql:host={$host};charset={$charset}";
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $sql = file_get_contents($schemaFile);
        $sql = preg_replace('/--.*(?:\r\n|\r|\n)/', "\n", $sql);
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $statement) {
            if ($statement === '') {
                continue;
            }
            $pdo->exec($statement);
        }
        migrateDatabaseSchema($config);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function migrateDatabaseSchema(array $config): bool
{
    try {
        $pdo = dbConnect($config);
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS `email_verified` TINYINT(1) NOT NULL DEFAULT 0");
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS `email_verified_at` DATETIME DEFAULT NULL");
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS `2fa_enabled` TINYINT(1) NOT NULL DEFAULT 0");
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS `2fa_secret` VARCHAR(255) DEFAULT NULL");
        $pdo->exec("CREATE TABLE IF NOT EXISTS `email_verifications` (
            `verification_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `token_hash` VARCHAR(255) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `verified_at` DATETIME DEFAULT NULL,
            `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`verification_id`),
            INDEX (`user_id`),
            INDEX (`expires_at`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS `two_factor_backup_codes` (
            `code_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `code_hash` VARCHAR(255) NOT NULL,
            `is_used` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `used_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`code_id`),
            INDEX (`user_id`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function validateUsername(string $username): bool
{
    return preg_match('/^[a-zA-Z0-9_.-]{4,64}$/', $username) === 1;
}

function generateTotpSecret(int $length = 16): string
{
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < $length; $i++) {
        $secret .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $secret;
}

function getTotpUri(string $secret, string $username, string $issuer = 'Secure Prototype'): string
{
    $label = rawurlencode($issuer . ':' . $username);
    return sprintf(
        'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
        $label,
        rawurlencode($secret),
        rawurlencode($issuer)
    );
}

function getQrCodeImageUrl(string $data, int $size = 200): string
{
    return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&data=' . rawurlencode($data);
}

function base32Decode(string $secret): string
{
    $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $paddingCharCount = substr_count($secret, '=');
    $allowedValues = [6, 4, 3, 1, 0];
    if (!in_array($paddingCharCount, $allowedValues, true)) {
        return '';
    }
    $secret = str_replace('=', '', $secret);
    $secret = strtoupper($secret);
    $binaryString = '';
    foreach (str_split($secret) as $character) {
        $currentChar = strpos($base32chars, $character);
        if ($currentChar === false) {
            return '';
        }
        $binaryString .= str_pad(decbin($currentChar), 5, '0', STR_PAD_LEFT);
    }
    $decoded = '';
    foreach (str_split($binaryString, 8) as $byte) {
        if (strlen($byte) === 8) {
            $decoded .= chr(bindec($byte));
        }
    }
    return $decoded;
}

function getTotpCode(string $secret, int $timeSlice = null): string
{
    if ($timeSlice === null) {
        $timeSlice = floor(time() / 30);
    }
    $secretKey = base32Decode($secret);
    if ($secretKey === '') {
        return '';
    }
    $time = pack('N*', 0) . pack('N*', $timeSlice);
    $hash = hash_hmac('sha1', $time, $secretKey, true);
    $offset = ord($hash[19]) & 0x0F;
    $code = (ord($hash[$offset]) & 0x7F) << 24 |
            (ord($hash[$offset + 1]) & 0xFF) << 16 |
            (ord($hash[$offset + 2]) & 0xFF) << 8 |
            (ord($hash[$offset + 3]) & 0xFF);
    return str_pad($code % 1000000, 6, '0', STR_PAD_LEFT);
}

function verifyTotpCode(string $secret, string $code, int $window = 1): bool
{
    $code = trim($code);
    if (!preg_match('/^[0-9]{6}$/', $code)) {
        return false;
    }
    for ($offset = -$window; $offset <= $window; $offset++) {
        if (hash_equals(getTotpCode($secret, floor(time() / 30) + $offset), $code)) {
            return true;
        }
    }
    return false;
}

function evaluatePasswordStrength(string $password, string $username = ''): array
{
    $score = 0;
    $advice = [];
    $length = strlen($password);
    if ($length >= 12) {
        $score += 2;
    } elseif ($length >= 10) {
        $score += 1;
        $advice[] = 'Use at least 12 characters for stronger protection.';
    } else {
        $advice[] = 'Password length should be at least 12 characters.';
    }
    if (preg_match('/[A-Z]/', $password)) {
        $score++;
    } else {
        $advice[] = 'Add at least one uppercase letter.';
    }
    if (preg_match('/[a-z]/', $password)) {
        $score++;
    } else {
        $advice[] = 'Add at least one lowercase letter.';
    }
    if (preg_match('/[0-9]/', $password)) {
        $score++;
    } else {
        $advice[] = 'Add at least one digit.';
    }
    if (preg_match('/[!@#$%^&*()_+\-=[\]{};:\"\\|,.<>\/?]/', $password)) {
        $score++;
    } else {
        $advice[] = 'Add at least one special character like ! @ # $ or %.';
    }
    if (preg_match('/\s/', $password)) {
        $advice[] = 'Remove spaces from the password.';
    }
    $common = ['password', '123456', 'qwerty', '111111', 'letmein', 'admin'];
    foreach ($common as $pattern) {
        if (stripos($password, $pattern) !== false) {
            $advice[] = 'Avoid common words and predictable patterns.';
            break;
        }
    }
    if ($username !== '' && stripos($password, $username) !== false) {
        $advice[] = 'Do not include your username in the password.';
    }
    if (count(array_unique(str_split($password))) >= 6) {
        $score++;
    }
    $label = match (true) {
        $score <= 1 => 'Very weak',
        $score === 2 => 'Weak',
        $score === 3 => 'Moderate',
        $score === 4 => 'Strong',
        default => 'Very strong',
    };
    if (empty($advice)) {
        $advice[] = 'This password meets modern strength requirements.';
    }
    return [
        'score' => min($score, 6),
        'label' => $label,
        'advice' => $advice,
    ];
}

function verifyTurnstile(string $token, array $config): bool
{
    $secret = $config['TURNSTILE_SECRET_KEY'] ?? '';
    if ($secret === '') {
        return false;
    }
    $payload = http_build_query([
        'secret' => $secret,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    if (function_exists('curl_version')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        $response = curl_exec($ch);
        curl_close($ch);
    } else {
        $response = file_get_contents($url . '?' . $payload);
    }
    if ($response === false) {
        return false;
    }
    $json = json_decode($response, true);
    return !empty($json['success']);
}

function setFlash(string $type, array $messages): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'messages' => $messages,
    ];
}

function loginUser(array $user): void
{
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
}

function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function requireLogin(): void
{
    if (empty($_SESSION['user_id'])) {
        setFlash('warning', ['Please sign in to continue.']);
        header('Location: login.php');
        exit;
    }
}

function getUserById(array $config, int $id): ?array
{
    $pdo = dbConnect($config);
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function getUserByUsername(array $config, string $username): ?array
{
    $pdo = dbConnect($config);
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function getUserByEmail(array $config, string $email): ?array
{
    $pdo = dbConnect($config);
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function getCurrentUser(array $config): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return getUserById($config, (int)$_SESSION['user_id']);
}

function getAppBaseUrl(): string
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
    return rtrim($scheme . '://' . $host . $scriptPath, '/');
}

function sendMailMessage(string $recipientEmail, string $subject, string $body, array $config): bool
{
    $mailerPath = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($mailerPath)) {
        error_log('sendMailMessage: PHPMailer not found at ' . $mailerPath);
        return false;
    }
    
    // Validate email config
    if (empty($config['MAIL_USERNAME']) || empty($config['MAIL_PASSWORD'])) {
        error_log('sendMailMessage: MAIL_USERNAME or MAIL_PASSWORD not configured in .env');
        return false;
    }
    
    require_once $mailerPath;
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $config['MAIL_HOST'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = trim($config['MAIL_USERNAME']);
        $mail->Password = trim($config['MAIL_PASSWORD']);
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int)($config['MAIL_PORT'] ?? 587);
        $mail->SMTPDebug = 0;
        $mail->Debugoutput = 'error_log';
        $mail->Timeout = 10;
        $mail->setFrom($config['MAIL_FROM'] ?? $mail->Username, $config['MAIL_FROM_NAME'] ?? 'Secure App');
        $mail->addAddress($recipientEmail);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->isHTML(false);
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('sendMailMessage error: ' . $e->getMessage() . ' (Code: ' . $e->getCode() . ')');
        return false;
    }
}

function sendNotificationEmail(string $recipientEmail, string $username, array $config): bool
{
    $subject = 'Account Created Successfully';
    $body = "Hello {$username},\n\nYour account has been created successfully on the secure registration prototype. If you did not initiate this request, please contact support immediately.\n\nRegards,\nSecurity Team";
    return sendMailMessage($recipientEmail, $subject, $body, $config);
}

function sendPasswordResetEmail(string $recipientEmail, string $username, string $token, array $config): bool
{
    $resetUrl = getAppBaseUrl() . '/reset.php?token=' . urlencode($token);
    $subject = 'Password Reset Request';
    $body = "Hello {$username},\n\nWe received a request to reset your password. Use the link below to update your password. The link expires in one hour.\n\n{$resetUrl}\n\nIf you did not request a password reset, please ignore this email.\n\nRegards,\nSecurity Team";
    return sendMailMessage($recipientEmail, $subject, $body, $config);
}

function sendPasswordResetConfirmationEmail(string $recipientEmail, string $username, array $config): bool
{
    $subject = 'Your password has been changed';
    $body = "Hello {$username},\n\nYour password has been reset successfully. If you did not perform this action, contact support immediately.\n\nRegards,\nSecurity Team";
    return sendMailMessage($recipientEmail, $subject, $body, $config);
}

function sendEmailVerificationEmail(string $recipientEmail, string $username, string $token, array $config): bool
{
    $verifyUrl = getAppBaseUrl() . '/verify-email.php?token=' . urlencode($token);
    $subject = 'Verify Your Email Address';
    $body = "Hello {$username},\n\nThank you for creating an account! To complete your registration, please verify your email address by clicking the link below. The link expires in 24 hours.\n\n{$verifyUrl}\n\nIf you did not create this account, please ignore this email.\n\nRegards,\nSecurity Team";
    return sendMailMessage($recipientEmail, $subject, $body, $config);
}

function createEmailVerification(array $config, int $userId): string
{
    $pdo = dbConnect($config);
    $pdo->prepare('DELETE FROM email_verifications WHERE user_id = :user_id AND (is_verified = 1 OR expires_at < NOW())')->execute(['user_id' => $userId]);
    $token = bin2hex(random_bytes(32));
    $tokenHash = password_hash($token, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO email_verifications (user_id, token_hash, expires_at) VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 24 HOUR))');
    $stmt->execute(['user_id' => $userId, 'token_hash' => $tokenHash]);
    return $token;
}

function findValidEmailVerification(array $config, string $token): ?array
{
    $pdo = dbConnect($config);
    $stmt = $pdo->query('SELECT * FROM email_verifications WHERE is_verified = 0 AND expires_at >= NOW() ORDER BY created_at DESC');
    while ($row = $stmt->fetch()) {
        if (password_verify($token, $row['token_hash'])) {
            return $row;
        }
    }
    return null;
}

function markEmailVerified(array $config, int $verificationId, int $userId): void
{
    $pdo = dbConnect($config);
    $pdo->prepare('UPDATE email_verifications SET is_verified = 1, verified_at = NOW() WHERE verification_id = :verification_id')->execute(['verification_id' => $verificationId]);
    $pdo->prepare('UPDATE users SET email_verified = 1, email_verified_at = NOW() WHERE id = :user_id')->execute(['user_id' => $userId]);
}

function createPasswordResetRequest(array $config, int $userId): string
{
    $pdo = dbConnect($config);
    $pdo->prepare('DELETE FROM password_resets WHERE user_id = :user_id AND (is_used = 1 OR expires_at < NOW())')->execute(['user_id' => $userId]);
    $token = bin2hex(random_bytes(32));
    $tokenHash = password_hash($token, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 60 MINUTE))');
    $stmt->execute(['user_id' => $userId, 'token_hash' => $tokenHash]);
    return $token;
}

function findValidPasswordReset(array $config, string $token): ?array
{
    $pdo = dbConnect($config);
    $stmt = $pdo->query('SELECT * FROM password_resets WHERE is_used = 0 AND expires_at >= NOW() ORDER BY created_at DESC');
    while ($row = $stmt->fetch()) {
        if (password_verify($token, $row['token_hash'])) {
            return $row;
        }
    }
    return null;
}

function markPasswordResetUsed(array $config, int $resetId): void
{
    $pdo = dbConnect($config);
    $stmt = $pdo->prepare('UPDATE password_resets SET is_used = 1, used_at = NOW() WHERE reset_id = :reset_id');
    $stmt->execute(['reset_id' => $resetId]);
}

function updateUserPassword(array $config, int $userId, string $passwordHash): void
{
    $pdo = dbConnect($config);
    $stmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash, last_password_change = NOW() WHERE id = :id');
    $stmt->execute(['password_hash' => $passwordHash, 'id' => $userId]);
    $history = $pdo->prepare('INSERT INTO password_history (user_id, password_hash, changed_at) VALUES (:user_id, :password_hash, NOW())');
    $history->execute(['user_id' => $userId, 'password_hash' => $passwordHash]);
}

function updateUserTwoFactor(array $config, int $userId, bool $enabled, ?string $secret): void
{
    $pdo = dbConnect($config);
    $stmt = $pdo->prepare('UPDATE users SET `2fa_enabled` = :enabled, `2fa_secret` = :secret WHERE id = :id');
    $stmt->execute([
        'enabled' => $enabled ? 1 : 0,
        'secret' => $secret,
        'id' => $userId,
    ]);
}

function generateBackupCode(int $length = 8): string
{
    $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return substr(chunk_split($code, 4, '-'), 0, $length + floor($length / 4));
}

function generateBackupCodes(array $config, int $userId, int $count = 10): array
{
    $pdo = dbConnect($config);
    $pdo->prepare('DELETE FROM two_factor_backup_codes WHERE user_id = :user_id')->execute(['user_id' => $userId]);
    $codes = [];
    $stmt = $pdo->prepare('INSERT INTO two_factor_backup_codes (user_id, code_hash) VALUES (:user_id, :code_hash)');
    for ($i = 0; $i < $count; $i++) {
        $plainCode = generateBackupCode();
        $stmt->execute(['user_id' => $userId, 'code_hash' => password_hash($plainCode, PASSWORD_DEFAULT)]);
        $codes[] = $plainCode;
    }
    return $codes;
}

function verifyBackupCode(array $config, int $userId, string $code): bool
{
    $pdo = dbConnect($config);
    $stmt = $pdo->prepare('SELECT code_id, code_hash FROM two_factor_backup_codes WHERE user_id = :user_id AND is_used = 0');
    $stmt->execute(['user_id' => $userId]);
    while ($row = $stmt->fetch()) {
        if (password_verify($code, $row['code_hash'])) {
            $update = $pdo->prepare('UPDATE two_factor_backup_codes SET is_used = 1, used_at = NOW() WHERE code_id = :code_id');
            $update->execute(['code_id' => $row['code_id']]);
            return true;
        }
    }
    return false;
}

function countUnusedBackupCodes(array $config, int $userId): int
{
    $pdo = dbConnect($config);
    $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM two_factor_backup_codes WHERE user_id = :user_id AND is_used = 0');
    $stmt->execute(['user_id' => $userId]);
    $row = $stmt->fetch();
    return (int)($row['total'] ?? 0);
}
