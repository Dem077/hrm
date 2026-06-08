import { computed, ref, watch } from 'vue';

const STORAGE_KEY = 'hrm-theme';

export type Theme = 'light' | 'dark';

function getStoredTheme(): Theme {
    if (typeof window === 'undefined') {
        return 'dark';
    }

    const stored = localStorage.getItem(STORAGE_KEY);

    return stored === 'light' || stored === 'dark' ? stored : 'dark';
}

function applyTheme(theme: Theme): void {
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
            localStorage.setItem(STORAGE_KEY, value);
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
    applyTheme(getStoredTheme());
}
