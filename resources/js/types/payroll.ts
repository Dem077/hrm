export type PayrollComponentType = 'addition' | 'deduction' | 'loan';

export type PayrollComponentCalculationMethod =
    | 'fixed'
    | 'daily'
    | 'hourly'
    | 'per_late_minute'
    | 'per_late_minute_of_basic'
    | 'per_absent_day'
    | 'per_absent_day_of_basic';

export type PayrollLoanBank = string;

export type LoanBankOption = {
    value: string;
    label: string;
};

export type PayrollCalculationMethodOption = {
    value: PayrollComponentCalculationMethod;
    label: string;
    is_percentage_rate: boolean;
    amount_label: string;
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
    global_rate?: number | null;
    uses_global_rate?: boolean;
    is_percentage_rate?: boolean;
    allowed_calculation_methods?: PayrollCalculationMethodOption[];
    is_mandatory: boolean;
    is_system_mandatory?: boolean;
    sort_order: number;
    is_active: boolean;
    grades_count?: number;
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
    uses_global_rate?: boolean;
    global_rate?: number | null;
    amount: number;
    loan_months: number | null;
    loan_bank: string | null;
    loan_bank_label?: string | null;
};

export type PayrollItemGroups = {
    mandatory: DesignationPayrollItem[];
    fixed_additions: DesignationPayrollItem[];
    fixed_deductions: DesignationPayrollItem[];
    attendance_allowance: DesignationPayrollItem[];
    company_penalties?: DesignationPayrollItem[];
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

export type StructureGradePackage = {
    id: number | null;
    grade: string;
    title: string;
    label: string;
    path_label: string;
    group?: { id: number; code: string; name: string } | null;
    node?: { id: number; name: string } | null;
    level?: { id: number; level_number: number; reference_title: string } | null;
    sort_order: number;
    is_active: boolean;
    items: DesignationPayrollItem[];
    item_groups?: PayrollItemGroups;
    totals?: DesignationTotals;
};

/** @deprecated Use StructureGradePackage */
export type Designation = StructureGradePackage & {
    name?: string;
    code?: string | null;
    description?: string | null;
};
