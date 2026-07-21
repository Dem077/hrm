import { onMounted, ref, watch } from 'vue';

const STORAGE_KEY = 'hrm-sidebar-collapsed';
const NAV_GROUP_STORAGE_PREFIX = 'hrm-nav-group-';

function canUseLocalStorage(): boolean {
    return typeof window !== 'undefined' && typeof localStorage !== 'undefined';
}

const collapsed = ref(canUseLocalStorage() && localStorage.getItem(STORAGE_KEY) === '1');
const mobileOpen = ref(false);
const expandedNavGroups = ref<Record<string, boolean>>({});

function readGroupExpanded(key: string): boolean {
    if (!canUseLocalStorage()) {
        return true;
    }

    return localStorage.getItem(`${NAV_GROUP_STORAGE_PREFIX}${key}-expanded`) !== '0';
}

function persistGroupExpanded(key: string, value: boolean): void {
    if (!canUseLocalStorage()) {
        return;
    }

    localStorage.setItem(`${NAV_GROUP_STORAGE_PREFIX}${key}-expanded`, value ? '1' : '0');
}

export function useSidebar() {
    onMounted(() => {
        if (!canUseLocalStorage()) {
            return;
        }

        collapsed.value = localStorage.getItem(STORAGE_KEY) === '1';
    });

    watch(collapsed, (value) => {
        if (!canUseLocalStorage()) {
            return;
        }

        localStorage.setItem(STORAGE_KEY, value ? '1' : '0');
    });

    function ensureNavGroup(key: string): void {
        if (expandedNavGroups.value[key] === undefined) {
            expandedNavGroups.value[key] = readGroupExpanded(key);
        }
    }

    function isNavGroupExpanded(key: string): boolean {
        ensureNavGroup(key);

        return expandedNavGroups.value[key] ?? true;
    }

    function setNavGroupExpanded(key: string, value: boolean): void {
        expandedNavGroups.value[key] = value;
        persistGroupExpanded(key, value);
    }

    function toggleNavGroupExpanded(key: string): void {
        setNavGroupExpanded(key, !isNavGroupExpanded(key));
    }

    function showNavGroupItems(key: string): boolean {
        return isNavGroupExpanded(key) || collapsed.value;
    }

    function toggleCollapsed(): void {
        collapsed.value = !collapsed.value;
    }

    function openMobile(): void {
        mobileOpen.value = true;
    }

    function closeMobile(): void {
        mobileOpen.value = false;
    }

    return {
        collapsed,
        mobileOpen,
        toggleCollapsed,
        isNavGroupExpanded,
        setNavGroupExpanded,
        toggleNavGroupExpanded,
        showNavGroupItems,
        openMobile,
        closeMobile,
    };
}
