# Enable PHP Sockets Extension in XAMPP

The Sockets extension is needed for SMTP email connections. Here's how to enable it:

## Quick Fix (Windows/XAMPP)

### Step 1: Open php.ini
Navigate to: `c:\xampp\php\php.ini`

### Step 2: Find the Extensions Section
Look for the line starting with:
```
;extension=sockets
```

### Step 3: Uncomment the Line
Remove the semicolon (`;`) from the beginning:

**Before:**
```
;extension=sockets
```

**After:**
```
extension=sockets
```

### Step 4: Save the File
Save `php.ini` and close it.

### Step 5: Restart Apache
1. Open XAMPP Control Panel
2. Click **Stop** for Apache
3. Wait a few seconds
4. Click **Start** for Apache

### Step 6: Verify
1. Visit `http://localhost/acs/test-email.php`
2. Check if "✓ Sockets Extension" now shows **PASS**

## If Still Not Working

### Option A: Check if extension is compiled
The sockets extension should be included with XAMPP by default. If it's missing:

1. **Find your PHP.exe location:**
   ```
   c:\xampp\php\php.exe
   ```

2. **Check loaded extensions:**
   Open Command Prompt and run:
   ```bash
   c:\xampp\php\php.exe -m | findstr sockets
   ```
   
   If it shows `sockets`, the extension is loaded.

### Option B: Check for Extension DLL
The sockets extension should be in: `c:\xampp\php\ext\php_sockets.dll`

If missing, you may need to:
- Reinstall XAMPP with all extensions
- Or manually download the extension DLL that matches your PHP version

### Option C: Verify php.ini Location
Some XAMPP installations use both `php.ini` and `php-prod.ini`. Make sure you edited the correct file:

1. **Check which php.ini is being used:**
   Open Command Prompt and run:
   ```bash
   c:\xampp\php\php.exe -i | findstr "Loaded Configuration"
   ```

2. **Edit that specific file** if it's different from the default location

## Verify Configuration Worked

After restarting Apache, visit `http://localhost/acs/test-email.php` and confirm:
- ✅ Sockets Extension (should show PASS)
- ✅ OpenSSL Extension (should show PASS)

Once both show PASS, you can send test emails.

## Still Having Issues?

Check the XAMPP Apache error log:
```
c:\xampp\apache\logs\error.log
```

Look for messages like:
- `Warning: PHP Startup: Unable to load dynamic library 'php_sockets.dll'`
- This would indicate the extension file is missing or corrupted

If you see this, reinstall XAMPP or download the matching PHP extension package.
