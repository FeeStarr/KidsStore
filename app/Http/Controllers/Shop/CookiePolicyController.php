<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;

class CookiePolicyController extends Controller
{
    public function show()
    {
        return view('shop.cookie-policy');
    }
}
