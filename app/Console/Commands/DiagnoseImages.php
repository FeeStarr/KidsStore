<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class DiagnoseImages extends Command
{
    protected $signature = 'images:diagnose {--file=} {--count=3}';

    protected $description = 'Check actual file formats and available tools on the server';

    public function handle(): int
    {
        $disk = 'public';
        $this->info('=== Server Image Diagnostics ===');
        $this->newLine();

        // Check available tools
        $this->info('Available tools:');
        foreach (['cwebp', 'dwebp', 'convert', 'identify', 'php'] as $tool) {
            $path = trim(shell_exec("which {$tool} 2>/dev/null") ?: '');
            $this->line("  {$tool}: " . ($path ? "YES ({$path})" : 'NO'));
        }
        $this->newLine();

        // Check GD info
        $this->info('GD Info:');
        $gdInfo = gd_info();
        foreach (['JPEG Support', 'PNG Support', 'WebP Support', 'FreeType Support'] as $key) {
            $this->line("  {$key}: " . ($gdInfo[$key] ?? 'unknown') . ' (v' . ($gdInfo['GD Version'] ?? '?') . ')');
        }
        $this->newLine();

        // Check Imagick
        $this->info('Imagick: ' . (class_exists('Imagick') ? 'YES (v' . Imagick::VERSION . ')' : 'NO'));
        $this->newLine();

        // Check PHP memory
        $this->info('PHP Memory: ' . ini_get('memory_limit'));
        $this->newLine();

        // Check file contents
        $count = (int) $this->option('count');
        $images = \App\Models\ProductImage::limit($count)->get();

        if ($file = $this->option('file')) {
            $images = collect([['path' => $file]]);
        }

        $this->info("Checking {$images->count()} files:");
        $this->newLine();

        foreach ($images as $img) {
            $path = $img['path'] ?? $img->path;
            $fullPath = Storage::disk($disk)->path($path);

            if (! file_exists($fullPath)) {
                $this->error("  NOT FOUND: {$path}");
                continue;
            }

            $size = filesize($fullPath);
            $data = file_get_contents($fullPath);
            $magic = substr($data, 0, 16);
            $hex = bin2hex($magic);
            $first8 = substr($hex, 0, 8);

            $this->line("  File: {$path}");
            $this->line("  Size: " . number_format($size) . " bytes");
            $this->line("  Extension: " . pathinfo($path, PATHINFO_EXTENSION));
            $this->line("  MIME (mime_content_type): " . mime_content_type($fullPath));
            $this->line("  getimagesize type: " . (getimagesize($fullPath)[2] ?? 'FAILED'));

            // Detect actual format from magic bytes
            $actualFormat = match(true) {
                str_starts_with($hex, '89504e47') => 'PNG',
                str_starts_with($hex, 'ffd8ff') => 'JPEG',
                str_starts_with($hex, '52494646') && str_contains($hex, '57454250') => 'WebP',
                str_starts_with($hex, '47494638') => 'GIF',
                default => "UNKNOWN (first bytes: {$first8})",
            };
            $this->line("  ACTUAL FORMAT: {$actualFormat}");

            // Try CLI detection
            $cliResult = trim(shell_exec("file {$fullPath} 2>/dev/null") ?: '');
            if ($cliResult) {
                $this->line("  file cmd: {$cliResult}");
            }

            $this->newLine();
        }

        return self::SUCCESS;
    }
}
