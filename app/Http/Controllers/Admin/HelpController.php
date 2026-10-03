<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HelpController extends Controller
{
    public function edit(): View
    {
        $saved = Setting::get('help_guide', '');

        return view('admin.help.edit', [
            'text'      => is_string($saved) ? $saved : '',
            'isDefault' => ! is_string($saved) || trim($saved) === '',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'help_guide' => ['required', 'string', 'max:30000'],
        ]);

        Setting::set('help_guide', $data['help_guide']);

        return back()->with('success', 'Help & Guide updated successfully.');
    }
}
