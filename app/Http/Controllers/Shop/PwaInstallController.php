<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PwaInstallController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        DB::table('pwa_installs')->insert([
            'user_id'    => $request->user()?->id,
            'platform'   => $request->input('platform'),
            'browser'    => $request->input('browser'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }
}
