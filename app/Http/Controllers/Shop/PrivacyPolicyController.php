<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Contracts\View\View;

class PrivacyPolicyController extends Controller
{
    public function show(): View
    {
        $policy = Setting::get('privacy_policy', '');

        return view('shop.privacy-policy.show', compact('policy'));
    }
}
