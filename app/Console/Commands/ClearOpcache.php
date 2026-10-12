<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ClearOpcache extends Command
{
    protected $signature = 'opcache:clear';

    protected $description = 'Reset the PHP opcode cache and drop Laravel\'s compiled bootstrap files.';

    public function handle(): int
    {
        // Drops bootstrap/cache/{packages,services}.php along with the config, route,
        // view and event caches. This is the work the old public/clear-opcache.php did,
        // without an endpoint sitting in the web root.
        $this->callSilent('optimize:clear');
        $this->line('  Compiled bootstrap files and application caches cleared.');

        if (function_exists('opcache_reset')) {
            $this->line(opcache_reset()
                ? '  OPcache reset.'
                : '  OPcache reset returned false - check opcache.enable and opcache.enable_cli.');
        } else {
            // The extension is frequently absent from the CLI SAPI, so this is normal
            // on local machines and is not a failure.
            $this->line('  OPcache not enabled for this SAPI - nothing to reset.');
        }

        $this->info('Done. Remember: if opcache.validate_timestamps is off, PHP-FPM may still need a reload.');

        return self::SUCCESS;
    }
}
