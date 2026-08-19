<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

class ImageOptimizationService
{
    private int $threshold;

    private int $quality;

    private int $webpQuality;

    private int $maxWidth;

    private array $srcsetWidths;

    private ImageManager $manager;

    public function __construct()
    {
        $this->threshold = (int) config('image-optimization.compress_threshold', 204800);
        $this->quality = (int) config('image-optimization.quality', 82);
        $this->webpQuality = (int) config('image-optimization.webp_quality', 80);
        $this->maxWidth = (int) config('image-optimization.max_width', 1200);
        $this->srcsetWidths = config('image-optimization.srcset_widths', [400, 800]);

        $this->manager = ImageManager::gd();
    }

    /**
     * Optimize an UploadedFile in-place: compress, create WebP, generate srcset thumbnails.
     *
     * @return array{path: string, webp_path: string|null, srcset: array<int,string>}
     */
    public function optimizeUploadedFile(UploadedFile $file, string $disk): array
    {
        $size = $file->getSize();
        if ($size < $this->threshold || ! $this->isSupported($file->getMimeType())) {
            return [
                'path' => $file->store(self::dirFromPath($file->getClientOriginalName()), $disk),
                'webp_path' => null,
                'srcset' => [],
            ];
        }

        $dir = self::dirFromPath($file->getClientOriginalName());
        $ext = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        $isPng = $ext === 'png';

        $img = $this->manager->read($file->getRealPath());
        $originalWidth = $img->width();

        if ($originalWidth > $this->maxWidth) {
            $img->resize($this->maxWidth, null, fn ($c) => $c->aspectRatio()->upsize());
        }

        $path = $file->store($dir, $disk);
        $fullPath = Storage::disk($disk)->path($path);

        if ($isPng) {
            $img->toPng()->save($fullPath);
        } else {
            $img->toJpeg($this->quality)->save($fullPath);
        }

        // WebP version
        $webpFull = $this->webpPath($fullPath);
        $webpImg = $this->manager->read($file->getRealPath());
        if ($originalWidth > $this->maxWidth) {
            $webpImg->resize($this->maxWidth, null, fn ($c) => $c->aspectRatio()->upsize());
        }
        $webpImg->toWebp($this->webpQuality)->save($webpFull);
        $webpRel = ltrim(str_replace(Storage::disk($disk)->path(''), '', $webpFull), '/');

        // Srcset thumbnails
        $srcset = [];
        foreach ($this->srcsetWidths as $w) {
            if ($originalWidth <= $w) {
                continue;
            }
            $thumbPath = $this->srcsetPath($fullPath, $w);
            $thumb = $this->manager->read($file->getRealPath());
            $thumb->resize($w, null, fn ($c) => $c->aspectRatio()->upsize());
            $thumb->toJpeg($this->quality)->save($thumbPath);

            $thumbWebp = $this->webpPath($thumbPath);
            $thumbWebpImg = $this->manager->read($file->getRealPath());
            $thumbWebpImg->resize($w, null, fn ($c) => $c->aspectRatio()->upsize());
            $thumbWebpImg->toWebp($this->webpQuality)->save($thumbWebp);

            $srcset[$w] = ltrim(str_replace(Storage::disk($disk)->path(''), '', $thumbPath), '/');
        }

        return [
            'path' => $path,
            'webp_path' => $webpRel,
            'srcset' => $srcset,
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

        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $isPng = $ext === 'png';

        $img = $this->manager->read($fullPath);
        $originalWidth = $img->width();

        if ($originalWidth > $this->maxWidth) {
            $img->resize($this->maxWidth, null, fn ($c) => $c->aspectRatio()->upsize());
        }

        if ($isPng) {
            $img->toPng()->save($fullPath);
        } else {
            $img->toJpeg($this->quality)->save($fullPath);
        }

        // WebP version
        $webpFull = $this->webpPath($fullPath);
        $webpImg = $this->manager->read($fullPath);
        if ($originalWidth > $this->maxWidth) {
            $webpImg->resize($this->maxWidth, null, fn ($c) => $c->aspectRatio()->upsize());
        }
        $webpImg->toWebp($this->webpQuality)->save($webpFull);

        // Srcset thumbnails
        foreach ($this->srcsetWidths as $w) {
            if ($originalWidth <= $w) {
                continue;
            }
            $thumbPath = $this->srcsetPath($fullPath, $w);
            $thumb = $this->manager->read($fullPath);
            $thumb->resize($w, null, fn ($c) => $c->aspectRatio()->upsize());
            $thumb->toJpeg($this->quality)->save($thumbPath);

            $thumbWebp = $this->webpPath($thumbPath);
            $thumbWebpImg = $this->manager->read($fullPath);
            $thumbWebpImg->resize($w, null, fn ($c) => $c->aspectRatio()->upsize());
            $thumbWebpImg->toWebp($this->webpQuality)->save($thumbWebp);
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

        return $info['dirname'].'/'.$info['filename'].'-'.$width.'.'.($info['extension'] ?: 'jpg');
    }
}
