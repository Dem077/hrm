import { onMounted, ref, watch } from 'vue';

const STORAGE_KEY = 'hrm-sidebar-collapsed';

const collapsed = ref(
    typeof window !== 'undefined' && localStorage.getItem(STORAGE_KEY) === '1',
);
const mobileOpen = ref(false);

export function useSidebar() {
    onMounted(() => {
        collapsed.value = localStorage.getItem(STORAGE_KEY) === '1';
    });

    watch(collapsed, (value) => {
        localStorage.setItem(STORAGE_KEY, value ? '1' : '0');
    });

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
        openMobile,
        closeMobile,
    };
}
