<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PwaInstallController extends Controller
{
    public function index(Request $request)
    {
        $installs = DB::table('pwa_installs')
            ->orderByDesc('created_at')
            ->paginate(25);

        $stats = [
            'total'    => DB::table('pwa_installs')->count(),
            'ios'      => DB::table('pwa_installs')->where('platform', 'ios')->count(),
            'android'  => DB::table('pwa_installs')->where('platform', 'android')->count(),
            'desktop'  => DB::table('pwa_installs')->where('platform', 'desktop')->count(),
            'thisWeek' => DB::table('pwa_installs')->where('created_at', '>=', now()->subWeek())->count(),
            'today'    => DB::table('pwa_installs')->whereDate('created_at', today())->count(),
        ];

        return view('admin.pwa-installs.index', compact('installs', 'stats'));
    }
}
