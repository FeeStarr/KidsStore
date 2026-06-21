<?php
// Creates the test database using credentials from .env.testing
$env = __DIR__ . '/../.env.testing';
if (!file_exists($env)) {
    echo ".env.testing not found\n";
    exit(1);
}
$contents = file_get_contents($env);
$lines = preg_split('/\r?\n/', $contents);
$vars = [];
foreach ($lines as $line) {
    if (!trim($line) || strpos(trim($line),'#')===0) continue;
    if (strpos($line,'=')===false) continue;
    [$k,$v] = explode('=', $line, 2);
    $v = trim($v);
    // strip surrounding quotes
    if ((substr($v,0,1)==='"' && substr($v,-1)==='"') || (substr($v,0,1)==="'" && substr($v,-1)==="'")) {
        $v = substr($v,1,-1);
    }
    $vars[trim($k)] = $v;
}
$db = $vars['DB_DATABASE'] ?? null;
$host = $vars['DB_HOST'] ?? '127.0.0.1';
$port = $vars['DB_PORT'] ?? 3306;
$user = $vars['DB_USERNAME'] ?? 'root';
$pass = $vars['DB_PASSWORD'] ?? '';
if (!$db) {
    echo "DB_DATABASE not set in .env.testing\n";
    exit(1);
}
try {
    $dsn = "mysql:host={$host};port={$port}";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database '{$db}' ensured.\n";
    exit(0);
} catch (PDOException $e) {
    echo "Failed to create database: " . $e->getMessage() . "\n";
    exit(1);
}
