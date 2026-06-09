import type {
    DesignationPayrollItem,
    DesignationTotals,
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

export function amountFieldLabel(item: DesignationPayrollItem): string {
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
    };
}

export type PayrollItemGroups = {
    mandatory: DesignationPayrollItem[];
    fixed_additions: DesignationPayrollItem[];
    fixed_deductions: DesignationPayrollItem[];
    daily: DesignationPayrollItem[];
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
    };
}

export function calculateDesignationTotals(items: DesignationPayrollItem[]): DesignationTotals {
    const fixedItems = items.filter((item) => item.calculation_method === 'fixed');

    const additions = fixedItems
        .filter((item) => item.type === 'addition')
        .reduce((sum, item) => sum + Number(item.amount || 0), 0);

    const deductions = fixedItems
        .filter((item) => item.type === 'deduction')
        .reduce((sum, item) => sum + Number(item.amount || 0), 0);

    const dailyCount = items.filter((item) => item.calculation_method === 'daily').length;

    return {
        additions: Math.round(additions * 100) / 100,
        deductions: Math.round(deductions * 100) / 100,
        net: Math.round((additions - deductions) * 100) / 100,
        has_daily: dailyCount > 0,
        daily_count: dailyCount,
    };
}
