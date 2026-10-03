<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\PolicyFormatter;
use Illuminate\Contracts\View\View;

class ReturnPolicyController extends Controller
{
    public function show(): View
    {
        $policy = Setting::get('return_policy', '');
        $policy = is_string($policy) ? $policy : '';
        $doc    = PolicyFormatter::format($policy);

        return view('shop.return-policy.show', ['policy' => $policy] + $doc);
    }
}
