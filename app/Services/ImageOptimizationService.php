<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ImageOptimizationService
{
    private int $threshold;
    private int $quality;
    private int $webpQuality;
    private int $maxWidth;
    private array $srcsetWidths;
    private ?string $convertPath = null;
    private bool $shellChecked = false;

    public function __construct()
    {
        $this->threshold    = (int) config('image-optimization.compress_threshold', 204800);
        $this->quality      = (int) config('image-optimization.quality', 82);
        $this->webpQuality  = (int) config('image-optimization.webp_quality', 80);
        $this->maxWidth     = (int) config('image-optimization.max_width', 1200);
        $this->srcsetWidths = config('image-optimization.srcset_widths', [400, 800]);
    }

    private function getConvertPath(): ?string
    {
        if ($this->shellChecked) {
            return $this->convertPath;
        }
        $this->shellChecked = true;
        if (function_exists('shell_exec')) {
            $path = @shell_exec('which convert 2>/dev/null');
            $this->convertPath = trim($path ?: '/usr/local/bin/convert');
        }
        return $this->convertPath;
    }

    private function shellAvailable(): bool
    {
        return function_exists('shell_exec') && $this->getConvertPath() !== null;
    }

    public function optimizeUploadedFile(UploadedFile $file, string $disk): array
    {
        $size = $file->getSize();
        if ($size < $this->threshold || ! $this->isSupported($file->getMimeType())) {
            return [
                'path'      => $file->store(self::dirFromPath($file->getClientOriginalName()), $disk),
                'webp_path' => null,
                'srcset'    => [],
            ];
        }

        $path = $file->store(self::dirFromPath($file->getClientOriginalName()), $disk);
        $fullPath = Storage::disk($disk)->path($path);

        $info = @getimagesize($fullPath);
        $originalWidth = $info ? $info[0] : 0;

        $targetWidth = min($originalWidth, $this->maxWidth);
        $this->convertResize($fullPath, $fullPath, $targetWidth);

        $webpFull = $this->webpPath($fullPath);
        $this->convertToWebp($fullPath, $webpFull, $targetWidth);
        $webpRel = ltrim(str_replace(Storage::disk($disk)->path(''), '', $webpFull), '/');

        $srcset = [];
        foreach ($this->srcsetWidths as $w) {
            if ($originalWidth <= $w) {
                continue;
            }
            $thumbPath = $this->srcsetPath($fullPath, $w);
            $this->convertResize($fullPath, $thumbPath, $w);
            $thumbWebp = $this->webpPath($thumbPath);
            $this->convertToWebp($fullPath, $thumbWebp, $w);
            $srcset[$w] = ltrim(str_replace(Storage::disk($disk)->path(''), '', $thumbPath), '/');
        }

        return [
            'path'      => $path,
            'webp_path' => $webpRel,
            'srcset'    => $srcset,
        ];
    }

    public function optimizeExisting(string $relativePath, string $disk): bool
    {
        $fullPath = Storage::disk($disk)->path($relativePath);
        if (! file_exists($fullPath)) {
            return false;
        }

        $size = filesize($fullPath);
        $mime = mime_content_type($fullPath);
        if ($size < $this->threshold || ! $this->isSupported($mime)) {
            return false;
        }

        $info = @getimagesize($fullPath);
        if ($info === false) {
            return false;
        }
        $originalWidth = $info[0];
        $targetWidth = min($originalWidth, $this->maxWidth);

        $saved = $this->convertResize($fullPath, $fullPath, $targetWidth);
        if (! $saved) {
            return false;
        }

        $webpFull = $this->webpPath($fullPath);
        $this->convertToWebp($fullPath, $webpFull, $targetWidth);

        foreach ($this->srcsetWidths as $w) {
            if ($originalWidth <= $w) {
                continue;
            }
            $thumbPath = $this->srcsetPath($fullPath, $w);
            $this->convertResize($fullPath, $thumbPath, $w);
            $thumbWebp = $this->webpPath($thumbPath);
            $this->convertToWebp($fullPath, $thumbWebp, $w);
        }

        return true;
    }

    public function getStats(string $disk): array
    {
        $images = \App\Models\ProductImage::all();
        $totalSize = 0;
        $compressed = 0;
        $hasWebp = 0;
        $hasSrcset = 0;
        $details = [];

        foreach ($images as $img) {
            $fullPath = Storage::disk($disk)->path($img->path);
            if (! file_exists($fullPath)) {
                continue;
            }

            $size = filesize($fullPath);
            $totalSize += $size;

            $webp = $this->webpPath($fullPath);
            $webpExists = file_exists($webp);
            $webpSize = $webpExists ? filesize($webp) : 0;

            $thumb400 = $this->srcsetPath($fullPath, 400);
            $thumb800 = $this->srcsetPath($fullPath, 800);
            $thumb400Exists = file_exists($thumb400);
            $thumb800Exists = file_exists($thumb800);

            if ($webpExists) {
                $hasWebp++;
            }
            if ($thumb400Exists || $thumb800Exists) {
                $hasSrcset++;
            }
            if ($size < $this->threshold) {
                $compressed++;
            }

            $details[] = [
                'path'      => $img->path,
                'size'      => $size,
                'webp_size' => $webpSize,
                'has_webp'  => $webpExists,
                'has_400'   => $thumb400Exists,
                'has_800'   => $thumb800Exists,
            ];
        }

        return [
            'total'      => $images->count(),
            'total_size' => $totalSize,
            'compressed' => $compressed,
            'has_webp'   => $hasWebp,
            'has_srcset' => $hasSrcset,
            'details'    => $details,
        ];
    }

    private function convertResize(string $sourcePath, string $destPath, int $targetWidth): bool
    {
        $convertPath = $this->getConvertPath();
        if (! $convertPath || ! function_exists('exec')) {
            return false;
        }

        $escaped = escapeshellarg($sourcePath);
        $destEsc = escapeshellarg($destPath);
        $cmd = "{$convertPath} {$escaped} -resize {$targetWidth}x -strip -quality {$this->quality} {$destEsc} 2>&1";

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            error_log("[ImageOpt] convert resize failed: {$sourcePath} - " . implode(' ', $output));

            return false;
        }

        return true;
    }

    private function convertToWebp(string $sourcePath, string $destPath, int $targetWidth): bool
    {
        $convertPath = $this->getConvertPath();
        if (! $convertPath || ! function_exists('exec')) {
            return false;
        }

        $escaped = escapeshellarg($sourcePath);
        $destEsc = escapeshellarg($destPath);
        $cmd = "{$convertPath} {$escaped} -resize {$targetWidth}x -strip -quality {$this->webpQuality} {$destEsc} 2>&1";

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            error_log("[ImageOpt] convert webp failed: {$sourcePath} - " . implode(' ', $output));

            return false;
        }

        return true;
    }

    private function isSupported(?string $mime): bool
    {
        return in_array($mime, config('image-optimization.supported_mimes', []), true);
    }

    private static function dirFromPath(string $filename): string
    {
        return dirname('x/'.$filename) === '.' ? '' : dirname($filename);
    }

    private function webpPath(string $fullPath): string
    {
        $info = pathinfo($fullPath);
        return $info['dirname'].'/'.$info['filename'].'.webp';
    }

    private function srcsetPath(string $fullPath, int $width): string
    {
        $info = pathinfo($fullPath);
        return $info['dirname'].'/'.$info['filename'].'-'.$width.'.jpg';
    }
}
