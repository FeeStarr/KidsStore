<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\AboutPage;
use Illuminate\Contracts\View\View;

class AboutController extends Controller
{
    public function show(): View
    {
        $about = AboutPage::instance();

        return view('shop.about', compact('about'));
    }
}
