<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBankRequest;
use App\Http\Requests\UpdateBankRequest;
use App\Models\Bank;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class BankController extends Controller
{
    public function store(StoreBankRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['code'] = strtoupper($data['code']);
        $data['sort_order'] ??= (int) Bank::query()->max('sort_order') + 1;
        $data['is_active'] = $request->boolean('is_active', true);

        Bank::query()->create($data);

        return back()->with('success', 'Bank added.');
    }

    public function update(UpdateBankRequest $request, Bank $bank): RedirectResponse
    {
        $data = $request->validated();
        $data['code'] = strtoupper($data['code']);
        $data['is_active'] = $request->boolean('is_active', true);

        $bank->update($data);

        return back()->with('success', 'Bank updated.');
    }

    public function destroy(Bank $bank): RedirectResponse
    {
        if ($bank->isInUse()) {
            throw ValidationException::withMessages([
                'bank' => 'This bank is in use by employees or payroll packages and cannot be deleted. Deactivate it instead.',
            ]);
        }

        $bank->delete();

        return back()->with('success', 'Bank deleted.');
    }
}
