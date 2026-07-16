<?php
/**
 * Run this script by visiting yourdomain.com/fix_storage.php 
 * it will create the symlink for your images and check directory permissions.
 */

$root = dirname(__DIR__);

echo "<h2>Kids Store Production Fixer</h2>";

// 1. Create Symlink
$target = $root . '/storage/app/public';
$link = __DIR__ . '/storage';

if (!file_exists($link)) {
    if (symlink($target, $link)) {
        echo "✅ Storage symlink created successfully.<br>";
    } else {
        echo "❌ Failed to create storage symlink.<br>";
    }
} else {
    echo "ℹ️ Storage symlink already exists.<br>";
}

// 2. Check Permissions
$paths = [
    $root . '/storage',
    $root . '/storage/logs',
    $root . '/storage/framework',
    $root . '/storage/framework/views',
    $root . '/storage/framework/cache',
    $root . '/bootstrap/cache',
];

foreach ($paths as $path) {
    if (is_writable($path)) {
        echo "✅ Writable: " . basename($path) . "<br>";
    } else {
        echo "❌ NOT Writable: " . $path . " (Fix: Change permission to 775 or 755 in cPanel)<br>";
    }
}

// 2.5 New Detailed Debug Block
echo "<h3>System Diagnostics:</h3>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "Current File: " . __FILE__ . "<br>";
echo "Root Path: " . $root . "<br>";
echo "Vendor Autoload Exists: " . (file_exists($root . '/vendor/autoload.php') ? '✅ Yes' : '❌ No (Upload vendor folder)') . "<br>";
echo "Bootstrap App Exists: " . (file_exists($root . '/bootstrap/app.php') ? '✅ Yes' : '❌ No') . "<br>";
echo "Composer.json Exists: " . (file_exists($root . '/composer.json') ? '✅ Yes' : '❌ No (Upload composer.json)') . "<br>";

// 3. Check Laravel Log for latest error
$logFile = $root . '/storage/logs/laravel.log';
echo "<h3>Latest Error Log (" . basename($logFile) . "):</h3>";
if (file_exists($logFile)) {
    $content = file($logFile);
    $lastLines = array_slice($content, -15);
    echo "<pre style='background: #333; color: #fff; padding: 15px; border-radius: 5px; overflow: auto; max-height: 400px;'>" . htmlspecialchars(implode("", $lastLines)) . "</pre>";
} else {
    echo "❌ No log file found at " . $logFile . "<br>";
}

// 4. Check DB Connection from within Laravel
echo "<h3>Laravel Boot Test:</h3>";
try {
    if (!file_exists($root . '/vendor/autoload.php')) {
        throw new Exception("Cannot attempt boot: vendor/autoload.php is missing.");
    }
    
    // Attempt to capture output because loading Laravel might trigger a fatal error
    ob_start();
    require $root . '/vendor/autoload.php';
    $app = require_once $root . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $status = $kernel->bootstrap();
    ob_end_clean();
    
    echo "✅ Laravel Kernel Boostrapped.<br>";
    
    $dbName = config('database.connections.mysql.database');
    echo "Attempting connection to database: <b>" . $dbName . "</b><br>";
    
    $results = DB::select('SELECT DATABASE() as db');
    echo "✅ Database Connection Successful! Actual joined DB: " . $results[0]->db . "<br>";
} catch (\Throwable $e) {
    echo "<div style='color: red; padding: 10px; border: 1px solid red;'>";
    echo "<b>Fatal Error during Boot:</b><br>";
    echo "Message: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . " on line " . $e->getLine() . "<br>";
    echo "</div>";
}

// 5. Test .env
if (file_exists($root . '/.env')) {
    echo "✅ .env file found.<br>";
} else {
    echo "❌ .env file missing in " . $root . "<br>";
}
