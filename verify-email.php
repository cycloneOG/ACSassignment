<?php
session_start();
require_once __DIR__ . '/utils.php';
$config = loadEnv();
initializeDatabaseSchema($config);

$token = $_GET['token'] ?? '';
$message = '';
$messageType = '';
$success = false;

if ($token === '') {
    $message = 'No verification token provided.';
    $messageType = 'danger';
} else {
    $verification = findValidEmailVerification($config, $token);
    
    if ($verification === null) {
        $message = 'The verification link is invalid or has expired. Please request a new verification email.';
        $messageType = 'danger';
    } else {
        try {
            markEmailVerified($config, $verification['verification_id'], $verification['user_id']);
            $message = 'Your email has been verified successfully! You can now sign in.';
            $messageType = 'success';
            $success = true;
        } catch (Exception $e) {
            $message = 'An error occurred while verifying your email. Please try again.';
            $messageType = 'danger';
        }
    }
}

setFlash($messageType, [$message]);
header('Location: ' . ($success ? 'login.php' : 'index.php'));
exit;
?>
