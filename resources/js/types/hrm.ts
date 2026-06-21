export type Gender = 'male' | 'female' | 'other';

export type DutyType = 'normal' | 'shift';

export type ZktDevicePrivilege = 'employee' | 'enroller' | 'administrator';

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
    designation_id: number | null;
    designation?: { id: number; name: string } | null;
    device_privilege: ZktDevicePrivilege;
    device_privilege_label?: string;
    device_card_number: string | null;
    device_password?: string | null;
    has_device_password?: boolean;
    user_id: number | null;
    has_login?: boolean;
    user?: { id: number; name: string; email: string } | null;
    manager_id: number | null;
    manager?: { id: number; name: string; staff_id: string } | null;
    is_active: boolean;
    works_saturday: boolean;
    duty_type: DutyType;
    duty_type_label?: string;
    uses_custom_duty_times: boolean;
    custom_duty_start_time: string | null;
    custom_duty_end_time: string | null;
    custom_grace_minutes: number | null;
    custom_saturday_duty_start_time: string | null;
    custom_saturday_duty_end_time: string | null;
    custom_saturday_grace_minutes: number | null;
    role_names?: string[];
    direct_reports?: Array<{ id: number; name: string; staff_id: string }>;
    zkt_location_group_ids?: number[];
    zkt_location_groups?: Array<{ id: number; name: string; code: string | null; is_active: boolean }>;
    zkt_device_syncs?: ZktDeviceEmployeeSync[];
};

export type ZktDeviceEmployeeSync = {
    id: number;
    device_uid: number | null;
    sync_status: string;
    sync_status_label: string;
    sync_status_color: string;
    last_synced_at: string | null;
    last_error: string | null;
    device?: { id: number; name: string; location: string | null } | null;
};

export type ZktLocationGroup = {
    id: number | null;
    name: string;
    code: string | null;
    description: string | null;
    sort_order: number;
    is_active: boolean;
    devices_count?: number;
    employees_count?: number;
    device_ids?: number[];
    devices?: Array<{ id: number; name: string; location: string | null; is_active: boolean }>;
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

export type DutyTypeOption = {
    value: DutyType;
    label: string;
};

export type DevicePrivilegeOption = {
    value: ZktDevicePrivilege;
    label: string;
};

export type DutyShiftTemplate = {
    id: number | null;
    name: string;
    duty_start_time: string;
    duty_end_time: string;
    grace_minutes: number;
    notes: string | null;
    is_active: boolean;
    sort_order: number;
};

export type ShiftEmployeeOption = {
    id: number;
    label: string;
    department_id: number | null;
};

export type DutyRosterEntry = {
    id: number | null;
    employee_id: number | null;
    employee?: {
        id: number;
        staff_id: string;
        name: string;
        department?: string | null;
    } | null;
    duty_date: string;
    duty_start_time: string;
    duty_end_time: string;
    grace_minutes: number;
    notes: string | null;
};
