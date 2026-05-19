<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AboutPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AboutController extends Controller
{
    public function edit(): View
    {
        $about = AboutPage::instance();

        return view('admin.about.edit', compact('about'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'hero_title'    => ['required', 'string', 'max:255'],
            'hero_subtitle' => ['required', 'string', 'max:255'],
            'story'         => ['nullable', 'string'],
            'mission'       => ['nullable', 'string'],
            'email'         => ['nullable', 'email', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:50'],
            'address'       => ['nullable', 'string', 'max:255'],
        ]);

        AboutPage::instance()->update($data);

        return back()->with('success', 'About page updated successfully.');
    }
}
