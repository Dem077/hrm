type EchoClient = {
    private: (channel: string) => {
        listen: (event: string, callback: (payload: never) => void) => unknown;
    };
    leave: (channel: string) => void;
};

type BrowserWindow = {
    Pusher: unknown;
    Echo?: EchoClient;
};

export function isEchoEnabled(): boolean {
    if (import.meta.env.SSR) {
        return false;
    }

    return Boolean(import.meta.env.VITE_PUSHER_APP_KEY);
}

/** @deprecated Use isEchoEnabled() */
export const echoEnabled = ! import.meta.env.SSR && Boolean(import.meta.env.VITE_PUSHER_APP_KEY);

let echoInstance: EchoClient | null = null;
let initPromise: Promise<EchoClient | null> | null = null;

function csrfHeaders(): Record<string, string> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

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

export async function initEcho(): Promise<EchoClient | null> {
    if (import.meta.env.SSR) {
        return null;
    }

    if (echoInstance) {
        return echoInstance;
    }

    if (initPromise) {
        return initPromise;
    }

    const key = import.meta.env.VITE_PUSHER_APP_KEY;

    if (! key) {
        return null;
    }

    initPromise = (async () => {
        const [{ default: Echo }, { default: Pusher }] = await Promise.all([
            import('laravel-echo'),
            import('pusher-js'),
        ]);

        const browserWindow = globalThis as typeof globalThis & BrowserWindow;
        browserWindow.Pusher = Pusher;

        echoInstance = new Echo({
            broadcaster: 'pusher',
            key,
            cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1',
            forceTLS: true,
            authorizer: (channel: { name: string }) => ({
                authorize: (
                    socketId: string,
                    callback: (error: Error | null, data: unknown) => void,
                ) => {
                    fetch('/broadcasting/auth', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: csrfHeaders(),
                        body: JSON.stringify({
                            socket_id: socketId,
                            channel_name: channel.name,
                        }),
                    })
                        .then(async (response) => {
                            if (! response.ok) {
                                throw new Error(`Broadcast auth failed (${response.status})`);
                            }

                            return response.json();
                        })
                        .then((data) => callback(null, data))
                        .catch((error: Error) => callback(error, null));
                },
            }),
        }) as unknown as EchoClient;

        browserWindow.Echo = echoInstance;

        return echoInstance;
    })();

    return initPromise;
}

export function getEcho(): EchoClient | null {
    return echoInstance;
}

export default null;
