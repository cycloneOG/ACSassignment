# Security Design Notes

This prototype is designed to demonstrate secure registration and authentication design principles.

## Key security features

- Password hashing:
  - All passwords are stored using PHP's `password_hash()`.
  - Hashes are never decrypted and password verification uses `password_verify()` if added later.

- Input validation:
  - Username validation prevents invalid characters and enforces a length policy.
  - Email fields are validated with PHP filters.
  - Password strength is evaluated on both client and server.

- Password strength policy:
  - Minimum 12 characters.
  - Mixed uppercase and lowercase letters.
  - Digit and special character requirements.
  - Protection against common passwords and use of the username.

- CAPTCHA protection:
  - Cloudflare Turnstile is used to distinguish humans from bots.
  - The CAPTCHA token is verified on the server side before registration completes.

- CSRF protection:
  - A per-session CSRF token is included in the registration form.
  - The token is validated on form submission to prevent cross-site request forgery.

- Secure configuration:
  - Sensitive values are stored in `.env` and excluded from version control.
  - Database credentials, CAPTCHA keys, and SMTP credentials are kept out of source files.

- Database security:
  - Prepared statements with PDO prevent SQL injection.
  - Password history is tracked for future policy enforcement.

- Email notification:
  - PHPMailer is used for SMTP email delivery.
  - SMTP communication is configured with TLS.
- Password reset flow:
  - Password reset tokens are time-bound and marked used after first use.
  - The system avoids leaking account existence when requesting a reset.
- Two-factor authentication:
  - Users can enable 2FA through an authenticator app.
  - Login requires a second factor when enabled.
  - 2FA can be disabled only by verifying a valid authenticator code.

## Future hardening recommendations

- Enforce unique email addresses.
- Add account login and password reset workflows.
- Implement rate limiting to prevent brute-force registration or CAPTCHA abuse.
- Add multi-factor authentication for sensitive operations.
- Use HTTPS in production to protect credentials in transit.
- Rotate CAPTCHA secret keys and email app passwords regularly.
