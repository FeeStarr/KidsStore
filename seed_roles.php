<?php

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Forbidden.";
    exit;
}
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Populating Roles and Granting Permissions ===\n\n";

try {
    // Step 1: Seed roles table
    echo "Step 1: Seeding roles table...\n";
    
    $roles = [
        ['name' => 'superadmin', 'display_name' => 'Super Admin', 'description' => 'Full system access'],
        ['name' => 'admin', 'display_name' => 'Administrator', 'description' => 'Admin panel access'],
        ['name' => 'vendor', 'display_name' => 'Vendor', 'description' => 'Vendor/Supplier account'],
        ['name' => 'staff', 'display_name' => 'Staff', 'description' => 'Staff member'],
        ['name' => 'delivery_agent', 'display_name' => 'Delivery Agent', 'description' => 'Delivery staff'],
        ['name' => 'pickup_staff', 'display_name' => 'Pickup Staff', 'description' => 'Pickup station staff'],
        ['name' => 'delivery', 'display_name' => 'Delivery', 'description' => 'Delivery role'],
        ['name' => 'pickup', 'display_name' => 'Pickup', 'description' => 'Pickup role'],
        ['name' => 'customer', 'display_name' => 'Customer', 'description' => 'Regular customer'],
    ];
    
    foreach ($roles as $roleData) {
        $role = Role::firstOrCreate(['name' => $roleData['name']], $roleData);
        echo "  ✓ Role '{$roleData['name']}' created/verified\n";
    }
    
    echo "\nStep 2: Granting SUPERADMIN role to nafiyoza@gmail.com...\n";
    
    // Step 2: Ensure user exists
    $user = User::where('email', 'nafiyoza@gmail.com')->first();
    
    if (!$user) {
        echo "  User not found, creating...\n";
        $user = User::create([
            'name' => 'Nafiyoza',
            'email' => 'nafiyoza@gmail.com',
            'password' => \Illuminate\Support\Facades\Hash::make('Admin123'),
            'email_verified_at' => now(),
        ]);
        echo "  ✓ User created: {$user->email}\n";
    } else {
        echo "  ✓ User found: {$user->email}\n";
    }
    
    // Step 3: Assign SUPERADMIN role
    $superAdminRole = Role::where('name', 'superadmin')->first();
    
    if ($superAdminRole) {
        // Clear existing roles
        $user->roles()->detach();
        
        // Assign superadmin role
        $user->roles()->attach($superAdminRole->id);
        
        echo "  ✓ SUPERADMIN role assigned\n";
    }
    
    // Step 4: Verify
    echo "\nStep 3: Verification...\n";
    $userRoles = $user->roles()->pluck('name')->toArray();
    echo "  User: {$user->email}\n";
    echo "  Roles: " . implode(', ', $userRoles ?: ['none']) . "\n";
    echo "  Role column value: {$user->role}\n";
    
    echo "\n✓ Complete! nafiyoza@gmail.com is now a SUPERADMIN\n";
    
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
