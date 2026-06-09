export type PayrollComponentType = 'addition' | 'deduction';

export type PayrollComponent = {
    id: number | null;
    name: string;
    code: string | null;
    type: PayrollComponentType;
    type_label?: string;
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
    is_mandatory: boolean;
    amount: number;
};

export type DesignationTotals = {
    additions: number;
    deductions: number;
    net: number;
};

export type Designation = {
    id: number | null;
    name: string;
    code: string | null;
    description: string | null;
    sort_order: number;
    is_active: boolean;
    items: DesignationPayrollItem[];
    totals?: DesignationTotals;
};
