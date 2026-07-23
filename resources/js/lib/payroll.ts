import type {
    DesignationPayrollItem,
    DesignationTotals,
    LoanBankOption,
    PayrollComponent,
    PayrollComponentCalculationMethod,
} from '@/types/payroll';

export function formatPayrollMoney(amount: number): string {
    return new Intl.NumberFormat('en-PK', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);
}

export function isDailyCalculation(method: PayrollComponentCalculationMethod | string): boolean {
    return method === 'daily';
}

export function isHourlyCalculation(method: PayrollComponentCalculationMethod | string): boolean {
    return method === 'hourly';
}

export function isAttendanceAllowanceCalculation(method: PayrollComponentCalculationMethod | string): boolean {
    return isDailyCalculation(method) || isHourlyCalculation(method);
}

export function usesGlobalRateCalculation(method: PayrollComponentCalculationMethod | string): boolean {
    return (
        method === 'per_late_minute' ||
        method === 'per_late_minute_of_basic' ||
        method === 'per_absent_day' ||
        method === 'per_absent_day_of_basic' ||
        method === 'custom_formula'
    );
}

export function isPercentageOfBasicCalculation(method: PayrollComponentCalculationMethod | string): boolean {
    return method === 'per_late_minute_of_basic' || method === 'per_absent_day_of_basic';
}

export function isCustomFormulaCalculation(method: PayrollComponentCalculationMethod | string): boolean {
    return method === 'custom_formula';
}

export function isLoanType(type: string): boolean {
    return type === 'loan';
}

export function amountFieldLabel(item: DesignationPayrollItem): string {
    if (isLoanType(item.type)) {
        return 'Monthly payment';
    }

    if (item.amount_label) {
        return item.amount_label;
    }

    if (isDailyCalculation(item.calculation_method)) {
        return 'Rate / attended day';
    }

    if (isHourlyCalculation(item.calculation_method)) {
        return 'Rate / hour';
    }

    if (item.calculation_method === 'per_late_minute') {
        return 'Rate / late minute';
    }

    if (item.calculation_method === 'per_late_minute_of_basic') {
        return '% of basic salary / late minute';
    }

    if (item.calculation_method === 'per_absent_day') {
        return 'Rate / absent day';
    }

    if (item.calculation_method === 'per_absent_day_of_basic') {
        return '% of basic salary / absent day';
    }

    if (item.calculation_method === 'custom_formula') {
        return 'Formula';
    }

    return 'Amount';
}

export function componentToPayrollItem(
    component: PayrollComponent,
    amount = 0,
    defaultBank: LoanBankOption | null = null,
): DesignationPayrollItem {
    return {
        payroll_component_id: component.id!,
        name: component.name,
        type: component.type,
        type_label: component.type_label,
        calculation_method: component.calculation_method,
        calculation_method_label: component.calculation_method_label,
        amount_label: component.amount_label,
        is_mandatory: component.is_mandatory,
        uses_global_rate: component.uses_global_rate ?? usesGlobalRateCalculation(component.calculation_method),
        global_rate: component.uses_global_rate ? Number(component.global_rate ?? 0) : null,
        calculation_formula: component.calculation_formula ?? null,
        amount: component.uses_global_rate ? Number(component.global_rate ?? amount) : amount,
        loan_months: isLoanType(component.type) ? 12 : null,
        loan_bank: isLoanType(component.type) ? (defaultBank?.value ?? defaultLoanBank) : null,
        loan_bank_label: isLoanType(component.type)
            ? (defaultBank?.label ?? defaultBank?.value ?? defaultLoanBank)
            : null,
    };
}

export type PayrollItemGroups = {
    mandatory: DesignationPayrollItem[];
    fixed_additions: DesignationPayrollItem[];
    fixed_deductions: DesignationPayrollItem[];
    attendance_allowance: DesignationPayrollItem[];
    company_penalties: DesignationPayrollItem[];
    loans: DesignationPayrollItem[];
};

export function groupPayrollItems(items: DesignationPayrollItem[]): PayrollItemGroups {
    return {
        mandatory: items.filter(
            (item) =>
                item.is_mandatory &&
                !isAttendanceAllowanceCalculation(item.calculation_method) &&
                !usesGlobalRateCalculation(item.calculation_method) &&
                !isLoanType(item.type),
        ),
        fixed_additions: items.filter(
            (item) => !item.is_mandatory && item.calculation_method === 'fixed' && item.type === 'addition',
        ),
        fixed_deductions: items.filter(
            (item) => !item.is_mandatory && item.calculation_method === 'fixed' && item.type === 'deduction',
        ),
        attendance_allowance: items.filter((item) =>
            isAttendanceAllowanceCalculation(item.calculation_method),
        ),
        company_penalties: items.filter((item) => usesGlobalRateCalculation(item.calculation_method)),
        loans: items.filter((item) => item.type === 'loan'),
    };
}

export function calculateDesignationTotals(items: DesignationPayrollItem[]): DesignationTotals {
    const additions = items
        .filter((item) => item.type === 'addition' && item.calculation_method === 'fixed')
        .reduce((sum, item) => sum + Number(item.amount || 0), 0);

    const fixedDeductions = items
        .filter((item) => item.type === 'deduction' && item.calculation_method === 'fixed')
        .reduce((sum, item) => sum + Number(item.amount || 0), 0);

    const loanDeductions = items
        .filter((item) => item.type === 'loan')
        .reduce((sum, item) => sum + Number(item.amount || 0), 0);

    const deductions = fixedDeductions + loanDeductions;
    const attendanceAllowanceCount = items.filter((item) =>
        isAttendanceAllowanceCalculation(item.calculation_method),
    ).length;
    const dailyCount = items.filter((item) => item.calculation_method === 'daily').length;
    const loanCount = items.filter((item) => item.type === 'loan').length;

    return {
        additions: Math.round(additions * 100) / 100,
        deductions: Math.round(deductions * 100) / 100,
        net: Math.round((additions - deductions) * 100) / 100,
        has_attendance_allowance: attendanceAllowanceCount > 0,
        attendance_allowance_count: attendanceAllowanceCount,
        has_daily: dailyCount > 0,
        daily_count: dailyCount,
        has_loans: loanCount > 0,
        loan_count: loanCount,
    };
}

export function updatePayrollItem(
    items: DesignationPayrollItem[],
    item: DesignationPayrollItem,
    changes: Partial<Pick<DesignationPayrollItem, 'amount' | 'loan_months' | 'loan_bank' | 'loan_bank_label'>>,
): DesignationPayrollItem[] {
    return items.map((row) =>
        row.payroll_component_id === item.payroll_component_id ? { ...row, ...changes } : row,
    );
}

export const defaultLoanBank = 'BML';
