<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Contracts\View\View;

class ReturnPolicyController extends Controller
{
    public function show(): View
    {
        $policy = Setting::get('return_policy', '');

        return view('shop.return-policy.show', compact('policy'));
    }
}
