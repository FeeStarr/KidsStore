<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Resolves the list of internal recipients for order/refund email notifications.
 *
 * Recipients:
 *  - All active superadmin and admin users
 *  - All active staff with staff_type = customer_support
 *
 * The "primary" recipient is the first customer-support staff found (TO:).
 * Admins and superadmins are BCC'd to avoid exposing internal addresses to customers.
 */
class NotificationRecipients
{
    /** @return Collection<int, User> */
    public static function internalStaff(): Collection
    {
        // Internal staff for order notifications: include customer support and order processing staff
        return User::where('is_active', true)
            ->where('role', User::ROLE_STAFF)
            ->whereIn('staff_type', [User::STAFF_CUSTOMER_SUPPORT, User::STAFF_ORDER_PROCESSING])
            ->orderByRaw("FIELD(staff_type, 'customer_support', 'order_processing') DESC")
            ->get();
    }

    /** Just admins + superadmins (for BCC on customer emails) */
    public static function adminUsers(): Collection
    {
        return User::where('is_active', true)
            ->whereIn('role', [User::ROLE_SUPERADMIN, User::ROLE_ADMIN])
            ->get();
    }

    /** Customer support staff only */
    public static function customerSupportStaff(): Collection
    {
        return User::where('is_active', true)
            ->where('role', User::ROLE_STAFF)
            ->where('staff_type', User::STAFF_CUSTOMER_SUPPORT)
            ->get();
    }

    /** Order processing staff only */
    public static function orderProcessingStaff(): Collection
    {
        return User::where('is_active', true)
            ->where('role', User::ROLE_STAFF)
            ->where('staff_type', User::STAFF_ORDER_PROCESSING)
            ->get();
    }
}
