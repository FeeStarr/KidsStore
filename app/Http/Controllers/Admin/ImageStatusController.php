<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ImageOptimizationService;
use Illuminate\Support\Facades\Storage;

class ImageStatusController extends Controller
{
    public function index(ImageOptimizationService $optimizer)
    {
        $stats = $optimizer->getStats('public');

        return view('admin.image-status.index', compact('stats'));
    }
}
