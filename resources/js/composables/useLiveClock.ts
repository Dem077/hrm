import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

import { formatClockDate, formatClockTime, formatClockTimezoneLabel } from '@/lib/format';

export function useLiveClock() {
    const page = usePage<{ timezone?: string }>();
    const timezone = computed(() => page.props.timezone || import.meta.env.VITE_APP_TIMEZONE || 'UTC');
    const now = ref(new Date());

    let interval: ReturnType<typeof setInterval> | undefined;

    onMounted(() => {
        now.value = new Date();
        interval = setInterval(() => {
            now.value = new Date();
        }, 1000);
    });

    onUnmounted(() => {
        if (interval !== undefined) {
            clearInterval(interval);
        }
    });

    const time = computed(() => formatClockTime(now.value, timezone.value));
    const date = computed(() => formatClockDate(now.value, timezone.value));
    const timezoneLabel = computed(() => formatClockTimezoneLabel(now.value, timezone.value));

    return {
        time,
        date,
        timezoneLabel,
    };
}
