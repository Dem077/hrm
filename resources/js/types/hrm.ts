export type Gender = 'male' | 'female' | 'other';

export type Employee = {
    id: number | null;
    staff_id: string;
    name: string;
    national_id: string;
    email: string | null;
    mobile_number: string | null;
    joined_date: string | null;
    gender: Gender;
    gender_label?: string;
    department_id: number | null;
    department?: { id: number; name: string } | null;
    user_id: number | null;
    has_login?: boolean;
    user?: { id: number; name: string; email: string } | null;
    manager_id: number | null;
    manager?: { id: number; name: string; staff_id: string } | null;
    is_active: boolean;
    works_saturday: boolean;
    role_names?: string[];
    direct_reports?: Array<{ id: number; name: string; staff_id: string }>;
};

export type Department = {
    id: number | null;
    name: string;
    code: string | null;
    description: string | null;
    head_employee_id: number | null;
    head_employee?: { id: number; name: string; staff_id: string } | null;
    is_active: boolean;
    sort_order: number;
    employees_count?: number;
    employees?: Array<{
        id: number;
        staff_id: string;
        name: string;
        manager: { id: number; name: string } | null;
    }>;
};

export type SelectOption = {
    id: number;
    name?: string;
    label?: string;
};

export type GenderOption = {
    value: Gender;
    label: string;
};
