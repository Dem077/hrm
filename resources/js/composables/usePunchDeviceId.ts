import { onMounted, ref } from 'vue';

const STORAGE_KEY = 'hrm.self_punch.device_id';

function createDeviceId(): string {
    return crypto.randomUUID();
}

function readStoredDeviceId(): string | null {
    try {
        return localStorage.getItem(STORAGE_KEY);
    } catch {
        return null;
    }
}

function storeDeviceId(deviceId: string): void {
    try {
        localStorage.setItem(STORAGE_KEY, deviceId);
    } catch {
        // Private browsing or blocked storage — still use in-memory id for this session.
    }
}

export function usePunchDeviceId() {
    const deviceId = ref<string | null>(null);

    onMounted(() => {
        const stored = readStoredDeviceId();
        if (stored) {
            deviceId.value = stored;
            return;
        }

        const created = createDeviceId();
        storeDeviceId(created);
        deviceId.value = created;
    });

    return { deviceId };
}
