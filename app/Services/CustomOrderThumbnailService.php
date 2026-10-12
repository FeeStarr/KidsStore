<?php

namespace App\Services;

use App\Models\CustomOrderFile;
use Illuminate\Support\Facades\Storage;

/**
 * Builds a small, cached JPEG preview of a custom order reference image so it can be
 * embedded directly in the order email.
 *
 * Reference files live on the private `custom_orders` disk with no public URL and are
 * served as `Content-Disposition: attachment` behind `auth`, so an <img src> hotlink is
 * not an option. The thumbnail is therefore attached to the mail and referenced by its
 * `cid:`, which Symfony promotes to a proper inline MIME part.
 */
class CustomOrderThumbnailService
{
    /** Name the email body references as `cid:` - must match the attachment `as` value. */
    public const CID_NAME = 'reference-thumbnail.jpg';

    /** Longest edge of the generated preview, in pixels. */
    public const MAX_EDGE = 320;

    private const JPEG_QUALITY = 72;

    private const SUPPORTED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * Whether a preview can be produced for this file. PDFs and anything missing from
     * disk are skipped so the email simply omits the image.
     */
    public function canThumbnail(CustomOrderFile $file): bool
    {
        if (! in_array($file->file_type, ['reference_image', 'colour_reference'], true)) {
            return false;
        }

        if (! in_array($file->mime_type, self::SUPPORTED_MIMES, true)) {
            return false;
        }

        // A tampered or malformed file_path makes Flysystem throw (PathTraversalDetected,
        // etc). This runs while building an email, so never let it escape.
        try {
            return Storage::disk('custom_orders')->exists($file->file_path);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Absolute path to the cached preview, generating it on first use.
     * Returns null when no preview is possible - callers must degrade gracefully.
     */
    public function thumbnailFor(CustomOrderFile $file): ?string
    {
        if (! $this->canThumbnail($file)) {
            return null;
        }

        $relative = $this->relativePathFor($file);
        $disk = Storage::disk('custom_orders');

        try {
            if ($disk->exists($relative)) {
                return $disk->path($relative);
            }
        } catch (\Throwable) {
            return null;
        }

        $source = $disk->path($file->file_path);

        if (! $this->isInsideDiskRoot($source)) {
            return null;
        }

        $thumbnail = $this->render($source);

        if ($thumbnail === null) {
            return null;
        }

        $disk->put($relative, $thumbnail);

        return $disk->path($relative);
    }

    private function relativePathFor(CustomOrderFile $file): string
    {
        return sprintf(
            'custom-orders/%d/thumbnails/email-%d.jpg',
            $file->custom_order_id,
            $file->id
        );
    }

    /**
     * Same containment check used by CustomFileService::serve() - defends against a
     * tampered file_path escaping the disk root.
     */
    private function isInsideDiskRoot(string $path): bool
    {
        $root = realpath(Storage::disk('custom_orders')->path(''));
        $real = realpath($path);

        return $root !== false && $real !== false && str_starts_with($real, $root);
    }

    /**
     * Decode, orient, downscale and re-encode as JPEG. Returns raw JPEG bytes or null.
     */
    private function render(string $source): ?string
    {
        $image = $this->load($source);

        if ($image === null) {
            return null;
        }

        $image = $this->applyOrientation($image, $this->readOrientation($source));

        $width = imagesx($image);
        $height = imagesy($image);

        $scale = min(self::MAX_EDGE / max($width, $height), 1);
        $targetW = max(1, (int) round($width * $scale));
        $targetH = max(1, (int) round($height * $scale));

        // Flatten onto white so transparent PNG/WebP sources do not turn black in JPEG.
        $canvas = imagecreatetruecolor($targetW, $targetH);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $targetW, $targetH, $white);

        imagecopyresampled(
            $canvas, $image,
            0, 0, 0, 0,
            $targetW, $targetH,
            $width, $height
        );

        ob_start();
        $ok = imagejpeg($canvas, null, self::JPEG_QUALITY);
        $bytes = ob_get_clean();

        imagedestroy($canvas);
        imagedestroy($image);

        if (! $ok || $bytes === false || $bytes === '') {
            return null;
        }

        return $bytes;
    }

    private function load(string $path): ?\GdImage
    {
        $info = @getimagesize($path);

        return match ($info[2] ?? null) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => $this->loadPng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
            default => null,
        } ?: null;
    }

