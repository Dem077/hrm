import { computed, onUnmounted, ref } from 'vue';

import { getEcho, initEcho, isEchoEnabled } from '@/echo';

export type JobProgressPayload = {
    job_id: string;
    action: string;
    status: 'queued' | 'running' | 'completed' | 'failed' | 'cancelled' | string;
    total: number;
    done: number;
    percent: number;
    message: string;
    error: string | null;
    cancel_requested?: boolean;
    [key: string]: unknown;
};

export type UseJobProgressOptions = {
    statusUrl: (jobId: string) => string;
    cancelUrl: (jobId: string) => string;
    channel: (jobId: string) => string;
    event: string;
    failedMessage?: string;
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

async function parseJsonResponse(response: Response): Promise<Record<string, unknown>> {
    const payload = await response.json().catch(() => ({}));

    if (! response.ok) {
        const errors = payload.errors as Record<string, string[] | string> | undefined;
        const firstError = errors
            ? Object.values(errors).flat()[0]
            : null;
        const message =
            (typeof firstError === 'string' && firstError) ||
            (typeof payload.message === 'string' && payload.message) ||
            'Request failed.';

        throw new Error(message);
    }

    return payload as Record<string, unknown>;
}

export function useJobProgress(options: UseJobProgressOptions) {
    const open = ref(false);
    const submitting = ref(false);
    const cancelling = ref(false);
    const jobId = ref<string | null>(null);
    const progress = ref<JobProgressPayload | null>(null);
    const error = ref<string | null>(null);
    let pollTimer: ReturnType<typeof setInterval> | null = null;
    let subscribedChannel: string | null = null;
    let settlePromise: {
        resolve: (value: JobProgressPayload) => void;
        reject: (reason?: unknown) => void;
    } | null = null;

    const percent = computed(() => progress.value?.percent ?? 0);
    const message = computed(() => progress.value?.message ?? 'Working…');
    const isTerminal = computed(() =>
        ['completed', 'failed', 'cancelled'].includes(progress.value?.status ?? ''),
    );
    const canCancel = computed(
        () =>
            !!jobId.value &&
            !isTerminal.value &&
            !cancelling.value &&
            !(progress.value?.cancel_requested ?? false),
    );

    function stopPolling(): void {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function unsubscribe(): void {
        const echo = getEcho();

        if (subscribedChannel && echo) {
            echo.leave(subscribedChannel);
        }

        subscribedChannel = null;
    }

    function settle(next: JobProgressPayload): void {
        if (! settlePromise) {
            return;
        }

        const { resolve, reject } = settlePromise;
        settlePromise = null;
        stopPolling();
        unsubscribe();
        submitting.value = false;
        cancelling.value = false;

        if (next.status === 'completed') {
            resolve(next);
        } else if (next.status === 'cancelled') {
            reject(new Error('Cancelled'));
        } else {
            reject(new Error(next.error || options.failedMessage || 'Job failed.'));
        }
    }

    function applyProgress(next: JobProgressPayload): void {
        progress.value = next;

        if (next.error) {
            error.value = String(next.error);
        }

        if (['completed', 'failed', 'cancelled'].includes(next.status)) {
            settle(next);
        }
    }

    async function fetchProgress(id: string): Promise<JobProgressPayload> {
        const response = await fetch(options.statusUrl(id), {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        return (await parseJsonResponse(response)) as JobProgressPayload;
    }

    async function subscribeToJob(id: string): Promise<void> {
        unsubscribe();

        if (! isEchoEnabled()) {
            return;
        }

        const echo = (await initEcho()) ?? getEcho();

        if (! echo) {
            return;
        }

        subscribedChannel = options.channel(id);
        echo.private(subscribedChannel).listen(options.event, (payload: JobProgressPayload) => {
            applyProgress(payload);
        });
    }

    function startWatching(id: string): Promise<JobProgressPayload> {
        return new Promise((resolve, reject) => {
            settlePromise = { resolve, reject };
            void subscribeToJob(id);

            const tick = async () => {
                try {
                    const next = await fetchProgress(id);
                    applyProgress(next);
                } catch {
                    // Ignore transient poll errors; Pusher remains primary.
                }
            };

            void tick();
            pollTimer = setInterval(() => {
                void tick();
            }, 5000);
        });
    }

    async function startJob(
        url: string,
        body: Record<string, unknown> = {},
    ): Promise<JobProgressPayload> {
        stopPolling();
        unsubscribe();
        submitting.value = true;
        cancelling.value = false;
        error.value = null;
        progress.value = {
            job_id: '',
            action: '',
            status: 'queued',
            total: 0,
            done: 0,
            percent: 0,
            message: 'Starting…',
            error: null,
        };
        open.value = true;
        jobId.value = null;

        try {
            const response = await fetch(url, {
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

            const payload = await parseJsonResponse(response);
            const id = String(payload.job_id ?? '');

            if (! id) {
                throw new Error('No job id returned.');
            }

            jobId.value = id;
            progress.value = {
                ...(progress.value as JobProgressPayload),
                ...payload,
                job_id: id,
                message: 'Queued…',
            };

            return await startWatching(id);
        } catch (err) {
            submitting.value = false;
            const messageText = err instanceof Error ? err.message : 'Request failed.';
            error.value = messageText;
            progress.value = {
                ...(progress.value as JobProgressPayload),
                status: 'failed',
                error: messageText,
                message: 'Failed',
            };
            throw err;
        }
    }

    async function cancelJob(): Promise<void> {
        if (! jobId.value || ! canCancel.value) {
            return;
        }

        cancelling.value = true;

        try {
            const response = await fetch(options.cancelUrl(jobId.value), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...csrfHeaders(),
                },
            });

            await parseJsonResponse(response);
        } catch (err) {
            cancelling.value = false;
            error.value = err instanceof Error ? err.message : 'Cancel failed.';
        }
    }

    function close(): void {
        if (submitting.value && ! isTerminal.value) {
            return;
        }

        stopPolling();
        unsubscribe();
        settlePromise = null;
        open.value = false;
        jobId.value = null;
        progress.value = null;
        error.value = null;
        submitting.value = false;
        cancelling.value = false;
    }

    onUnmounted(() => {
        stopPolling();
        unsubscribe();
        settlePromise = null;
    });

    return {
        open,
        submitting,
        cancelling,
        progress,
        percent,
        message,
        error,
        isTerminal,
        canCancel,
        startJob,
        cancelJob,
        close,
    };
}
