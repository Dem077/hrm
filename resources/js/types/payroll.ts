export type PayrollComponentType = 'addition' | 'deduction';

export type PayrollComponentCalculationMethod = 'fixed' | 'daily';

export type PayrollComponent = {
    id: number | null;
    name: string;
    code: string | null;
    type: PayrollComponentType;
    type_label?: string;
    calculation_method: PayrollComponentCalculationMethod;
    calculation_method_label?: string;
    amount_label?: string;
    is_mandatory: boolean;
    is_system_mandatory?: boolean;
    sort_order: number;
    is_active: boolean;
    designations_count?: number;
};

export type DesignationPayrollItem = {
    payroll_component_id: number;
    name: string;
    type: PayrollComponentType;
    type_label?: string;
    calculation_method: PayrollComponentCalculationMethod;
    calculation_method_label?: string;
    amount_label?: string;
    is_mandatory: boolean;
    amount: number;
};

export type PayrollItemGroups = {
    mandatory: DesignationPayrollItem[];
    fixed_additions: DesignationPayrollItem[];
    fixed_deductions: DesignationPayrollItem[];
    daily: DesignationPayrollItem[];
};

export type DesignationTotals = {
    additions: number;
    deductions: number;
    net: number;
    has_daily?: boolean;
    daily_count?: number;
};

export type Designation = {
    id: number | null;
    name: string;
    code: string | null;
    description: string | null;
    sort_order: number;
    is_active: boolean;
    items: DesignationPayrollItem[];
    item_groups?: PayrollItemGroups;
    totals?: DesignationTotals;
};
