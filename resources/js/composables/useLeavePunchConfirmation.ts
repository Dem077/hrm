import { ref } from 'vue';

type PunchConflictResponse = {
    has_punches: boolean;
    dates: string[];
    message: string | null;
};

type LeavePunchCheckPayload = {
    start_date: string;
    end_date: string;
    employee_id?: string | number | null;
};

function csrfHeaders(): Record<string, string> {
    const headers: Record<string, string> = {};
    const meta = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    if (meta) {
        headers['X-CSRF-TOKEN'] = meta;
    }

    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    if (match) {
        headers['X-XSRF-TOKEN'] = decodeURIComponent(match[1]);
    }

    return headers;
}

export async function checkLeavePunchConflict(payload: LeavePunchCheckPayload): Promise<PunchConflictResponse> {
    const body: Record<string, string | number> = {
        start_date: String(payload.start_date),
        end_date: String(payload.end_date),
    };

    if (payload.employee_id) {
        body.employee_id = Number(payload.employee_id);
    }

    const response = await fetch('/leave-requests/check-punches', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...csrfHeaders(),
        },
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        throw new Error('Unable to check punch records for these dates.');
    }

    return response.json() as Promise<PunchConflictResponse>;
}

export function useLeavePunchConfirmation() {
    const dialogOpen = ref(false);
    const dialogMessage = ref('');
    const checkError = ref<string | null>(null);
    const checking = ref(false);

    async function requestConfirmation(payload: LeavePunchCheckPayload): Promise<'proceed' | 'confirm' | 'error'> {
        checking.value = true;
        checkError.value = null;

        try {
            const result = await checkLeavePunchConflict(payload);

            if (result.has_punches && result.message) {
                dialogMessage.value = result.message;
                dialogOpen.value = true;

                return 'confirm';
            }

            return 'proceed';
        } catch {
            checkError.value = 'Unable to check punch records for these dates. Please try again.';

            return 'error';
        } finally {
            checking.value = false;
        }
    }

    function closeDialog(): void {
        dialogOpen.value = false;
    }

    return {
        dialogOpen,
        dialogMessage,
        checkError,
        checking,
        requestConfirmation,
        closeDialog,
    };
}
