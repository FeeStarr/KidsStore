<?php

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Forbidden.";
    exit;
}
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

// Bootstrap the application
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Safety guard: this script permanently DROPS every table in the database.
// It must not run accidentally (e.g. via a mis-clicked VS Code task).
// Require explicit confirmation via env var or CLI flag before proceeding.
$confirmed = (getenv('RESET_DB_CONFIRM') === '1')
    || in_array('--yes', $argv, true)
    || in_array('--force', $argv, true);

if (!$confirmed) {
    $dbName = config('database.connections.' . config('database.default') . '.database', 'unknown');
    echo "!!! DANGER: This will PERMANENTLY DROP every table in database '{$dbName}' and re-run migrations.\n";
    echo "All data will be lost unless you have a backup.\n\n";
    echo "To proceed, re-run with one of:\n";
    echo "  RESET_DB_CONFIRM=1 php reset_db.php\n";
    echo "  php reset_db.php --yes\n\n";
    echo "Aborting. No changes were made.\n";
    exit(1);
}

echo "=== Clearing Migrations Table ===\n\n";

try {
    // Drop all tables
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    $tables = DB::select("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'kidsstore'");
    foreach ($tables as $table) {
        DB::statement("DROP TABLE `{$table->TABLE_NAME}`");
    }
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
    echo "✓ All tables dropped\n";
    
    // Directly clear migrations (in case table still exists)
    try {
        DB::table('migrations')->truncate();
        echo "✓ Migrations table cleared\n\n";
    } catch (\Exception $e) {
        echo "✓ No migrations table found (expected)\n\n";
    }
    
    // Now run migrations
    echo "Running fresh migrations...\n";
    $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
    $exitCode = $kernel->call('migrate', ['--force' => true, '--no-interaction' => true]);
    
    if ($exitCode === 0) {
        echo "\n✓ All migrations completed successfully!\n";
    } else {
        echo "\n✗ Migrations failed with exit code: $exitCode\n";
    }
    
    // Verify tables
    echo "\nVerifying key tables:\n";
    $tables = DB::select("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'kidsstore'");
    $tableNames = array_map(fn($t) => $t->TABLE_NAME, $tables);
    
    $requiredTables = ['products', 'product_variants', 'pickup_stations', 'customers', 'orders'];
    foreach ($requiredTables as $table) {
        if (in_array($table, $tableNames)) {
            echo "  ✓ $table\n";
        } else {
            echo "  ✗ $table (MISSING)\n";
        }
    }
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
