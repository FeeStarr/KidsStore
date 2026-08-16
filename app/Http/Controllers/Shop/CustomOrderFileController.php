<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\CustomOrder;
use App\Models\CustomOrderFile;
use App\Services\CustomFileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CustomOrderFileController extends Controller
{
    public function __construct(
        private CustomFileService $fileService,
    ) {}

    public function show(CustomOrder $customOrder, CustomOrderFile $file): Response
    {
        abort_unless($customOrder->user_id === Auth::id(), 403);
        abort_unless($file->custom_order_id === $customOrder->id, 404);

        $response = $this->fileService->serve($file);

        if (!$response) {
            abort(404, 'File not found.');
        }

        return $response;
    }
}
