<?php

if (php_sapi_name() !== 'cli') {
	http_response_code(403);
	echo "Forbidden.";
	exit;
}
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

echo "Starting migrations...\n";
$exitCode = $kernel->call('migrate', ['--force' => true]);
echo "Migration complete. Exit code: $exitCode\n";
