export type AttendanceDutyPolicy = {
    id?: number | null;
    effective_from: string;
    duty_start_time: string;
    duty_end_time: string;
    grace_minutes: number;
    saturday_duty_start_time: string;
    saturday_duty_end_time: string;
    saturday_grace_minutes: number;
    label?: string;
};

export type AttendanceSheetRow = {
    date: string;
    employee_id: number;
    staff_id: string;
    employee_name: string;
    department: string | null;
    check_in: string | null;
    check_out: string | null;
    working_minutes: number | null;
    working_hours_label: string;
    late_minutes: number | null;
    status: string;
    status_label: string;
    status_color: string;
    holiday_name: string | null;
    duty_start_time: string;
    duty_end_time: string;
    duty_policy?: AttendanceDutyPolicy;
};

export type PublicHoliday = {
    id: number | null;
    name: string;
    date: string;
    notes: string | null;
};

export type Paginated<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    total: number;
    from: number | null;
    to: number | null;
};
