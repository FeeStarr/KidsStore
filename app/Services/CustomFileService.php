<?php

namespace App\Services;

use App\Models\CustomOrder;
use App\Models\CustomOrderFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class CustomFileService
{
    public function upload(CustomOrder $order, UploadedFile $file, string $type, int $userId): CustomOrderFile
    {
        $filename = uniqid('', true) . '.' . $file->getClientOriginalExtension();

        $path = $file->storeAs(
            "custom-orders/{$order->id}",
            $filename,
            'custom_orders'
        );

        return CustomOrderFile::create([
            'custom_order_id' => $order->id,
            'file_type' => $type,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
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

        if (!file_exists($fullPath)) {
            return null;
        }

        return response()->file($fullPath, [
            'Content-Type' => $file->mime_type,
            'Content-Disposition' => 'inline; filename="' . ($file->original_filename ?? basename($file->file_path)) . '"',
        ]);
    }

    public function getMaxFileSizeMb(): int
    {
        return (int) \App\Models\Setting::get('custom_order_max_file_size_mb', 10);
    }

    public function getMaxFiles(): int
    {
        return (int) \App\Models\Setting::get('custom_order_max_files', 5);
    }

    public function getAllowedMimeTypes(): array
    {
        return ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
    }
}
