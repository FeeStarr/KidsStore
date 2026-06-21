<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Purchase;

$purchases = Purchase::with('supplier')->orderBy('id')->get();

foreach ($purchases as $p) {
    $supplier = $p->supplier ? $p->supplier->name : '(null)';
    echo sprintf("%d | %s | supplier_id=%s | %s\n", $p->id, $p->reference, $p->supplier_id ?? 'NULL', $supplier);
}

return 0;
