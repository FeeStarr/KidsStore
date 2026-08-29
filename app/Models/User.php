<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    public const ROLE_SUPERADMIN = 'superadmin';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_VENDOR = 'vendor';
    public const ROLE_CUSTOMER = 'customer';
    public const ROLE_STAFF = 'staff';
    public const ROLE_DELIVERY_AGENT = 'delivery_agent';

    // Staff subtypes
    public const STAFF_ORDER_PROCESSING = 'order_processing';
    public const STAFF_INVENTORY = 'inventory';
    public const STAFF_CUSTOMER_SUPPORT = 'customer_support';

    public static function roleOptions(): array
    {
        return [
            self::ROLE_SUPERADMIN => 'Super Admin',
            self::ROLE_ADMIN => 'Admin',
            self::ROLE_VENDOR => 'Vendor',
            self::ROLE_CUSTOMER => 'Customer',
            self::ROLE_STAFF => 'Staff',
            self::ROLE_DELIVERY_AGENT => 'Delivery Agent',
        ];
    }

    public static function staffTypes(): array
    {
        return [
            self::STAFF_ORDER_PROCESSING => 'Order Processing',
            self::STAFF_INVENTORY => 'Inventory Management',
            self::STAFF_CUSTOMER_SUPPORT => 'Customer Support',
        ];
    }

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'password',
        'role',
        'staff_type',
        'vendor_id',
        'is_active',
        'two_factor_enabled',
        'two_factor_backup_code',
        'two_factor_code',
        'two_factor_expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_backup_code',
        'two_factor_code',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'     => 'datetime',
            'two_factor_expires_at' => 'datetime',
            'two_factor_enabled'    => 'boolean',
            'password'              => 'hashed',
        ];
    }

    /**
     * Get all orders for this user (if role is 'customer').
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    /**
     * Get all product reviews for this user (if role is 'customer').
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class, 'customer_id');
    }

    /**
     * Get the vendor profile (if user is a vendor).
     */
    public function vendor()
    {
        return $this->hasOne(Vendor::class, 'user_id');
    }

    /**
     * Check if user is a customer.
     */
    public function isCustomer(): bool
    {
        return $this->hasRole(self::ROLE_CUSTOMER);
    }

    /**
     * Check if user has any admin role (admin or superadmin).
     */
    public function isAdmin(): bool
    {
        return $this->hasAnyRole([self::ROLE_ADMIN, self::ROLE_SUPERADMIN]);
    }

    /**
     * Check if user is a superadmin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::ROLE_SUPERADMIN);
    }

    /**
     * Check if user is a vendor.
     */
    public function isVendor(): bool
    {
        return $this->hasRole(self::ROLE_VENDOR);
    }

    /**
     * Check if user is staff.
     */
    public function isStaff(): bool
    {
        return $this->hasRole(self::ROLE_STAFF);
    }

    /**
     * Check if user is a specific staff type.
     */
    public function isStaffType(string $type): bool
    {
        return $this->isStaff() && $this->staff_type === $type;
    }

    /**
     * Check if user is delivery agent.
     */
    public function isDeliveryAgent(): bool
    {
        return $this->hasRole(self::ROLE_DELIVERY_AGENT);
    }

    /**
     * Check if user is active.
     */
    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * Check if user has permission for a specific action.
     * More granular permission checking can be added here.
     */
    public function hasPermission(string $permission): bool
    {
        // SuperAdmin has all permissions
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Map permissions to roles
        $rolePermissions = [
            'view_dashboard' => [self::ROLE_ADMIN, self::ROLE_SUPERADMIN, self::ROLE_VENDOR, self::ROLE_STAFF],
            'manage_products' => [self::ROLE_ADMIN, self::ROLE_SUPERADMIN, self::ROLE_VENDOR],
            'manage_categories' => [self::ROLE_ADMIN, self::ROLE_SUPERADMIN],
            'manage_customers' => [self::ROLE_ADMIN, self::ROLE_SUPERADMIN],
            'manage_vendors' => [self::ROLE_ADMIN, self::ROLE_SUPERADMIN],
            'manage_orders' => [self::ROLE_ADMIN, self::ROLE_SUPERADMIN, self::ROLE_STAFF],
            'manage_staff' => [self::ROLE_ADMIN, self::ROLE_SUPERADMIN],
            'view_analytics' => [self::ROLE_ADMIN, self::ROLE_SUPERADMIN, self::ROLE_VENDOR],
            'manage_settings' => [self::ROLE_ADMIN, self::ROLE_SUPERADMIN],
            'manage_payment_gateway' => [self::ROLE_SUPERADMIN],
            'view_reports' => [self::ROLE_ADMIN, self::ROLE_SUPERADMIN],
            'manage_coupons' => [self::ROLE_ADMIN, self::ROLE_SUPERADMIN],
            'manage_deals' => [self::ROLE_ADMIN, self::ROLE_SUPERADMIN],
            'update_order_status' => [self::ROLE_ADMIN, self::ROLE_SUPERADMIN, self::ROLE_VENDOR, self::ROLE_STAFF],
            'view_customer_data' => [self::ROLE_ADMIN, self::ROLE_SUPERADMIN],
            'update_inventory' => [self::ROLE_ADMIN, self::ROLE_SUPERADMIN, self::ROLE_VENDOR, self::ROLE_STAFF],
            'view_inventory' => [self::ROLE_ADMIN, self::ROLE_SUPERADMIN, self::ROLE_VENDOR, self::ROLE_STAFF],
            'checkout' => [self::ROLE_CUSTOMER],
            'leave_review' => [self::ROLE_CUSTOMER],
            'manage_deliveries' => [self::ROLE_DELIVERY_AGENT, self::ROLE_ADMIN, self::ROLE_SUPERADMIN],
        ];

        return $this->hasAnyRole($rolePermissions[$permission] ?? []);
    }

    /**
     * Roles pivot (supports multiple roles per user if needed).
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    public function hasRole(string $role): bool
    {
        if ($this->role === $role) {
            return true;
        }

        if ($this->relationLoaded('roles')) {
            return $this->roles->contains('name', $role);
        }

        return $this->roles()->where('name', $role)->exists();
    }

    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function syncRoles(array $roles): void
    {
        $roleIds = Role::whereIn('name', $roles)->pluck('id')->all();
        $this->roles()->sync($roleIds);

        if (! empty($roles)) {
            $primary = $roles[0];
            $this->role = $primary;
            $this->save();
        }
    }

    public function addRoles(array $roles): void
    {
        $existing = Role::whereIn('name', $roles)->pluck('id')->all();
        $this->roles()->syncWithoutDetaching($existing);

        if (! $this->role && ! empty($roles)) {
            $this->role = $roles[0];
            $this->save();
        }
    }

    public function generateTwoFactorCode(): void
    {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->update([
            'two_factor_code' => $code,
            'two_factor_expires_at' => now()->addMinutes(10),
        ]);
    }

    public function validateTwoFactorCode(string $code): bool
    {
        return $this->two_factor_code && $this->two_factor_expires_at && now()->lte($this->two_factor_expires_at)
            && hash_equals($this->two_factor_code, $code);
    }

    public function resetTwoFactorCode(): void
    {
        $this->update([
            'two_factor_code' => null,
            'two_factor_expires_at' => null,
        ]);
    }

    /**
     * Generate a one-time backup code, store it hashed, and return the plaintext.
     * The plaintext is shown once by the admin - never stored or logged.
     */
    public function generateBackupCode(): string
    {
        // 10-character alphanumeric code e.g. "A3K9-XP2WM"
        $plain = strtoupper(substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(8))), 0, 4))
               . '-'
               . strtoupper(substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(8))), 0, 5));

        $this->update(['two_factor_backup_code' => \Illuminate\Support\Facades\Hash::make($plain)]);

        return $plain;
    }

    /**
     * Verify a backup code. If valid, consume it (single-use).
     */
    public function useBackupCode(string $plain): bool
    {
        if (! $this->two_factor_backup_code) {
            return false;
        }

        if (! \Illuminate\Support\Facades\Hash::check($plain, $this->two_factor_backup_code)) {
            return false;
        }

        // Consume - clear so it cannot be used again
        $this->update(['two_factor_backup_code' => null]);

        return true;
    }

    public function hasBackupCode(): bool
    {
        return ! empty($this->two_factor_backup_code);
    }

    /**
     * Profile relationship.
     */
    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    /**
     * Addresses relationship.
     */
    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    /**
     * Vendor approval record (if any).
     */
    public function vendorApproval()
    {
        return $this->hasOne(VendorApproval::class);
    }

    /**
     * Audit logs for this user.
     */
    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }
}
