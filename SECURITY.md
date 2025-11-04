# Security Policy

## Overview

This document outlines the security measures implemented in Google Calendar Manager and provides guidelines for secure deployment and usage.

## Security Features Implemented

### 1. CSRF Protection

**Status**: ✅ Implemented

All forms now include CSRF (Cross-Site Request Forgery) tokens to prevent unauthorized actions.

**Implementation**:
- `CSRFProtection` class provides token generation and validation
- Tokens expire after 1 hour
- Uses `hash_equals()` to prevent timing attacks
- All POST forms protected with CSRF tokens

**Usage**:
```php
// In forms
<?php echo CSRFProtection::getTokenField(); ?>

// Validation (automatic in calendar-example.php)
CSRFProtection::verifyPostToken();
```

---

### 2. Secure Session Management

**Status**: ✅ Implemented

Sessions are configured with security best practices.

**Features**:
- HttpOnly cookies (prevents XSS cookie theft)
- SameSite=Strict (prevents CSRF)
- Secure cookies on HTTPS
- Session ID regeneration
- User agent validation
- Automatic session timeout (4 hours)
- Session ID regeneration every 30 minutes

**Configuration**:
```php
SessionSecurity::startSecureSession(true); // Force HTTPS in production
```

---

### 3. OAuth Token Encryption

**Status**: ✅ Implemented

OAuth tokens are encrypted at rest using AES-256-GCM.

**Features**:
- AES-256-GCM encryption (authenticated encryption)
- Random IV for each encryption
- Configurable encryption key
- Automatic decryption on load

**Setup**:
```php
// Generate encryption key
$key = base64_encode(random_bytes(32));

// In config
'encryption_key' => base64_decode($key),
```

**Best Practice**: Store the encryption key in environment variables, not in config files.

---

### 4. Input Validation & Sanitization

**Status**: ✅ Implemented

All user inputs are validated and sanitized.

**Validations**:
- Event title: max 255 characters
- Event description: max 5000 characters
- Date validation with logical checks
- Future date limits (max 10 years)
- Event ID format validation
- Calendar ID validation

**Output Sanitization**:
```php
sanitizeOutput($data); // Uses htmlspecialchars with ENT_QUOTES
```

---

### 5. Path Traversal Prevention

**Status**: ✅ Implemented

File paths are validated to prevent directory traversal attacks.

**Features**:
- Validates paths are within application directory
- Checks for `..` sequences
- Validates expected subdirectories
- Uses `realpath()` for canonicalization

---

### 6. Security Headers

**Status**: ✅ Implemented

Multiple security headers protect against common attacks.

**Headers**:
- `X-Frame-Options: DENY` - Prevents clickjacking
- `X-Content-Type-Options: nosniff` - Prevents MIME sniffing
- `X-XSS-Protection: 1; mode=block` - XSS protection
- `Content-Security-Policy` - Restricts resource loading
- `Referrer-Policy: strict-origin-when-cross-origin` - Controls referrer

---

### 7. Secure File Permissions

**Status**: ✅ Implemented

Restrictive file permissions protect sensitive data.

**Permissions**:
- Token files: `0640` (owner read/write, group read)
- Token directory: `0750` (owner rwx, group rx)
- Log files: `0640`
- Log directory: `0750`
- Cache directory: `0750`

---

### 8. Error Handling

**Status**: ✅ Implemented

Error messages don't expose sensitive information.

**Features**:
- Generic error messages shown to users
- Detailed errors logged server-side
- Debug mode for development (disabled in production)
- OAuth state validation with descriptive errors

---

### 9. OAuth Security

**Status**: ✅ Implemented

OAuth flow protected against CSRF and other attacks.

**Features**:
- State parameter validation
- State cleanup after use
- Random state generation (32 bytes)
- Secure token storage

---

### 10. Updated Dependencies

**Status**: ✅ Implemented

Using supported PHP versions and up-to-date libraries.

**Versions**:
- PHP: 8.2 (actively supported)
- Minimum PHP: 8.1
- Google API Client: ^2.12.0

---

## Security Checklist for Deployment

### Before Going to Production

- [ ] Set `debug` to `false` in config
- [ ] Generate and securely store encryption key
- [ ] Use environment variables for sensitive data
- [ ] Enable HTTPS
- [ ] Update `SessionSecurity::startSecureSession(true)`
- [ ] Review and restrict file permissions
- [ ] Configure proper backup for token directory
- [ ] Set up proper logging and monitoring
- [ ] Review CSP policy for your domain
- [ ] Enable PHP error logging (disable display_errors)

### Environment Variables (Recommended)

```bash
# .env (never commit this file)
ENCRYPTION_KEY=your_base64_encoded_32_byte_key
GOOGLE_CLIENT_ID=your_client_id
GOOGLE_CLIENT_SECRET=your_client_secret
```

### PHP Configuration (php.ini)

```ini
# Production settings
display_errors = Off
log_errors = On
error_log = /var/log/php/error.log

# Session security
session.cookie_httponly = 1
session.cookie_secure = 1
session.cookie_samesite = Strict
session.use_only_cookies = 1
session.sid_length = 48
```

---

## Threat Model

### Protected Against

✅ Cross-Site Request Forgery (CSRF)
✅ Cross-Site Scripting (XSS)
✅ Session Hijacking
✅ Session Fixation
✅ Path Traversal
✅ Information Disclosure
✅ Clickjacking
✅ MIME Sniffing
✅ Token Theft (encrypted at rest)
✅ OAuth CSRF

### Additional Recommendations

⚠️ **Rate Limiting**: Consider implementing rate limiting for login attempts and API calls

⚠️ **IP Whitelisting**: For sensitive deployments, consider IP whitelisting

⚠️ **2FA**: Consider implementing two-factor authentication for critical operations

⚠️ **Audit Logging**: Implement comprehensive audit logging for all sensitive operations

⚠️ **Regular Updates**: Keep dependencies updated regularly

---

## Reporting Security Issues

If you discover a security vulnerability, please:

1. **Do NOT** open a public issue
2. Email the maintainer directly
3. Include:
   - Description of the vulnerability
   - Steps to reproduce
   - Potential impact
   - Suggested fix (if any)

---

## Security Best Practices

### For Developers

1. **Never commit sensitive data** to version control
2. **Use environment variables** for credentials
3. **Keep dependencies updated**
4. **Review code changes** for security implications
5. **Test security features** regularly
6. **Use prepared statements** for database queries (if added)
7. **Validate all inputs** from users and external sources
8. **Sanitize all outputs**
9. **Use HTTPS** in production
10. **Keep error messages generic** for users

### For System Administrators

1. **Keep PHP updated** to latest stable version
2. **Configure firewall** properly
3. **Use fail2ban** or similar for intrusion prevention
4. **Monitor logs** for suspicious activity
5. **Regular security audits**
6. **Backup encryption keys** securely
7. **Implement monitoring** and alerting
8. **Use SELinux/AppArmor** if possible
9. **Restrict network access** to necessary services
10. **Regular penetration testing**

---

## License

This security documentation is part of the Google Calendar Manager project.

---

## Last Updated

2024-11-04
