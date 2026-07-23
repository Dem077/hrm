<?php

use App\Services\Payroll\PayrollFormulaEvaluator;
use Illuminate\Validation\ValidationException;

it('evaluates formulas with attendance and salary variables', function () {
    $evaluator = new PayrollFormulaEvaluator();

    expect($evaluator->evaluate('late_minutes * 2', [
        'late_minutes' => 30,
        'absent_days' => 0,
        'present_days' => 20,
        'basic_salary' => 20000,
        'gross_salary' => 20000,
        'total_deductions' => 0,
        'net_salary' => 20000,
        'hours_worked' => 160,
        'additional_hours_worked' => 5,
        'working_days' => 22,
        'total_days_of_payroll' => 30,
    ]))->toBe(60.0);

    expect($evaluator->evaluate('(basic_salary / total_days_of_payroll) * absent_days', [
        'late_minutes' => 0,
        'absent_days' => 3,
        'present_days' => 17,
        'basic_salary' => 30000,
        'gross_salary' => 30000,
        'total_deductions' => 0,
        'net_salary' => 30000,
        'hours_worked' => 0,
        'additional_hours_worked' => 0,
        'working_days' => 22,
        'total_days_of_payroll' => 30,
    ]))->toBe(3000.0);

    expect($evaluator->evaluate('additional_hours_worked * 100 + hours_worked', [
        'late_minutes' => 0,
        'absent_days' => 0,
        'present_days' => 4,
        'basic_salary' => 0,
        'gross_salary' => 0,
        'total_deductions' => 0,
        'net_salary' => 0,
        'hours_worked' => 10,
        'additional_hours_worked' => 2.5,
        'working_days' => 20,
        'total_days_of_payroll' => 31,
    ]))->toBe(260.0);

    expect($evaluator->evaluate('gross_salary * 0.05 + total_deductions', [
        'gross_salary' => 10000,
        'total_deductions' => 500,
        'net_salary' => 9500,
        'basic_salary' => 8000,
    ]))->toBe(1000.0);

    expect($evaluator->evaluate('net_salary * 0.1', [
        'net_salary' => 9000,
        'gross_salary' => 10000,
        'total_deductions' => 1000,
    ]))->toBe(900.0);
});

it('rejects invalid formula tokens', function () {
    $evaluator = new PayrollFormulaEvaluator();

    expect(fn () => $evaluator->assertValid('absent_days + foo'))
        ->toThrow(ValidationException::class);
});

it('rejects division by zero', function () {
    $evaluator = new PayrollFormulaEvaluator();

    expect(fn () => $evaluator->evaluate('basic_salary / absent_days', [
        'absent_days' => 0,
        'present_days' => 0,
        'late_minutes' => 0,
        'basic_salary' => 1000,
        'hours_worked' => 0,
        'additional_hours_worked' => 0,
        'working_days' => 0,
        'total_days_of_payroll' => 30,
    ]))->toThrow(ValidationException::class);
});
