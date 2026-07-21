export type ConnectionStatus = 'unknown' | 'online' | 'offline' | 'inactive';

export type BrandOption = {
    value: string;
    label: string;
};

export type MachineTypeOption = {
    value: string;
    label: string;
};

export type ZktMachineType = 'attendance' | 'access';

export type ZktConnectionMode = 'tcp_pull' | 'adms_push';

export type ZktDevice = {
    id: number | null;
    name: string;
    brand: string;
    brand_label?: string;
    location: string | null;
    machine_type: ZktMachineType;
    machine_type_label?: string;
    machine_type_short_label?: string;
    machine_type_color?: string;
    ip_address: string;
    port: number;
    protocol: string;
    protocol_label?: string;
    connection_mode: ZktConnectionMode;
    connection_mode_label?: string;
    comm_password: number;
    serial_number: string | null;
    model_name: string | null;
    firmware_version: string | null;
    is_active: boolean;
    auto_sync: boolean;
    sync_interval_minutes: number;
    connection_status: ConnectionStatus;
    connection_status_label?: string;
    connection_status_color?: string;
    last_connected_at: string | null;
    last_synced_at: string | null;
    last_adms_seen_at?: string | null;
    last_sync_error: string | null;
    notes: string | null;
    tcpmux_enabled: boolean;
    tcpmux_subdomain: string | null;
    tcpmux_port: number | null;
    adms_pending_commands?: number | null;
    adms_failed_commands?: number | null;
    adms_commands?: AdmsCommand[];
    sync_logs?: SyncLog[];
    attendance_logs?: AttendanceLogSummary[];
    employee_syncs?: DeviceEmployeeSync[];
};

export type AdmsCommand = {
    id: number;
    command_no: number;
    payload: string;
    status: string;
    status_label: string;
    status_color: string;
    result: string | null;
    sent_at: string | null;
    completed_at: string | null;
    created_at: string | null;
};

export type DeviceEmployeeSync = {
    id: number;
    device_uid: number | null;
    sync_status: string;
    sync_status_label: string;
    sync_status_color: string;
    last_synced_at: string | null;
    last_error: string | null;
    employee: {
        id: number;
        name: string;
        staff_id: string;
    } | null;
};

export type SyncLog = {
    id: number;
    status: string;
    status_label: string;
    status_color: string;
    records_fetched: number;
    records_stored: number;
    message: string | null;
    started_at: string | null;
    completed_at: string | null;
};

export type AttendanceLogSummary = {
    id: number;
    device_user_id: string;
    emp_no: string;
    employee: {
        id: number;
        name: string;
        staff_id: string;
    } | null;
    device_uid: number;
    punch_state_label: string;
    punched_at: string | null;
};

export type ProtocolOption = {
    value: string;
    label: string;
};

export type ConnectionModeOption = {
    value: ZktConnectionMode;
    label: string;
};

export type Paginated<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
};

export type AttendanceLog = {
    id: number;
    device: {
        id: number;
        name: string;
    };
    device_user_id: string;
    emp_no: string;
    employee: {
        id: number;
        name: string;
        staff_id: string;
    } | null;
    device_uid: number;
    punch_state_label: string;
    punched_at: string | null;
    source: string;
    source_label: string;
    manual_reason: string | null;
    is_manual: boolean;
    is_removed: boolean;
    removal_reason: string | null;
};
