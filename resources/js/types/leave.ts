export type LeaveType = {
    id: number | null;
    name: string;
    code: string | null;
    description: string | null;
    requires_document: boolean;
    is_visible_to_employees: boolean;
    is_active: boolean;
    sort_order: number;
    annual_limit: number | null;
    can_carry_forward: boolean;
    max_carry_forward_days: number | null;
    leave_requests_count?: number;
};

export type LeaveRequestItem = {
    id: number;
    record_number: string;
    employee_id: number;
    employee: {
        id: number;
        name: string;
        staff_id: string;
        department: { id: number; name: string } | null;
    } | null;
    leave_type_id: number;
    leave_type: {
        id: number;
        name: string;
        requires_document: boolean;
    } | null;
    start_date: string;
    end_date: string;
    days_count: number;
    reason: string;
    document_path: string | null;
    document_url: string | null;
    status: string;
    status_label: string;
    status_color: string;
    approver_employee_id: number | null;
    approver: { id: number; name: string; staff_id: string } | null;
    approver_label: string | null;
    manager_reviewed_by_employee_id: number | null;
    manager_reviewed_by: { id: number; name: string; staff_id: string } | null;
    manager_reviewed_at: string | null;
    manager_review_notes: string | null;
    reviewed_by_employee_id: number | null;
    reviewed_by: { id: number; name: string; staff_id: string } | null;
    reviewed_at: string | null;
    review_notes: string | null;
    created_at: string | null;
};

export type LeaveTypeOption = {
    id: number;
    name: string;
    description: string | null;
    requires_document: boolean;
    annual_limit: number | null;
    carry_forward_days: number | null;
    available_days: number | null;
    used_days: number | null;
    remaining_days: number | null;
    period_start: string | null;
    period_end: string | null;
};

export type LeaveBalanceLeaveType = {
    id: number;
    leave_type_id: number;
    name: string;
    code: string | null;
    annual_limit: number | null;
    carry_forward_days: number | null;
    available_days: number | null;
    used_days: number | null;
    remaining_days: number | null;
    period_start: string | null;
    period_end: string | null;
};

export type LeaveYearOption = {
    offset: number;
    period_start: string;
    period_end: string;
    label: string;
    is_current: boolean;
};

export type LeaveBalanceEmployee = {
    id: number;
    name: string;
    staff_id: string;
    department: string | null;
    joined_date: string | null;
    balances: LeaveBalanceLeaveType[];
};

export type LeaveCarryForwardAdjustment = {
    id: number;
    leave_type: { id: number; name: string; code: string | null } | null;
    days: number;
    reason: string;
    from_period_label: string;
    to_period_label: string;
    moved_by: string;
    created_at: string | null;
};
