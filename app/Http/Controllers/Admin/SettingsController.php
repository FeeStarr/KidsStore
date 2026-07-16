<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit()
    {
        $homeFee = Setting::get('home_delivery_fee', '0');
        $shippingFee = Setting::get('shipping_fee', $homeFee);
        $shippingDiscount = Setting::get('shipping_discount', '0');
        return view('admin.settings.edit', compact('homeFee', 'shippingFee', 'shippingDiscount'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'shipping_fee'       => ['required', 'numeric', 'min:0'],
            'shipping_discount'  => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $saved = Setting::set('shipping_fee', (string) $data['shipping_fee']);
        $saved = Setting::set('shipping_discount', (string) ($data['shipping_discount'] ?? 0)) && $saved;

        if (! $saved) {
            return redirect()->route('admin.settings.edit')->with('error', 'Could not save settings. Ensure the database migrations have been run and the DB is available.');
        }

        return redirect()->route('admin.settings.edit')->with('success', 'Settings updated.');
    }
}
