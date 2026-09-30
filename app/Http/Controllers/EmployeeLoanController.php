<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeLoanRequest;
use App\Http\Requests\UpdateEmployeeLoanRequest;
use App\Models\Bank;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeLoanController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('EmployeeLoans/Index', [
            'loans' => EmployeeLoan::query()
                ->with('employee:id,name,staff_id')
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get()
                ->map(fn (EmployeeLoan $loan) => $loan->toPresentationArray()),
            'employees' => Employee::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'staff_id'])
                ->map(fn (Employee $employee) => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'staff_id' => $employee->staff_id,
                    'label' => "{$employee->name} ({$employee->staff_id})",
                ]),
            'loanBanks' => Bank::options(),
            'emptyLoan' => $this->emptyLoan(),
        ]);
    }

    public function store(StoreEmployeeLoanRequest $request): RedirectResponse
    {
        EmployeeLoan::query()->create($request->loanAttributes());

        return back()->with('success', 'Employee loan created successfully.');
    }

    public function update(UpdateEmployeeLoanRequest $request, EmployeeLoan $employeeLoan): RedirectResponse
    {
        $employeeLoan->update($request->loanAttributes());

        return back()->with('success', 'Employee loan updated successfully.');
    }

    public function destroy(EmployeeLoan $employeeLoan): RedirectResponse
    {
        $employeeLoan->delete();

        return back()->with('success', 'Employee loan deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyLoan(): array
    {
        return [
            'id' => null,
            'employee_id' => null,
            'name' => '',
            'monthly_amount' => '',
            'loan_months' => 12,
            'loan_bank' => Bank::defaultCode(),
            'start_date' => now()->toDateString(),
            'notes' => '',
            'is_active' => true,
        ];
    }
}
