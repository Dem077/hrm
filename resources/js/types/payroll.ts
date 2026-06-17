export type PayrollComponentType = 'addition' | 'deduction' | 'loan';

export type PayrollComponentCalculationMethod = 'fixed' | 'daily' | 'hourly';

export type PayrollLoanBank = 'BML' | 'MIB' | 'CBM';

export type LoanBankOption = {
    value: PayrollLoanBank;
    label: string;
};

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
    loan_months: number | null;
    loan_bank: PayrollLoanBank | null;
    loan_bank_label?: string | null;
};

export type PayrollItemGroups = {
    mandatory: DesignationPayrollItem[];
    fixed_additions: DesignationPayrollItem[];
    fixed_deductions: DesignationPayrollItem[];
    attendance_allowance: DesignationPayrollItem[];
    loans: DesignationPayrollItem[];
};

export type DesignationTotals = {
    additions: number;
    deductions: number;
    net: number;
    has_attendance_allowance?: boolean;
    attendance_allowance_count?: number;
    has_daily?: boolean;
    daily_count?: number;
    has_loans?: boolean;
    loan_count?: number;
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
