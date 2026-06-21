let appTimezone = import.meta.env.VITE_APP_TIMEZONE || 'UTC';

export function setAppTimezone(timezone: string): void {
    appTimezone = timezone;
}

export function getAppTimezone(): string {
    return appTimezone;
}
