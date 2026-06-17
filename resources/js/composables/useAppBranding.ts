import type { AppBranding } from '@/types/branding';

export function hexToRgba(hex: string, alpha: number): string {
    const normalized = hex.replace('#', '');

    if (normalized.length !== 6) {
        return `rgba(0, 0, 0, ${alpha})`;
    }

    const red = Number.parseInt(normalized.slice(0, 2), 16);
    const green = Number.parseInt(normalized.slice(2, 4), 16);
    const blue = Number.parseInt(normalized.slice(4, 6), 16);

    return `rgba(${red}, ${green}, ${blue}, ${alpha})`;
}

export function loginPanelGradient(branding: AppBranding, isDark: boolean): string {
    const primaryAlpha = isDark ? 0.2 : 0.15;
    const secondaryAlpha = isDark ? 0.12 : 0.1;

    const primary = hexToRgba(branding.brand_color_500, primaryAlpha);
    const secondary = hexToRgba(branding.brand_color_400, secondaryAlpha);

    return `radial-gradient(circle at top right, ${primary}, transparent 45%), radial-gradient(circle at bottom left, ${secondary}, transparent 40%)`;
}

export function applyAppBranding(branding: AppBranding): void {
    const root = document.documentElement;

    root.style.setProperty('--color-brand-400', branding.brand_color_400);
    root.style.setProperty('--color-brand-500', branding.brand_color_500);
    root.style.setProperty('--color-brand-600', branding.brand_color_600);
    root.style.setProperty('--color-brand-700', branding.brand_color_700);
}

export function appInitial(branding: AppBranding): string {
    return branding.app_name.trim().charAt(0).toUpperCase() || 'H';
}
