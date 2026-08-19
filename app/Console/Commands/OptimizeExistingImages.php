<?php

namespace App\Console\Commands;

use App\Models\Deal;
use App\Models\ProductImage;
use App\Services\ImageOptimizationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class OptimizeExistingImages extends Command
{
    protected $signature = 'images:optimize {--disk=public} {--dry-run}';

    protected $description = 'Batch-compress existing product and deal images, create WebP and srcset thumbnails';

    public function handle(ImageOptimizationService $optimizer): int
    {
        $disk = $this->option('disk');
        $dryRun = $this->option('dry-run');

        $this->info("Optimizing images on disk: {$disk}");
        $this->newLine();

        // ── Product images ───────────────────────────────────────────────
        $productImages = ProductImage::all();
        $processed = 0;
        $skipped = 0;
        $failed = 0;

        $this->info("Processing {$productImages->count()} product images...");
        $bar = $this->output->createProgressBar($productImages->count());
        $bar->start();

        foreach ($productImages as $img) {
            if ($dryRun) {
                $size = Storage::disk($disk)->exists($img->path)
                    ? Storage::disk($disk)->size($img->path)
                    : 0;
                if ($size >= config('image-optimization.compress_threshold', 204800)) {
                    $this->newLine();
                    $this->line("  Would optimize: {$img->path} (".number_format($size).' bytes)');
                    $processed++;
                } else {
                    $skipped++;
                }
            } else {
                try {
                    if ($optimizer->optimizeExisting($img->path, $disk)) {
                        $processed++;
                    } else {
                        $skipped++;
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    $this->newLine();
                    $this->error("  Failed: {$img->path} - {$e->getMessage()}");
                }
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);

        // ── Deal images ──────────────────────────────────────────────────
        $deals = Deal::whereNotNull('banner_image')
            ->orWhereNotNull('thumbnail_image')
            ->get();

        $this->info("Processing {$deals->count()} deal images...");
        $bar = $this->output->createProgressBar($deals->count());
        $bar->start();

        foreach ($deals as $deal) {
            foreach (['banner_image', 'thumbnail_image'] as $field) {
                $path = $deal->{$field};
                if (! $path) {
                    continue;
                }

                if ($dryRun) {
                    $size = Storage::disk($disk)->exists($path)
                        ? Storage::disk($disk)->size($path)
                        : 0;
                    if ($size >= config('image-optimization.compress_threshold', 204800)) {
                        $this->newLine();
                        $this->line("  Would optimize: {$path} (".number_format($size).' bytes)');
                        $processed++;
                    } else {
                        $skipped++;
                    }
                } else {
                    try {
                        if ($optimizer->optimizeExisting($path, $disk)) {
                            $processed++;
                        } else {
                            $skipped++;
                        }
                    } catch (\Throwable $e) {
                        $failed++;
                        $this->newLine();
                        $this->error("  Failed: {$path} - {$e->getMessage()}");
                    }
                }
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);

        // ── Summary ──────────────────────────────────────────────────────
        $this->info('Done!');
        $this->table(['Metric', 'Count'], [
            ['Optimized', $processed],
            ['Skipped (below threshold or unsupported)', $skipped],
            ['Failed', $failed],
        ]);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
