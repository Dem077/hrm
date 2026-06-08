const appTimezone = import.meta.env.VITE_APP_TIMEZONE || 'UTC';

function parseDate(value: string): Date | null {
    const date = new Date(value.includes('T') ? value : `${value}T00:00:00`);

    return Number.isNaN(date.getTime()) ? null : date;
}

export function formatDateTime(value: string | null | undefined, timeZone: string = appTimezone): string {
    if (!value) {
        return '—';
    }

    const date = parseDate(value);

    if (!date) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
        timeZone,
    }).format(date);
}

export function formatDate(value: string | null | undefined, timeZone: string = appTimezone): string {
    if (!value) {
        return '—';
    }

    const date = parseDate(value);

    if (!date) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        timeZone,
    }).format(date);
}
