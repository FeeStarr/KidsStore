<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;
use App\Models\Purchase;

$id = $argv[1] ?? null;
if (! $id) {
    echo "Usage: php scripts/check_purchase.php <id>\n";
    exit(1);
}

$row = DB::table('purchases')->where('id', $id)->first();
if (! $row) {
    echo "Purchase $id not found in purchases table\n";
} else {
    echo "Purchase {$row->id} found in purchases table: reference={$row->reference}, supplier_id={$row->supplier_id}\n";
}
return 0;
