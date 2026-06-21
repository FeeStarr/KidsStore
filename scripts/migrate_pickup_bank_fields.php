<?php

use App\Models\PickupStation;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Starting migration of pickup station bank fields into pickup_station_bank_accounts...\n";

DB::transaction(function () {
    $stations = PickupStation::all();
    foreach ($stations as $station) {
        $bank = $station->bank_name;
        $accName = $station->bank_account_name;
        $accNo = $station->bank_account_number;
        $instr = $station->bank_instructions;
        if ($bank || $accName || $accNo) {
            $station->bankAccounts()->create([
                'bank_name' => $bank,
                'bank_account_name' => $accName,
                'bank_account_number' => $accNo,
                'instructions' => $instr,
                'is_active' => true,
                'is_default' => true,
            ]);

            // Clear legacy fields to avoid duplicate display
            $station->update([
                'bank_name' => null,
                'bank_account_name' => null,
                'bank_account_number' => null,
                'bank_instructions' => null,
            ]);

            echo "Migrated station {$station->id}\n";
        }
    }
});

echo "Done.\n";
