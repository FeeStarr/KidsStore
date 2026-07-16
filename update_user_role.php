<?php

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Forbidden.";
    exit;
}
use App\Models\User;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Updating User Role Column ===\n\n";

try {
    $user = User::where('email', 'nafiyoza@gmail.com')->firstOrFail();
    
    $oldRole = $user->role;
    $user->update(['role' => 'superadmin']);
    
    echo "✓ User role column updated\n";
    echo "  Email: {$user->email}\n";
    echo "  Old role: $oldRole\n";
    echo "  New role: {$user->role}\n";
    echo "  Pivot roles: " . $user->roles()->pluck('name')->join(', ') . "\n";
    
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
