<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RefundRequest;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReturnPolicyController extends Controller
{
    public function edit(): View
    {
        $policy   = Setting::get('return_policy', '');
        $windows  = RefundRequest::windowSettings();

        return view('admin.return-policy.edit', compact('policy', 'windows'));
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [
            'return_policy' => ['required', 'string'],
        ];

        foreach (array_keys(RefundRequest::WINDOW_SETTINGS) as $key) {
            $rules[$key] = ['nullable', 'numeric', 'min:0.5', 'max:365'];
        }

        $data = $request->validate($rules);

        Setting::set('return_policy', $data['return_policy']);

        foreach (array_keys(RefundRequest::WINDOW_SETTINGS) as $key) {
            if ($request->filled($key)) {
                Setting::set($key, (float) $request->input($key));
            }
        }

        return back()->with('success', 'Return policy updated successfully.');
    }
}
