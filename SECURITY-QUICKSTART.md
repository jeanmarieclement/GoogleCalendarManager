# Security Quick Start Guide

## Essential Security Steps

### 1. Generate Encryption Key

```bash
php -r "echo base64_encode(random_bytes(32)) . PHP_EOL;"
```

Save the output securely and add to your config:

```php
'encryption_key' => base64_decode('YOUR_KEY_HERE'),
```

### 2. Configure Session Security

In production with HTTPS:

```php
SessionSecurity::startSecureSession(true);
```

### 3. Set Debug Mode

In `calendar-config.php`:

```php
'debug' => false, // ALWAYS false in production
```

### 4. File Permissions

Ensure correct permissions:

```bash
chmod 750 token/ logs/ cache/
chmod 640 token/*.json logs/*.log
```

### 5. HTTPS Required

**Always use HTTPS in production** to protect:
- OAuth tokens
- Session cookies
- User credentials
- API communications

### 6. Environment Variables (Recommended)

Store sensitive data in environment variables instead of config files:

```php
// In calendar-config.php
'client_id' => getenv('GOOGLE_CLIENT_ID'),
'client_secret' => getenv('GOOGLE_CLIENT_SECRET'),
'encryption_key' => base64_decode(getenv('ENCRYPTION_KEY')),
```

### 7. Review Security Headers

The application sets these security headers:
- X-Frame-Options
- X-Content-Type-Options
- X-XSS-Protection
- Content-Security-Policy
- Referrer-Policy

Verify they work with your infrastructure.

### 8. Regular Updates

```bash
composer update
```

Keep PHP and dependencies updated.

---

## Quick Security Checklist

- [ ] Generated encryption key
- [ ] HTTPS enabled
- [ ] Debug mode disabled
- [ ] File permissions set correctly
- [ ] Using environment variables for secrets
- [ ] PHP 8.1+ installed
- [ ] Session security configured
- [ ] Reviewed security headers

---

For complete security documentation, see [SECURITY.md](SECURITY.md)