    /**
     * imagecreatefrompng() keeps the alpha channel, which makes imagecopyresampled()
     * composite against an undefined backdrop. Rebuild it as truecolor first.
     */
    private function loadPng(string $path): ?\GdImage
    {
        $source = @imagecreatefrompng($path);

        if ($source === false) {
            return null;
        }

        $flat = imagecreatetruecolor(imagesx($source), imagesy($source));
        imagealphablending($flat, true);
        imagecopy($flat, $source, 0, 0, 0, 0, imagesx($source), imagesy($source));
        imagedestroy($source);

        return $flat;
    }

    /**
     * imagerotate() returns a NEW image rather than mutating in place, so the rotated
     * instance has to be threaded back through.
     */
    private function applyOrientation(\GdImage $image, int $orientation): \GdImage
    {
        $rotate = match ($orientation) {
            3, 4 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => 0,
        };

        if ($rotate !== 0) {
            $rotated = imagerotate($image, $rotate, 0);

            if ($rotated instanceof \GdImage) {
                imagedestroy($image);
                $image = $rotated;
            }
        }

        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        return $image;
    }

    /**
     * EXIF orientation without relying on the (commonly absent) exif extension.
     * Reference images are usually phone photos, and GD ignores orientation on decode,
     * so without this a portrait photo renders sideways in the email.
     */
    private function readOrientation(string $path): int
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false || fread($handle, 2) !== "\xFF\xD8") {
            $handle && fclose($handle);

            return 1;
        }

        try {
            while (! feof($handle)) {
                $marker = fread($handle, 2);

                if ($marker === false || strlen($marker) < 2) {
                    return 1;
                }

                $code = ord($marker[1]);

                if ($code === 0xFF) {          // fill byte, keep scanning
                    fseek($handle, -1, SEEK_CUR);

                    continue;
                }

                if ($code === 0xD8 || $code === 0x01 || ($code >= 0xD0 && $code <= 0xD7)) {
                    continue;
                }

                if ($code === 0xDA || $code === 0xD9) {   // start of scan / end of image
                    return 1;
                }

                $lengthBytes = fread($handle, 2);

                if ($lengthBytes === false || strlen($lengthBytes) < 2) {
                    return 1;
                }

                $length = unpack('n', $lengthBytes)[1];

                if ($length < 2) {
                    return 1;
                }

                $payload = $code === 0xE1 ? fread($handle, $length - 2) : null;

                if ($payload === null) {
                    fseek($handle, $length - 2, SEEK_CUR);

                    continue;
                }

                if (str_starts_with($payload, "Exif\x00\x00")) {
                    return $this->orientationFromTiff(substr($payload, 6));
                }
            }
        } finally {
            fclose($handle);
        }

        return 1;
    }

    private function orientationFromTiff(string $tiff): int
    {
        // TIFF header: byte order (2) | magic 42 as a SHORT (2) | first-IFD offset (4).
        if (strlen($tiff) < 8) {
            return 1;
        }

        $shortFormat = match (substr($tiff, 0, 2)) {
            'II' => 'v',      // little endian
            'MM' => 'n',      // big endian
            default => null,
        };

        if ($shortFormat === null) {
            return 1;
        }

        $longFormat = $shortFormat === 'v' ? 'V' : 'N';

        if (unpack($shortFormat, substr($tiff, 2, 2))[1] !== 42) {
            return 1;
        }

        $ifd = unpack($longFormat, substr($tiff, 4, 4))[1];

        if ($ifd + 2 > strlen($tiff)) {
            return 1;
        }

        $entries = unpack($shortFormat, substr($tiff, $ifd, 2))[1];

        for ($i = 0; $i < $entries; $i++) {
            $entry = $ifd + 2 + ($i * 12);

            if ($entry + 12 > strlen($tiff)) {
                return 1;
            }

            if (unpack($shortFormat, substr($tiff, $entry, 2))[1] !== 0x0112) {
                continue;
            }

            $value = unpack($shortFormat, substr($tiff, $entry + 8, 2))[1];

            return ($value >= 1 && $value <= 8) ? $value : 1;
        }

        return 1;
    }
}
