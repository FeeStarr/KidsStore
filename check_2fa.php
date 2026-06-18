<?php
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== 2FA Code Diagnostic ===\n\n";

echo "Step 1: Current Mail Configuration\n";
echo "  MAIL_MAILER: " . env('MAIL_MAILER') . "\n";
echo "  MAIL_HOST: " . env('MAIL_HOST') . "\n";
echo "  MAIL_PORT: " . env('MAIL_PORT') . "\n";
if (env('MAIL_MAILER') === 'log') {
    echo "  ⚠️  WARNING: Emails are being logged, not sent!\n";
}
echo "\n";

echo "Step 2: Recent 2FA Codes in Database\n";
try {
    $users = DB::table('users')
        ->whereNotNull('two_factor_code')
        ->where('two_factor_code', '!=', '')
        ->select('id', 'email', 'name', 'two_factor_code', 'two_factor_expires_at', 'updated_at')
        ->orderByDesc('updated_at')
        ->limit(10)
        ->get();
    
    if ($users->isEmpty()) {
        echo "  No pending 2FA codes\n";
    } else {
        foreach ($users as $user) {
            echo "  ─────────────────────\n";
            echo "  Email: {$user->email}\n";
            echo "  Name: {$user->name}\n";
            echo "  Code: {$user->two_factor_code}\n";
            echo "  Expires: {$user->two_factor_expires_at}\n";
        }
    }
} catch (\Exception $e) {
    echo "  Error reading 2FA codes: " . $e->getMessage() . "\n";
}

echo "\nStep 3: Recent Log Entries (2FA/Email)\n";
$logPath = storage_path('logs/laravel.log');
if (file_exists($logPath)) {
    $lines = explode("\n", file_get_contents($logPath));
    $matches = array_filter($lines, fn($line) => 
        stripos($line, 'verification') !== false || 
        stripos($line, '2fa') !== false ||
        stripos($line, 'notification') !== false
    );
    
    if (empty($matches)) {
        echo "  No 2FA-related log entries found\n";
    } else {
        foreach (array_slice($matches, -5) as $line) {
            echo "  " . trim($line) . "\n";
        }
    }
} else {
    echo "  Log file not found\n";
}

echo "\n";
