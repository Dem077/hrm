import { getAppTimezone } from '@/lib/timezone';

function resolveTimezone(timezone?: string): string {
    return timezone || getAppTimezone();
}

export function formatClockTime(date: Date, timezone?: string): string {
    return new Intl.DateTimeFormat('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
        timeZone: resolveTimezone(timezone),
    }).format(date);
}

export function formatClockDate(date: Date, timezone?: string): string {
    return new Intl.DateTimeFormat('en-GB', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: resolveTimezone(timezone),
    }).format(date);
}

export function formatClockTimezoneLabel(date: Date, timezone?: string): string {
    const parts = new Intl.DateTimeFormat('en-GB', {
        timeZone: resolveTimezone(timezone),
        timeZoneName: 'short',
    }).formatToParts(date);

    return parts.find((part) => part.type === 'timeZoneName')?.value ?? resolveTimezone(timezone);
}

const isoDatePattern = /^(\d{4})-(\d{2})-(\d{2})$/;

function parseDate(value: string): Date | null {
    const date = new Date(value.includes('T') ? value : `${value}T00:00:00`);

    return Number.isNaN(date.getTime()) ? null : date;
}

function dateParts(date: Date, timeZone: string): { day: string; month: string; year: string } {
    const parts = new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        timeZone,
    }).formatToParts(date);

    const value = (type: Intl.DateTimeFormatPartTypes) =>
        parts.find((part) => part.type === type)?.value ?? '';

    return {
        day: value('day'),
        month: value('month'),
        year: value('year'),
    };
}

function formatDateValue(date: Date, timeZone: string): string {
    const { day, month, year } = dateParts(date, timeZone);

    return `${day}/${month}/${year}`;
}

export function formatIsoDateOnly(value: string): string | null {
    const match = value.match(isoDatePattern);

    if (!match) {
        return null;
    }

    return `${match[3]}/${match[2]}/${match[1]}`;
}

export function displayToIso(value: string): string | null {
    const match = value.trim().match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);

    if (!match) {
        return null;
    }

    const day = Number(match[1]);
    const month = Number(match[2]);
    const year = Number(match[3]);
    const iso = `${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    const parsed = parseDate(iso);

    if (!parsed || parsed.getFullYear() !== year || parsed.getMonth() + 1 !== month || parsed.getDate() !== day) {
        return null;
    }

    return iso;
}

export function formatDate(value: string | null | undefined, timeZone: string = getAppTimezone()): string {
    if (!value) {
        return '—';
    }

    if (!value.includes('T')) {
        const isoFormatted = formatIsoDateOnly(value);

        if (isoFormatted) {
            return isoFormatted;
        }
    }

    const date = parseDate(value);

    if (!date) {
        return '—';
    }

    return formatDateValue(date, timeZone);
}

export function formatTime(value: string | null | undefined, timeZone: string = getAppTimezone()): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
        timeZone,
    }).format(date);
}

export function formatDateTime(value: string | null | undefined, timeZone: string = getAppTimezone()): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    const time = new Intl.DateTimeFormat('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
        timeZone,
    }).format(date);

    return `${formatDateValue(date, timeZone)} ${time}`;
}

export function formatDateRange(
    from: string | null | undefined,
    to: string | null | undefined,
    timeZone: string = getAppTimezone(),
): string {
    return `${formatDate(from, timeZone)} – ${formatDate(to, timeZone)}`;
}
