<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateIcons extends Command
{
    protected $signature = 'icons:generate {source=public/images/logo.png}';
    protected $description = 'Generate PWA icons and favicon from source image';

    public function handle(): int
    {
        $source = $this->argument('source');
        if (! file_exists($source)) {
            $this->error("Source image not found: {$source}");
            return 1;
        }

        $sizes = [
            'public/icons/icon-192.png' => 192,
            'public/icons/icon-512.png' => 512,
            'public/favicon.png'        => 64,
        ];

        foreach ($sizes as $dest => $size) {
            $img = imagecreatetruecolor($size, $size);
            $sourceImg = @imagecreatefromstring(file_get_contents($source));
            if (! $sourceImg) {
                $this->error("Failed to load source image.");
                return 1;
            }

            imagesavealpha($img, true);
            $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
            imagefilledrectangle($img, 0, 0, $size, $size, $transparent);

            $srcW = imagesx($sourceImg);
            $srcH = imagesy($sourceImg);
            $minDim = min($srcW, $srcH);
            $srcX = (int)(($srcW - $minDim) / 2);
            $srcY = (int)(($srcH - $minDim) / 2);

            imagecopyresampled($img, $sourceImg, 0, 0, $srcX, $srcY, $size, $size, $minDim, $minDim);
            imagepng($img, $dest);
            imagedestroy($img);
            imagedestroy($sourceImg);

            $this->info("Generated: {$dest} ({$size}x{$size})");
        }

        $this->info("All icons generated successfully.");
        return 0;
    }
}
