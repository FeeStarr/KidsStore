<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo 'OPcache cleared successfully.';
} else {
    echo 'OPcache not available.';
}
if (function_exists('opcache_invalidate')) {
    $dir = dirname(__DIR__) . '/bootstrap/cache';
    $files = glob($dir . '/*.php');
    if ($files) {
        foreach ($files as $f) {
            opcache_invalidate($f, true);
        }
    }
    echo ' Invalidated ' . count($files ?? []) . ' cached files.';
}
