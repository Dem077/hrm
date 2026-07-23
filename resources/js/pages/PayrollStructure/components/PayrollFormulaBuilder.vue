<script setup lang="ts">
import { computed, ref, watch } from 'vue';

import UiButton from '@/components/ui/UiButton.vue';
import UiInput from '@/components/ui/UiInput.vue';

export type FormulaVariableOption = {
    value: string;
    label: string;
};

type FormulaToken =
    | { id: string; kind: 'variable'; value: string }
    | { id: string; kind: 'operator'; value: string }
    | { id: string; kind: 'number'; value: string };

const props = defineProps<{
    modelValue: string;
    variables: FormulaVariableOption[];
    error?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const tokens = ref<FormulaToken[]>([]);
const numberDraft = ref('');
let syncingFromModel = false;
let tokenSeq = 0;

const operators = ['+', '-', '*', '/', '(', ')'] as const;

const serialized = computed(() => tokens.value.map((token) => token.value).join(''));

const hasFormula = computed(() => tokens.value.length > 0);

function nextId(): string {
    tokenSeq += 1;
    return `t-${tokenSeq}`;
}

function serializeTokens(list: FormulaToken[]): string {
    return list.map((token) => token.value).join('');
}

function parseFormula(formula: string, allowedVariables: string[]): FormulaToken[] {
    const normalized = formula.replace(/\s+/g, '');
    if (!normalized) {
        return [];
    }

    const variablePattern = allowedVariables
        .slice()
        .sort((a, b) => b.length - a.length)
        .map((name) => name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))
        .join('|');

    const pattern = new RegExp(`(?:${variablePattern})|\\d+(?:\\.\\d+)?|[+\\-*/()]`, 'g');
    const matches = normalized.match(pattern) ?? [];
    const rebuilt = matches.join('');

    if (rebuilt !== normalized) {
        // Keep unparsable formulas as a single locked number-like token fallback by splitting safely.
        return matches.map((value) => tokenFromRaw(value, allowedVariables));
    }

    return matches.map((value) => tokenFromRaw(value, allowedVariables));
}

function tokenFromRaw(value: string, allowedVariables: string[]): FormulaToken {
    if (allowedVariables.includes(value)) {
        return { id: nextId(), kind: 'variable', value };
    }

    if (/^[+\-*/()]$/.test(value)) {
        return { id: nextId(), kind: 'operator', value };
    }

    return { id: nextId(), kind: 'number', value };
}

function pushToken(token: Omit<FormulaToken, 'id'>) {
    tokens.value = [...tokens.value, { ...token, id: nextId() }];
}

function addVariable(value: string) {
    pushToken({ kind: 'variable', value });
}

function addOperator(value: string) {
    pushToken({ kind: 'operator', value });
}

function addNumber() {
    const raw = numberDraft.value.trim();
    if (!raw || !/^\d+(\.\d+)?$/.test(raw)) {
        return;
    }

    pushToken({ kind: 'number', value: raw });
    numberDraft.value = '';
}

function removeToken(id: string) {
    tokens.value = tokens.value.filter((token) => token.id !== id);
}

function removeLast() {
    tokens.value = tokens.value.slice(0, -1);
}

function clearAll() {
    tokens.value = [];
    numberDraft.value = '';
}

watch(
    () => [props.modelValue, props.variables] as const,
    ([formula, variables]) => {
        const allowed = variables.map((option) => option.value);
        const next = parseFormula(formula ?? '', allowed);
        const nextSerialized = serializeTokens(next);

        if (nextSerialized === serializeTokens(tokens.value)) {
            return;
        }

        syncingFromModel = true;
        tokens.value = next;
        syncingFromModel = false;
    },
    { immediate: true },
);

watch(serialized, (value) => {
    if (syncingFromModel) {
        return;
    }

    if (value !== (props.modelValue ?? '')) {
        emit('update:modelValue', value);
    }
});
</script>

