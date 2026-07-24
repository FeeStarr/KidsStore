<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PrivacyPolicyController extends Controller
{
    public function edit(): View
    {
        $policy = Setting::get('privacy_policy', '');

        return view('admin.privacy-policy.edit', compact('policy'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'privacy_policy' => ['required', 'string'],
        ]);

        Setting::set('privacy_policy', $data['privacy_policy']);

        return back()->with('success', 'Privacy policy updated successfully.');
    }
}
