<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReturnPolicyController extends Controller
{
    public function edit(): View
    {
        $policy = Setting::get('return_policy', '');

        return view('admin.return-policy.edit', compact('policy'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'return_policy' => ['required', 'string'],
        ]);

        Setting::set('return_policy', $data['return_policy']);

        return back()->with('success', 'Return policy updated successfully.');
    }
}
