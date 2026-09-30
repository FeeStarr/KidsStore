<?php

namespace App\Console\Commands;

use App\Models\CustomCreation;
use App\Models\Deal;
use App\Models\ProductImage;
use App\Services\ImageOptimizationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class OptimizeExistingImages extends Command
{
    protected $signature = 'images:optimize {--disk=public} {--dry-run}';

    protected $description = 'Batch-compress existing product, deal and custom creation images, create WebP and srcset thumbnails';

    public function handle(ImageOptimizationService $optimizer): int
    {
        $disk = $this->option('disk');
        $dryRun = $this->option('dry-run');

        $this->info("Optimizing images on disk: {$disk}");
        $this->newLine();

        $processed = 0;
        $skipped = 0;
        $alreadyOptimized = 0;
        $failed = 0;

        $sources = [
            'product images' => ProductImage::pluck('path'),
            'deal images' => Deal::whereNotNull('banner_image')->orWhereNotNull('thumbnail_image')->get()
                ->flatMap(fn ($deal) => [$deal->banner_image, $deal->thumbnail_image])
                ->filter()->unique()->values(),
            'custom creation images' => CustomCreation::whereNotNull('image_path')->pluck('image_path'),
        ];

        foreach ($sources as $label => $paths) {
            $this->info("Processing {$paths->count()} {$label}...");
            $bar = $this->output->createProgressBar($paths->count());
            $bar->start();

            foreach ($paths as $path) {
                [$state, $error] = $this->optimizePath($optimizer, $path, $disk, $dryRun);

                match ($state) {
                    'optimized' => $processed++,
                    'already' => $alreadyOptimized++,
                    'skipped' => $skipped++,
                    'failed' => $failed++,
                };

                if ($state === 'failed') {
                    $this->newLine();
                    $this->error("  Failed: {$path} - {$error}");
                }
                $bar->advance();
            }
            $bar->finish();
            $this->newLine(2);
        }

        // ── Summary ──────────────────────────────────────────────────────
        $this->info('Done!');
        $this->table(['Metric', 'Count'], [
            ['Optimized', $processed],
            ['Already optimized (skipped)', $alreadyOptimized],
            ['Skipped (below threshold or unsupported)', $skipped],
            ['Failed', $failed],
        ]);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string} [state, error message]
     */
    private function optimizePath(ImageOptimizationService $optimizer, string $path, string $disk, bool $dryRun): array
    {
        if ($dryRun) {
            $size = Storage::disk($disk)->exists($path)
                ? Storage::disk($disk)->size($path)
                : 0;
            $webpPath = $optimizer->webpPathFor($path, $disk);
            if ($webpPath && file_exists($webpPath)) {
                return ['already', ''];
            }
            if ($size >= config('image-optimization.compress_threshold', 204800)) {
                $this->newLine();
                $this->line("  Would optimize: {$path} (".number_format($size).' bytes)');
                return ['optimized', ''];
            }
            return ['skipped', ''];
        }

        try {
            if ($optimizer->optimizeExisting($path, $disk)) {
                return ['optimized', ''];
            }
            $webpPath = $optimizer->webpPathFor($path, $disk);
            if ($webpPath && file_exists($webpPath)) {
                return ['already', ''];
            }
            return ['skipped', ''];
        } catch (\Throwable $e) {
            return ['failed', $e->getMessage()];
        }
    }
}
