export type LeaveType = {
    id: number | null;
    name: string;
    code: string | null;
    description: string | null;
    requires_document: boolean;
    is_visible_to_employees: boolean;
    is_active: boolean;
    sort_order: number;
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
};
