<?php

if (php_sapi_name() !== 'cli') {
	http_response_code(403);
	echo "Forbidden.";
	exit;
}
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);

echo "Clearing Laravel caches...\n\n";

$kernel->call('config:clear');
echo "✓ Config cache cleared\n";

$kernel->call('cache:clear');
echo "✓ Application cache cleared\n";

echo "\nRetesting email configuration...\n\n";
