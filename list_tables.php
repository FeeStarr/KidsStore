<?php
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== All Tables in Database ===\n\n";

try {
    $tables = DB::select("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'kidsstore' ORDER BY TABLE_NAME");
    $tableNames = array_map(fn($t) => $t->TABLE_NAME, $tables);
    
    foreach ($tableNames as $table) {
        echo "  ✓ $table\n";
    }
    
    echo "\nTotal: " . count($tableNames) . " tables\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
