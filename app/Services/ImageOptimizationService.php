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
    private bool $hasImagick;

    public function __construct()
    {
        $this->threshold    = (int) config('image-optimization.compress_threshold', 204800);
        $this->quality      = (int) config('image-optimization.quality', 82);
        $this->webpQuality  = (int) config('image-optimization.webp_quality', 80);
        $this->maxWidth     = (int) config('image-optimization.max_width', 1200);
        $this->srcsetWidths = config('image-optimization.srcset_widths', [400, 800]);
        $this->hasImagick   = class_exists('Imagick');
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

        $ext = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        $isPng = $ext === 'png';

        $info = @getimagesize($file->getRealPath());
        if ($info === false) {
            return [
                'path'      => $file->store(self::dirFromPath($file->getClientOriginalName()), $disk),
                'webp_path' => null,
                'srcset'    => [],
            ];
        }
        $originalWidth = $info[0];

        $path = $file->store(self::dirFromPath($file->getClientOriginalName()), $disk);
        $fullPath = Storage::disk($disk)->path($path);

        $targetWidth = min($originalWidth, $this->maxWidth);
        $this->saveResized($file->getRealPath(), $fullPath, $targetWidth, $isPng);

        $webpFull = $this->webpPath($fullPath);
        $this->saveWebp($file->getRealPath(), $webpFull, $targetWidth);
        $webpRel = ltrim(str_replace(Storage::disk($disk)->path(''), '', $webpFull), '/');

        $srcset = [];
        foreach ($this->srcsetWidths as $w) {
            if ($originalWidth <= $w) {
                continue;
            }
            $thumbPath = $this->srcsetPath($fullPath, $w);
            $this->saveResized($file->getRealPath(), $thumbPath, $w, false);

            $thumbWebp = $this->webpPath($thumbPath);
            $this->saveWebp($file->getRealPath(), $thumbWebp, $w);

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

        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $isPng = $ext === 'png';

        $targetWidth = min($originalWidth, $this->maxWidth);

        $saved = $this->saveResized($fullPath, $fullPath, $targetWidth, $isPng);
        if (! $saved) {
            return false;
        }

        $webpFull = $this->webpPath($fullPath);
        $this->saveWebp($fullPath, $webpFull, $targetWidth);

        foreach ($this->srcsetWidths as $w) {
            if ($originalWidth <= $w) {
                continue;
            }
            $thumbPath = $this->srcsetPath($fullPath, $w);
            $this->saveResized($fullPath, $thumbPath, $w, false);

            $thumbWebp = $this->webpPath($thumbPath);
            $this->saveWebp($fullPath, $thumbWebp, $w);
        }

        return true;
    }

    public function getStats(string $disk): array
    {
        $images = \App\Models\ProductImage::all();
        $totalSize = 0;
        $optimized = 0;
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
            $thumb400Exists = file_exists($thumb400);
            $thumb800 = $this->srcsetPath($fullPath, 800);
            $thumb800Exists = file_exists($thumb800);

            if ($webpExists) {
                $hasWebp++;
            }
            if ($thumb400Exists || $thumb800Exists) {
                $hasSrcset++;
            }
            if ($size < $this->threshold) {
                $optimized++;
            }

            $details[] = [
                'path'       => $img->path,
                'size'       => $size,
                'webp_size'  => $webpSize,
                'has_webp'   => $webpExists,
                'has_400'    => $thumb400Exists,
                'has_800'    => $thumb800Exists,
            ];
        }

        return [
            'total'     => $images->count(),
            'total_size'=> $totalSize,
            'compressed'=> $optimized,
            'has_webp'  => $hasWebp,
            'has_srcset'=> $hasSrcset,
            'details'   => $details,
        ];
    }

    private function loadSource(string $path): ?\GdImage
    {
        $data = @file_get_contents($path);
        if ($data === false) {
            return null;
        }

        $src = @imagecreatefromstring($data);
        if (is_resource($src)) {
            return $src;
        }

        if ($this->hasImagick) {
            try {
                $imagick = new \Imagick();
                $imagick->readImage($path);
                $imagick = $imagick->coalesceImages();
                $canvas = $imagick->getImage();
                $w = $canvas->getImageWidth();
                $h = $canvas->getImageHeight();
                $gd = imagecreatetruecolor($w, $h);
                imagealphablending($gd, false);
                imagesavealpha($gd, true);
                $canvas->setImageFormat('bmp');
                ob_start();
                $canvas->writeImageFile(fopen('php://memory', 'rw'));
                $bmp = ob_get_clean();
                imagecopy($gd, imagecreatefromstring($bmp), 0, 0, 0, 0, $w, $h, $w, $h);
                $imagick->destroy();
                return $gd;
            } catch (\Throwable $e) {
                error_log("[ImageOpt] Imagick fallback failed: {$path} - {$e->getMessage()}");
            }
        }

        return null;
    }

    private function saveResized(string $sourcePath, string $destPath, int $targetWidth, bool $isPng): bool
    {
        $src = $this->loadSource($sourcePath);
        if (! is_resource($src)) {
            error_log("[ImageOpt] Could not load: {$sourcePath}");

            return false;
        }

        $origW = imagesx($src);
        $origH = imagesy($src);
        if ($origW === 0 || $origH === 0) {
            imagedestroy($src);
            return false;
        }

        $targetHeight = (int) round($origH * ($targetWidth / $origW));
        $dst = imagecreatetruecolor($targetWidth, $targetHeight);

        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $targetWidth, $targetHeight, $transparent);

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetWidth, $targetHeight, $origW, $origH);

        imagejpeg($dst, $destPath, $this->quality);

        imagedestroy($src);
        imagedestroy($dst);

        return true;
    }

    private function saveWebp(string $sourcePath, string $destPath, int $targetWidth): bool
    {
        if (! function_exists('imagewebp')) {
            return false;
        }

        $src = $this->loadSource($sourcePath);
        if (! is_resource($src)) {
            return false;
        }

        $origW = imagesx($src);
        $origH = imagesy($src);
        if ($origW === 0 || $origH === 0) {
            imagedestroy($src);
            return false;
        }

        $targetHeight = (int) round($origH * ($targetWidth / $origW));
        $dst = imagecreatetruecolor($targetWidth, $targetHeight);

        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $targetWidth, $targetHeight, $transparent);

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetWidth, $targetHeight, $origW, $origH);

        imagewebp($dst, $destPath, $this->webpQuality);

        imagedestroy($src);
        imagedestroy($dst);

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
