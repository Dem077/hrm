import { computed, ref, watch } from 'vue';

const STORAGE_KEY = 'hrm-theme';

export type Theme = 'light' | 'dark';

function canUseDom(): boolean {
    return typeof window !== 'undefined' && typeof document !== 'undefined';
}

function getStoredTheme(): Theme {
    if (!canUseDom()) {
        return 'dark';
    }

    const stored = localStorage.getItem(STORAGE_KEY);

    return stored === 'light' || stored === 'dark' ? stored : 'dark';
}

function applyTheme(theme: Theme): void {
    if (!canUseDom()) {
        return;
    }

    const root = document.documentElement;

    root.classList.toggle('dark', theme === 'dark');
    root.style.colorScheme = theme;

    const meta = document.querySelector('meta[name="theme-color"]');

    if (meta) {
        meta.setAttribute('content', theme === 'dark' ? '#070b12' : '#f1f5f9');
    }
}

const theme = ref<Theme>(getStoredTheme());

export function useTheme() {
    watch(
        theme,
        (value) => {
            if (canUseDom()) {
                localStorage.setItem(STORAGE_KEY, value);
            }
            applyTheme(value);
        },
        { immediate: true },
    );

    const isDark = computed(() => theme.value === 'dark');

    function toggleTheme(): void {
        theme.value = theme.value === 'dark' ? 'light' : 'dark';
    }

    function setTheme(value: Theme): void {
        theme.value = value;
    }

    return {
        theme,
        isDark,
        toggleTheme,
        setTheme,
    };
}

export function initTheme(): void {
    if (!canUseDom()) {
        return;
    }

    applyTheme(getStoredTheme());
}
