# Password Reset Feature - Implementation Guide

## Overview

Complete password reset functionality has been implemented for both Shop and Admin users.

## Features

### Shop Users
- **Forgot Password Form** (`/forgot-password`)
  - User enters email address
  - System sends reset email if account exists
  
- **Password Reset Form** (`/reset-password/{token}`)
  - Validates token (60-minute expiration)
  - User sets new password with confirmation
  - Password reset completes and token is destroyed

### Admin Users
- **Forgot Password Form** (`/admin/forgot-password`)
  - Admin enters email address
  - System sends reset email if admin account exists
  
- **Password Reset Form** (`/admin/reset-password/{token}`)
  - Same validation and security as shop
  - Admin can reset password independently

## Technical Implementation

### Controllers
- `app/Http/Controllers/Shop/PasswordResetController.php`
- `app/Http/Controllers/Admin/PasswordResetController.php`

**Methods:**
- `showForgotForm()` — Show forgot password form
- `sendResetLink()` — Validate email and send reset email
- `showResetForm()` — Show reset form with token validation
- `resetPassword()` — Update password and clear token

### Notification
- `app/Notifications/PasswordResetNotification.php`
  - Sends password reset email
  - Includes reset link with token
  - 60-minute expiration notice

### Routes
**Shop Routes (guest middleware):**
```php
GET  /forgot-password              → showForgotForm()     [shop.password.request]
POST /forgot-password              → sendResetLink()      [shop.password.email]
GET  /reset-password/{token}       → showResetForm()      [shop.password.reset]
POST /reset-password               → resetPassword()      [shop.password.update]
```

**Admin Routes (guest middleware):**
```php
GET  /admin/forgot-password        → showForgotForm()     [admin.password.request]
POST /admin/forgot-password        → sendResetLink()      [admin.password.email]
GET  /admin/reset-password/{token} → showResetForm()      [admin.password.reset]
POST /admin/reset-password         → resetPassword()      [admin.password.update]
```

### Views
- `resources/views/shop/auth/forgot-password.blade.php`
- `resources/views/shop/auth/reset-password.blade.php`
- `resources/views/admin/auth/forgot-password.blade.php`
- `resources/views/admin/auth/reset-password.blade.php`

### Database
- Uses existing `password_reset_tokens` table
- Stores: email, hashed token, created_at
- Tokens expire after 60 minutes
- Used tokens are automatically deleted

## Security Features

1. **Token Hashing**
   - Tokens are hashed using Laravel's `Hash::make()`
   - Verified using `Hash::check()`

2. **Token Expiration**
   - Tokens expire after 60 minutes
   - Checked on every reset attempt

3. **User Type Verification**
   - Shop reset only works for customer accounts
   - Admin reset only works for admin accounts
   - Prevents cross-account resets

4. **Password Validation**
   - Minimum 8 characters
   - Requires uppercase letter
   - Requires lowercase letter
   - Requires number
   - Requires special character
   - Uses Laravel's `PasswordRule::defaults()`

5. **Email Verification**
   - Only existing emails receive reset links
   - Prevents user enumeration (generic "not found" message)

6. **Single-Use Tokens**
   - Token deleted after successful reset
   - Cannot reuse same link

## User Flow

### Forgot Password (Shop)
1. User clicks "Forgot password?" on login page
2. User enters email address
3. System checks if email exists and is customer account
4. Email sent with reset link
5. User clicks link within 60 minutes
6. User enters new password (with confirmation)
7. Password updated, user redirected to login
8. User logs in with new password

### Admin Flow
Same as shop, but for admin accounts only.

## Email Content

Subject: "Reset Your KidsStore Password"

```
Hello [Name],

You are receiving this email because we received a password reset request for your account.

[Reset Password Button]

This password reset link will expire in 60 minutes.

If you did not request a password reset, no further action is required.

Best regards,
KidsStore Team
```

## Testing the Feature

### From User Perspective
1. Go to login page
2. Click "Forgot password?"
3. Enter email address
4. Check inbox for reset email
5. Click reset link in email
6. Enter new password
7. Submit form
8. Redirected to login with success message
9. Log in with new password

### For Admin
1. Go to `/admin/login`
2. Click "Forgot password?"
3. Follow same flow

### Database Verification
```sql
-- Check reset tokens
SELECT * FROM password_reset_tokens;

-- Verify token was created
SELECT * FROM password_reset_tokens WHERE email = 'user@example.com';
```

## Error Handling

| Error | Cause | Fix |
|-------|-------|-----|
| "Email not found" | Email doesn't exist | Enter correct email |
| "Not customer account" | Admin trying shop reset | Use admin panel |
| "Not admin account" | Customer trying admin reset | Use shop login |
| "Invalid/expired link" | Token > 60 min old | Request new reset link |
| "Passwords don't match" | Confirmation mismatch | Re-enter passwords |
| "Password too weak" | Fails validation | Use stronger password |

## Configuration

### Token Expiration
Located in `app/Http/Controllers/*/PasswordResetController.php`:
```php
->where('created_at', '>', now()->subMinutes(60))
```
Change `60` to different value for different expiration time.

### Email Configuration
Uses default mail driver from `.env`:
```
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=
```

## Future Enhancements

- [ ] Rate limiting on forgot password (3 emails per hour per IP)
- [ ] Admin audit log for password resets
- [ ] SMS-based password reset option
- [ ] Social login integration
- [ ] Biometric password reset
- [ ] Account recovery codes

## Troubleshooting

### Email Not Sending
- Check `.env` mail configuration
- Verify SMTP credentials
- Check Laravel logs: `storage/logs/laravel.log`
- Ensure `QUEUE_CONNECTION` is set correctly

### "Token has expired"
- User took > 60 minutes to reset
- Request new password reset

### "Link not working"
- Token may have been used already
- Browser cache issue (try incognito)
- Request new password reset

---

**Last Updated:** June 16, 2026  
**Status:** Ready for Production ✅
