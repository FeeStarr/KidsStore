<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function index(): View
    {
        $methods = PaymentMethod::orderBy('key')->get();
        return view('admin.payment_methods.index', compact('methods'));
    }

    public function update(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $request->validate(['is_active' => ['required', 'boolean']]);
        $paymentMethod->update(['is_active' => (bool) $request->is_active]);
        return back()->with('success', 'Payment method updated.');
    }
}
