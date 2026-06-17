<script setup lang="ts">
import { useLiveClock } from '@/composables/useLiveClock';

const { time, date, timezoneLabel } = useLiveClock();
</script>

<template>
    <div aria-live="polite" aria-atomic="true">
        <div
            class="flex items-center rounded-lg border border-slate-200 bg-surface-elevated px-2.5 py-1 dark:border-slate-800 sm:hidden"
        >
            <p class="font-mono text-xs font-semibold tabular-nums text-slate-900 dark:text-white">
                {{ time }}
            </p>
        </div>

        <div
            class="hidden items-center gap-3 rounded-xl border border-slate-200 bg-surface-elevated px-3 py-1.5 dark:border-slate-800 sm:flex"
        >
            <div class="text-right">
                <p class="font-mono text-sm font-semibold tabular-nums tracking-wide text-slate-900 dark:text-white">
                    {{ time }}
                </p>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                    {{ date }}
                </p>
            </div>
            <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-slate-500 dark:bg-surface-muted dark:text-slate-400">
                {{ timezoneLabel }}
            </span>
        </div>
    </div>
</template>
