<?php

namespace App\Services;

use App\Models\CustomOrder;
use App\Models\CustomOrderFile;
use App\Models\CustomOrderQcCheck;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class CustomFileService
{
    /** Max size for message attachments (images and PDFs), in MB. */
    public const MESSAGE_MAX_MB = 3;

    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    public function __construct(
        private ImageOptimizationService $optimizer,
    ) {}

    public function messageMaxKilobytes(): int
    {
        return self::MESSAGE_MAX_MB * 1024;
    }

    public function upload(CustomOrder $order, UploadedFile $file, string $type, int $userId, bool $optimizeImages = false): CustomOrderFile
    {
        $ext = strtolower($file->getClientOriginalExtension());

        if (! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException('File type not allowed.');
        }

        // Validate actual MIME type using finfo (server-side), not client-reported
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($file->getRealPath());
        if (! in_array($realMime, self::ALLOWED_MIME_TYPES, true)) {
            throw new \InvalidArgumentException('File content does not match allowed types.');
        }

        // Cryptographically random filename
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;

        $path = $file->storeAs(
            "custom-orders/{$order->id}",
            $filename,
            'custom_orders'
        );

        if ($optimizeImages && str_starts_with($realMime, 'image/')) {
            $this->optimizer->optimizeExisting($path, 'custom_orders');
        }

        return CustomOrderFile::create([
            'custom_order_id' => $order->id,
            'file_type' => $type,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $realMime,
            'file_size' => $file->getSize(),
            'uploaded_by' => $userId,
            'created_at' => now(),
        ]);
    }

    public function delete(CustomOrderFile $file): bool
    {
        Storage::disk('custom_orders')->delete($file->file_path);
        return $file->delete();
    }

    public function serve(CustomOrderFile $file): ?Response
    {
        $fullPath = Storage::disk('custom_orders')->path($file->file_path);

        // Path containment check - prevent path traversal even if DB is compromised
        $basePath = realpath(Storage::disk('custom_orders')->path(''));
        if ($basePath === false || strpos(realpath($fullPath), $basePath) !== 0) {
            abort(403, 'Invalid file path.');
        }

        if (!file_exists($fullPath)) {
            return null;
        }

        // Sanitize filename for Content-Disposition header
        $safeName = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $file->original_filename ?? basename($file->file_path));

        return response()->file($fullPath, [
            'Content-Type' => $file->mime_type,
            'Content-Disposition' => 'attachment; filename="' . $safeName . '"',
        ]);
    }

    public function getMaxFileSizeMb(): int
    {
        return (int) \App\Models\Setting::get('custom_order_max_file_size_mb', 10);
    }

    public function storeQcPhoto(CustomOrder $order, CustomOrderQcCheck $check, UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');

        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new \InvalidArgumentException('File type not allowed.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($file->getRealPath());
        if (! in_array($realMime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new \InvalidArgumentException('File content does not match allowed types.');
        }

        if ($check->photo_path) {
            Storage::disk('custom_orders')->delete($check->photo_path);
        }

        $filename = 'qc-' . $check->id . '-' . bin2hex(random_bytes(8)) . '.' . $ext;

        $path = $file->storeAs(
            "custom-orders/{$order->id}/qc",
            $filename,
            'custom_orders'
        );

        if ($path === false) {
            throw new \RuntimeException('Failed to store QC photo.');
        }

        return $path;
    }

    public function serveQcPhoto(CustomOrder $order, CustomOrderQcCheck $check): ?Response
    {
        if (! $check->photo_path) {
            return null;
        }

        $fullPath = Storage::disk('custom_orders')->path($check->photo_path);

        $basePath = realpath(Storage::disk('custom_orders')->path(''));
        if ($basePath === false || strpos(realpath($fullPath) ?: '', $basePath) !== 0) {
            abort(403, 'Invalid file path.');
        }

        if (! file_exists($fullPath)) {
            return null;
        }

        return response()->file($fullPath);
    }

    public function getMaxFiles(): int
    {
        return (int) \App\Models\Setting::get('custom_order_max_files', 5);
    }

    public function getAllowedMimeTypes(): array
    {
        return self::ALLOWED_MIME_TYPES;
    }
}
