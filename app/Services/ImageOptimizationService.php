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

    public function __construct()
    {
        $this->threshold    = (int) config('image-optimization.compress_threshold', 204800);
        $this->quality      = (int) config('image-optimization.quality', 82);
        $this->webpQuality  = (int) config('image-optimization.webp_quality', 80);
        $this->maxWidth     = (int) config('image-optimization.max_width', 1200);
        $this->srcsetWidths = config('image-optimization.srcset_widths', [400, 800]);
    }

    /**
     * Optimize an UploadedFile: compress, create WebP, generate srcset thumbnails.
     *
     * @return array{path: string, webp_path: string|null, srcset: array<int,string>}
     */
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

        // WebP version
        $webpFull = $this->webpPath($fullPath);
        $this->saveWebp($file->getRealPath(), $webpFull, $targetWidth);
        $webpRel = ltrim(str_replace(Storage::disk($disk)->path(''), '', $webpFull), '/');

        // Srcset thumbnails
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

    /**
     * Optimize an existing file on disk (for batch processing).
     */
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

        // Save compressed version (overwrites original directly)
        $saved = $this->saveResized($fullPath, $fullPath, $targetWidth, $isPng);
        if (! $saved) {
            return false;
        }

        // WebP version
        $webpFull = $this->webpPath($fullPath);
        $this->saveWebp($fullPath, $webpFull, $targetWidth);

        // Srcset thumbnails
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

    /**
     * Load an image from a file path into a GD resource.
     */
    private function loadSource(string $path)
    {
        $info = @getimagesize($path);
        if ($info === false) {
            error_log("[ImageOpt] getimagesize failed: {$path}");

            return null;
        }

        // Use imagecreatefromstring instead of type-specific functions
        // because imagecreatefrompng fails on some shared hosting GD builds
        $data = @file_get_contents($path);
        if ($data === false) {
            error_log("[ImageOpt] file_get_contents failed: {$path}");

            return null;
        }

        $result = @imagecreatefromstring($data);

        if (! is_resource($result)) {
            error_log("[ImageOpt] imagecreatefromstring failed: {$path} (type={$info[2]}, size=" . strlen($data) . " bytes)");
        }

        return $result;
    }

    /**
     * Resize and save as JPEG (or PNG).
     */
    private function saveResized(string $sourcePath, string $destPath, int $targetWidth, bool $isPng): bool
    {
        $src = $this->loadSource($sourcePath);
        if (! is_resource($src)) {
            $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
            error_log("[ImageOpt] Failed to load: {$sourcePath} (ext={$ext}, gd=" . (function_exists('imagecreatefrom' . $ext) ? 'yes' : 'no') . ")");

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

        if ($isPng) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $targetWidth, $targetHeight, $transparent);
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetWidth, $targetHeight, $origW, $origH);

        if ($isPng) {
            imagepng($dst, $destPath, 6);
        } else {
            imagejpeg($dst, $destPath, $this->quality);
        }

        imagedestroy($src);
        imagedestroy($dst);

        return true;
    }

    /**
     * Save a WebP version of the source image.
     */
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

        return $info['dirname'].'/'.$info['filename'].'-'.$width.'.'.($info['extension'] ?: 'jpg');
    }
}
