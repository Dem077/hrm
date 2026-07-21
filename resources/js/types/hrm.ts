export type Gender = 'male' | 'female' | 'other';

export type DutyType = 'normal' | 'shift';

export type ZktDevicePrivilege = 'employee' | 'enroller' | 'administrator';

export type MaritalStatus = 'single' | 'married' | 'divorced' | 'widowed' | 'other';

export type BloodGroup =
    | 'a_positive'
    | 'a_negative'
    | 'b_positive'
    | 'b_negative'
    | 'ab_positive'
    | 'ab_negative'
    | 'o_positive'
    | 'o_negative';

export type EmploymentType = 'permanent' | 'contract' | 'probation' | 'temporary' | 'intern';

export type Employee = {
    id: number | null;
    staff_id: string;
    name: string;
    profile_photo_url?: string | null;
    national_id: string;
    email: string | null;
    mobile_number: string | null;
    joined_date: string | null;
    gender: Gender;
    gender_label?: string;
    grade_id: number | null;
    grade?: {
        id: number;
        label: string;
        grade?: string;
        title?: string;
        path_label?: string;
        group?: { id: number; code: string; name: string } | null;
        node?: { id: number; name: string } | null;
        level?: { id: number; level_number: number; reference_title: string } | null;
    } | null;
    department?: { id: number; name: string } | null;
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
    current_address?: string | null;
    permanent_address?: string | null;
    ext_no?: string | null;
    personal_email?: string | null;
    office_email?: string | null;
    emergency_contact_name?: string | null;
    emergency_contact_number?: string | null;
    marital_status?: MaritalStatus | null;
    marital_status_label?: string | null;
    blood_group?: BloodGroup | null;
    blood_group_label?: string | null;
    date_of_birth?: string | null;
    nationality?: string | null;
    religion?: string | null;
    work_location?: string | null;
    qualification?: string | null;
    employment_type?: EmploymentType | null;
    employment_type_label?: string | null;
    bank_name?: string | null;
    bank_name_label?: string | null;
    account_name?: string | null;
    account_no?: string | null;
    length_of_service_label?: string | null;
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

export type EnumOption<T extends string = string> = {
    value: T;
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
