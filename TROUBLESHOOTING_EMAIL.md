# Email Configuration Troubleshooting Guide

## Quick Diagnosis

1. **Visit the test page**: Go to `http://localhost/acs/test-email.php`
2. **Check your configuration**: Verify all settings are present and correct
3. **Send a test email**: Use the form to send a test message
4. **Review error logs**: Check `c:\xampp\apache\logs\error.log` for error messages

## Common Issues & Solutions

### ❌ "Connection timed out" or "Connection refused"
**Cause**: Port 587 is blocked or SMTP host is wrong

**Solutions**:
- Try port 465 with SSL instead of 587 with STARTTLS:
  ```
  MAIL_PORT=465
  ```
- Update `sendMailMessage()` in `utils.php`:
  ```php
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;  // Change from STARTTLS
  ```

### ❌ "Authentication failed" or "535 5.7.8 Username and password not accepted"
**Cause**: Invalid Gmail credentials

**Solutions**:
- You must use an **App Password**, not your regular Google password
- Generate one at: https://myaccount.google.com/apppasswords
- Make sure 2-Step Verification is enabled on your Google Account
- Verify your `.env` has the correct format:
  ```
  MAIL_USERNAME=your-email@gmail.com
  MAIL_PASSWORD=xxxx xxxx xxxx xxxx
  ```
- Remove any quotes from the password in `.env`

### ❌ "Failed to send verification email"
**Cause**: General mail sending failure

**Solutions**:
1. Check `.env` file exists and has all required keys
2. Run test-email.php to see detailed errors
3. Check PHP error logs for stack trace
4. Verify MAIL_USERNAME and MAIL_PASSWORD are not empty
5. Clear any cached config by restarting Apache (XAMPP Control Panel > Stop Apache > Start Apache)

### ❌ "SMTP Could not authenticate" with correct password
**Cause**: Gmail may block XAMPP's IP (localhost/127.0.0.1)

**Solutions**:
- Use a different email provider (SendGrid, Mailgun, your web host's SMTP)
- Or, create a Gmail test account specifically for development

### ❌ PHPMailer not found
**Cause**: Composer dependencies not installed

**Solutions**:
```bash
cd c:\xampp\htdocs\acs
composer install
```

## Gmail App Password Setup (Step by Step)

1. **Go to Google Account**: https://myaccount.google.com/security
2. **Enable 2-Step Verification**:
   - Click "2-Step Verification"
   - Follow the setup wizard (you'll get a code via SMS or authenticator app)
3. **Generate App Password**:
   - Click "App passwords"
   - Select "Mail" and "Windows Computer"
   - Click "Generate"
   - Google will show a 16-character password (e.g., `abcd efgh ijkl mnop`)
4. **Copy to `.env`**:
   ```
   MAIL_PASSWORD=abcd efgh ijkl mnop
   ```
5. **Test**: Visit `http://localhost/acs/test-email.php`

## Alternative Email Providers

If Gmail doesn't work, try SendGrid (free tier: 100 emails/day):
- Sign up: https://sendgrid.com
- SMTP Settings:
  ```
  MAIL_HOST=smtp.sendgrid.net
  MAIL_PORT=587
  MAIL_USERNAME=apikey
  MAIL_PASSWORD=SG.your-api-key-here
  ```

## Debugging

Check error messages in real-time by viewing `c:\xampp\apache\logs\error.log`:
- Look for entries starting with `sendMailMessage error:` for PHPMailer errors
- Stack trace will show the exact issue from Gmail/SMTP server
