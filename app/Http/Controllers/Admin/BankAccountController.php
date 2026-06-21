<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

class BankAccountController extends Controller
{
    public function index(): View
    {
        $accounts = BankAccount::orderByDesc('is_default')->get();
        return view('admin.bank_accounts.index', compact('accounts'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'bank_name' => ['nullable','string','max:120'],
            'bank_account_name' => ['nullable','string','max:200'],
            'bank_account_number' => ['nullable','string','max:60'],
            'instructions' => ['nullable','string','max:1000'],
        ]);

        if ($request->has('is_default') && $request->boolean('is_default')) {
            BankAccount::query()->update(['is_default' => false]);
            $data['is_default'] = true;
        }

        $data['is_active'] = $request->boolean('is_active', true);

        BankAccount::create($data);
        return back()->with('success', 'Bank account added.');
    }

    public function update(Request $request, BankAccount $bankAccount)
    {
        if ($request->has('set_default')) {
            BankAccount::query()->update(['is_default' => false]);
            $bankAccount->update(['is_default' => true]);
            return back()->with('success', 'Default account updated.');
        }

        $data = $request->validate([
            'bank_name' => ['nullable','string','max:120'],
            'bank_account_name' => ['nullable','string','max:200'],
            'bank_account_number' => ['nullable','string','max:60'],
            'instructions' => ['nullable','string','max:1000'],
            'is_active' => ['nullable','boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active', $bankAccount->is_active);
        $bankAccount->update($data);
        return back()->with('success', 'Bank account updated.');
    }

    public function destroy(BankAccount $bankAccount)
    {
        $bankAccount->delete();
        return back()->with('success', 'Bank account removed.');
    }
}
