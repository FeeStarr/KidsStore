<?php
$dir = __DIR__ . '/../storage/framework/views';
if (!is_dir($dir)) {
    echo "Views dir not found: $dir\n";
    exit(1);
}
$files = glob($dir . '/*.php');
$removed = 0;
foreach ($files as $f) {
    if (basename($f) === '.gitignore') continue;
    @unlink($f);
    $removed++;
}
echo "Removed $removed compiled view(s)\n";
