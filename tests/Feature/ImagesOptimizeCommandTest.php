<?php

namespace Tests\Feature;

use App\Models\CustomCreation;
use App\Services\ImageOptimizationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImagesOptimizeCommandTest extends TestCase
{
    public function test_dry_run_includes_custom_creation_images(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('custom-creations/big.jpg', str_repeat('x', 300000));

        CustomCreation::create([
            'title' => 'Big Creation',
            'image_path' => 'custom-creations/big.jpg',
            'is_active' => true,
        ]);

        Artisan::call('images:optimize', ['--dry-run' => true, '--disk' => 'public']);

        $output = Artisan::output();
        $this->assertStringContainsString('Processing 1 custom creation images', $output);
        $this->assertStringContainsString('Would optimize: custom-creations/big.jpg', $output);
    }

    public function test_dry_run_skips_small_custom_creation_images(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('custom-creations/small.jpg', str_repeat('x', 1024));

        CustomCreation::create([
            'title' => 'Small Creation',
            'image_path' => 'custom-creations/small.jpg',
            'is_active' => true,
        ]);

        Artisan::call('images:optimize', ['--dry-run' => true, '--disk' => 'public']);

        $this->assertStringNotContainsString('Would optimize: custom-creations/small.jpg', Artisan::output());
    }

    public function test_stats_include_custom_creation_images(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('custom-creations/stats.jpg', str_repeat('x', 300000));

        CustomCreation::create([
            'title' => 'Stats Creation',
            'image_path' => 'custom-creations/stats.jpg',
            'is_active' => true,
        ]);

        $stats = app(ImageOptimizationService::class)->getStats('public');

        $creationDetail = collect($stats['details'])->firstWhere('path', 'custom-creations/stats.jpg');
        $this->assertNotNull($creationDetail, 'Custom creation image missing from stats');
        $this->assertSame('Custom Creation', $creationDetail['source']);
        $this->assertGreaterThanOrEqual(1, $stats['total']);
    }
}