<template>
    <div class="space-y-3">
        <div>
            <p class="mb-1.5 text-sm font-medium text-slate-700 dark:text-slate-300">Calculation formula</p>
            <div
                class="min-h-[3.25rem] rounded-xl border border-slate-200 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-surface-elevated"
                :class="error ? 'border-red-400' : ''"
            >
                <div v-if="hasFormula" class="flex flex-wrap gap-1.5">
                    <span
                        v-for="token in tokens"
                        :key="token.id"
                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 font-mono text-xs font-medium ring-1 ring-inset"
                        :class="
                            token.kind === 'variable'
                                ? 'bg-sky-50 text-sky-800 ring-sky-200 dark:bg-sky-950/50 dark:text-sky-200 dark:ring-sky-800/60'
                                : token.kind === 'operator'
                                  ? 'bg-slate-100 text-slate-700 ring-slate-200 dark:bg-slate-800/80 dark:text-slate-300 dark:ring-slate-700'
                                  : 'bg-emerald-50 text-emerald-800 ring-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-200 dark:ring-emerald-800/60'
                        "
                    >
                        {{ token.value }}
                        <button
                            type="button"
                            class="ml-0.5 rounded-full px-1 text-[10px] opacity-60 transition hover:bg-black/10 hover:opacity-100 dark:hover:bg-white/10"
                            :title="`Remove ${token.value}`"
                            @click="removeToken(token.id)"
                        >
                            ×
                        </button>
                    </span>
                </div>
                <p v-else class="text-sm text-slate-400">Build a formula with variables, operators, and numbers.</p>
            </div>
            <p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>
            <p v-if="hasFormula" class="mt-1 font-mono text-[11px] text-slate-500">{{ serialized }}</p>
        </div>

        <div>
            <p class="mb-1.5 text-xs font-medium text-slate-500 dark:text-slate-400">Variables</p>
            <div class="flex flex-wrap gap-1.5">
                <button
                    v-for="option in variables"
                    :key="option.value"
                    type="button"
                    class="inline-flex items-center rounded-full bg-sky-50 px-2.5 py-1 font-mono text-xs font-medium text-sky-800 ring-1 ring-inset ring-sky-200 transition hover:bg-sky-100 dark:bg-sky-950/50 dark:text-sky-200 dark:ring-sky-800/60 dark:hover:bg-sky-950/80"
                    :title="option.label"
                    @click="addVariable(option.value)"
                >
                    {{ option.value }}
                </button>
            </div>
        </div>

        <div>
            <p class="mb-1.5 text-xs font-medium text-slate-500 dark:text-slate-400">Operators</p>
            <div class="flex flex-wrap gap-1.5">
                <button
                    v-for="operator in operators"
                    :key="operator"
                    type="button"
                    class="inline-flex min-w-9 items-center justify-center rounded-full bg-slate-100 px-2.5 py-1 font-mono text-xs font-medium text-slate-700 ring-1 ring-inset ring-slate-200 transition hover:bg-slate-200 dark:bg-slate-800/80 dark:text-slate-300 dark:ring-slate-700 dark:hover:bg-slate-700"
                    @click="addOperator(operator)"
                >
                    {{ operator }}
                </button>
            </div>
        </div>

        <div class="flex flex-wrap items-end gap-2">
            <div class="min-w-[8rem] flex-1">
                <UiInput v-model="numberDraft" label="Number" type="text" inputmode="decimal" placeholder="e.g. 150" />
            </div>
            <UiButton type="button" variant="secondary" size="sm" @click="addNumber">Add number</UiButton>
            <UiButton type="button" variant="ghost" size="sm" :disabled="!hasFormula" @click="removeLast">
                Undo
            </UiButton>
            <UiButton type="button" variant="ghost" size="sm" :disabled="!hasFormula" @click="clearAll">
                Clear
            </UiButton>
        </div>
    </div>
</template>
