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
        $appName = Setting::get('app_name', config('app.name', 'KidsFlairr'));
        $commissionRate = Setting::get('commission_rate', '10');
        $commissionMin = Setting::get('commission_min', '500');
        $commissionMax = Setting::get('commission_max', '2000');
        return view('admin.settings.edit', compact('homeFee', 'shippingFee', 'shippingDiscount', 'appName', 'commissionRate', 'commissionMin', 'commissionMax'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'app_name'          => ['nullable', 'string', 'max:100'],
            'shipping_fee'      => ['required', 'numeric', 'min:0'],
            'shipping_discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_rate'   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_min'    => ['nullable', 'numeric', 'min:0'],
            'commission_max'    => ['nullable', 'numeric', 'min:0'],
        ]);

        $saved = true;
        if (!empty($data['app_name'])) {
            $saved = Setting::set('app_name', $data['app_name']) && $saved;
        }
        $saved = Setting::set('shipping_fee', (string) $data['shipping_fee']) && $saved;
        $saved = Setting::set('shipping_discount', (string) ($data['shipping_discount'] ?? 0)) && $saved;
        if (!empty($data['commission_rate'])) {
            $saved = Setting::set('commission_rate', (string) $data['commission_rate']) && $saved;
        }
        if (!empty($data['commission_min'])) {
            $saved = Setting::set('commission_min', (string) $data['commission_min']) && $saved;
        }
        if (!empty($data['commission_max'])) {
            $saved = Setting::set('commission_max', (string) $data['commission_max']) && $saved;
        }

        if (! $saved) {
            return redirect()->route('admin.settings.edit')->with('error', 'Could not save settings. Ensure the database migrations have been run and the DB is available.');
        }

        return redirect()->route('admin.settings.edit')->with('success', 'Settings updated.');
    }
}
