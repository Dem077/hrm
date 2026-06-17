import type {
    DesignationPayrollItem,
    DesignationTotals,
    PayrollComponent,
    PayrollComponentCalculationMethod,
    PayrollLoanBank,
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

export function isLoanType(type: string): boolean {
    return type === 'loan';
}

export function amountFieldLabel(item: DesignationPayrollItem): string {
    if (isLoanType(item.type)) {
        return 'Monthly payment';
    }

    return item.amount_label ?? (isDailyCalculation(item.calculation_method) ? 'Rate / day' : 'Amount');
}

export function componentToPayrollItem(component: PayrollComponent, amount = 0): DesignationPayrollItem {
    return {
        payroll_component_id: component.id!,
        name: component.name,
        type: component.type,
        type_label: component.type_label,
        calculation_method: component.calculation_method,
        calculation_method_label: component.calculation_method_label,
        amount_label: component.amount_label,
        is_mandatory: component.is_mandatory,
        amount,
        loan_months: isLoanType(component.type) ? 12 : null,
        loan_bank: isLoanType(component.type) ? 'BML' : null,
        loan_bank_label: isLoanType(component.type) ? 'BML' : null,
    };
}

export type PayrollItemGroups = {
    mandatory: DesignationPayrollItem[];
    fixed_additions: DesignationPayrollItem[];
    fixed_deductions: DesignationPayrollItem[];
    daily: DesignationPayrollItem[];
    loans: DesignationPayrollItem[];
};

export function groupPayrollItems(items: DesignationPayrollItem[]): PayrollItemGroups {
    return {
        mandatory: items.filter((item) => item.is_mandatory),
        fixed_additions: items.filter(
            (item) => !item.is_mandatory && item.calculation_method === 'fixed' && item.type === 'addition',
        ),
        fixed_deductions: items.filter(
            (item) => !item.is_mandatory && item.calculation_method === 'fixed' && item.type === 'deduction',
        ),
        daily: items.filter((item) => item.calculation_method === 'daily'),
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
    const dailyCount = items.filter((item) => item.calculation_method === 'daily').length;
    const loanCount = items.filter((item) => item.type === 'loan').length;

    return {
        additions: Math.round(additions * 100) / 100,
        deductions: Math.round(deductions * 100) / 100,
        net: Math.round((additions - deductions) * 100) / 100,
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

export const defaultLoanBank: PayrollLoanBank = 'BML';
