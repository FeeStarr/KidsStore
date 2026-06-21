<?php

use App\Models\BankAccount;
use App\Models\PickupStationBankAccount;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Migrating pickup station bank accounts into global bank_accounts...\n";

DB::transaction(function () {
    $rows = PickupStationBankAccount::orderByDesc('is_default')->get();
    if ($rows->isEmpty()) {
        echo "No station-specific accounts found.\n";
        return;
    }

    foreach ($rows as $r) {
        // Only create one global account per unique account number
        $exists = BankAccount::where('bank_account_number', $r->bank_account_number)->first();
        if ($exists) {
            // if incoming is_default, mark existing as default
            if ($r->is_default) {
                BankAccount::query()->update(['is_default' => false]);
                $exists->update(['is_default' => true, 'is_active' => $r->is_active]);
            }
            continue;
        }

        if (empty($r->bank_account_number) && empty($r->bank_account_name) && empty($r->bank_name)) {
            continue;
        }

        // if this is marked as default, clear others
        if ($r->is_default) BankAccount::query()->update(['is_default' => false]);

        BankAccount::create([
            'bank_name' => $r->bank_name,
            'bank_account_name' => $r->bank_account_name,
            'bank_account_number' => $r->bank_account_number,
            'instructions' => $r->instructions,
            'is_active' => $r->is_active,
            'is_default' => $r->is_default,
        ]);
    }

    echo "Done migrating " . BankAccount::count() . " global accounts.\n";
});

echo "Finished.\n";
