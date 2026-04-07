# Secure Registration Prototype

This prototype demonstrates secure registration design with HTML, CSS, JavaScript, PHP, Bootstrap, MySQL, Cloudflare Turnstile CAPTCHA, and PHPMailer.

## Features
- Secure user registration form with username, password, and optional email.
- Client-side and server-side password strength evaluation.
- Cloudflare Turnstile CAPTCHA validation.
- CSRF token protection.
- Password hashing with `password_hash()`.
- Duplicate username prevention.
- Password history tracking.
- Notification email via PHPMailer and Gmail SMTP.
- Configuration via `.env` file.
- Login, protected dashboard, password reset request, and token-based password recovery.
- Two-factor authentication (2FA) support via authenticator apps.
- Landing page first with registration and login options.

## Setup
1. Copy this folder into your web root, for example `c:\xampp\htdocs\acs`.
2. Create the database using `db.sql` in MySQL:
   - Import `db.sql` via phpMyAdmin or `mysql -u root -p < db.sql`.
3. Install dependencies with Composer:
   - `cd c:\xampp\htdocs\acs`
   - `composer install`
   - If Composer is not installed, download it from https://getcomposer.org/
4. Copy `.env.example` to `.env` and adjust values for database, CAPTCHA, and email credentials.
5. Start XAMPP and visit `http://localhost/acs/`.

## Email Configuration (Gmail SMTP)

To enable email verification and password reset emails:

1. **Enable 2-Step Verification** on your Google account: https://myaccount.google.com/security
2. **Create an App Password**: 
   - Go to Google Account > Security > App passwords
   - Select "Mail" and "Windows Computer" (or your OS)
   - Google will generate a 16-character password (e.g., `abcd efgh ijkl mnop`)
3. **Update `.env`**:
   ```
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_USERNAME=your-email@gmail.com
   MAIL_PASSWORD=your-app-password-here
   MAIL_FROM=your-email@gmail.com
   MAIL_FROM_NAME="Your App Name"
   ```
4. **Test your configuration**: Visit `http://localhost/acs/test-email.php` to send a test email.

### Troubleshooting Email Issues
- **"Failed to send verification email"**: Check `test-email.php` to diagnose the issue.
- **Check PHP error logs**: Look in `c:\xampp\apache\logs\error.log` or `php_error_log` for detailed error messages.
- **Common issues**:
  - Invalid Gmail App Password (must be generated from Google Account settings, not your regular password)
  - Port 587 blocked by firewall (try 465 as alternative with `SMTPSecure = PHPMailer::ENCRYPTION_SMTPS`)
  - PHPMailer not installed (run `composer install`)

## Notes
- The `.env` file stores secrets and is excluded from Git using `.gitignore`.
- The application verifies the Turnstile token on the server side.
- Password strength criteria include length, mixed character classes, special symbols, and avoidance of common patterns.
- The app is a prototype and should be reviewed before production use.
