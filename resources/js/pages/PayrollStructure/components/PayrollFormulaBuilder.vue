<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';

import UiButton from '@/components/ui/UiButton.vue';

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
const caretIndex = ref(0);
const draft = ref('');
const inputRef = ref<HTMLInputElement | null>(null);
const dragFromIndex = ref<number | null>(null);
const dragOverIndex = ref<number | null>(null);

let syncingFromModel = false;
let tokenSeq = 0;

const operators = ['+', '-', '*', '/', '(', ')'] as const;
const operatorSet = new Set<string>(operators);

const serialized = computed(() => tokens.value.map((token) => token.value).join(''));
const hasFormula = computed(() => tokens.value.length > 0 || draft.value.trim() !== '');
const preview = computed(() => `${serialized.value}${draft.value}`);

const variableLabelByValue = computed(() => {
    const map = new Map<string, string>();

    for (const option of props.variables) {
        map.set(option.value, option.label || option.value);
    }

    return map;
});

function displayToken(token: FormulaToken): string {
    if (token.kind === 'variable') {
        return variableLabelByValue.value.get(token.value) ?? token.value;
    }

    return token.value;
}

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

function focusInput() {
    nextTick(() => inputRef.value?.focus());
}

function bindInputRef(el: unknown) {
    inputRef.value = (el as HTMLInputElement | null) ?? null;
}

function setCaret(index: number) {
    commitDraft();
    caretIndex.value = Math.max(0, Math.min(index, tokens.value.length));
    focusInput();
}

function insertTokensAtCaret(newTokens: Omit<FormulaToken, 'id'>[]) {
    if (newTokens.length === 0) {
        return;
    }

    const created = newTokens.map((token) => ({ ...token, id: nextId() }));
    const next = [...tokens.value];
    next.splice(caretIndex.value, 0, ...created);
    tokens.value = next;
    caretIndex.value += created.length;
}

function commitDraft(): boolean {
    const raw = draft.value.trim();
    if (!raw) {
        return false;
    }

    if (!/^\d+(\.\d+)?$/.test(raw)) {
        return false;
    }

    insertTokensAtCaret([{ kind: 'number', value: raw }]);
    draft.value = '';
    return true;
}

function addVariable(value: string) {
    commitDraft();
    insertTokensAtCaret([{ kind: 'variable', value }]);
    focusInput();
}

function addOperator(value: string) {
    commitDraft();
    insertTokensAtCaret([{ kind: 'operator', value }]);
    focusInput();
}

function removeToken(id: string) {
    const index = tokens.value.findIndex((token) => token.id === id);
    if (index < 0) {
        return;
    }

    tokens.value = tokens.value.filter((token) => token.id !== id);
    if (caretIndex.value > index) {
        caretIndex.value -= 1;
    } else if (caretIndex.value > tokens.value.length) {
        caretIndex.value = tokens.value.length;
    }
    focusInput();
}

function removeLast() {
    if (draft.value !== '') {
        draft.value = draft.value.slice(0, -1);
        focusInput();
        return;
    }

    if (caretIndex.value > 0) {
        const next = [...tokens.value];
        next.splice(caretIndex.value - 1, 1);
        tokens.value = next;
        caretIndex.value -= 1;
    }
    focusInput();
}

function clearAll() {
    tokens.value = [];
    draft.value = '';
    caretIndex.value = 0;
    focusInput();
}

function onCanvasClick() {
    setCaret(tokens.value.length);
}

function onDraftInput(event: Event) {
    const value = (event.target as HTMLInputElement).value;
    draft.value = value.replace(/[^\d.]/g, '');
}

function onDraftKeydown(event: KeyboardEvent) {
    if (operatorSet.has(event.key)) {
        event.preventDefault();
        commitDraft();
        insertTokensAtCaret([{ kind: 'operator', value: event.key }]);
        return;
    }

    if (event.key === 'Enter') {
        event.preventDefault();
        commitDraft();
        return;
    }

    if (event.key === 'Backspace' && draft.value === '') {
        event.preventDefault();
        removeLast();
        return;
    }

    if (event.key === 'ArrowLeft' && draft.value === '' && (inputRef.value?.selectionStart ?? 0) === 0) {
        event.preventDefault();
        setCaret(Math.max(0, caretIndex.value - 1));
        return;
    }

    if (event.key === 'ArrowRight' && draft.value === '') {
        event.preventDefault();
        setCaret(Math.min(tokens.value.length, caretIndex.value + 1));
        return;
    }

    if (event.key === ' ' || event.key === ',') {
        event.preventDefault();
        commitDraft();
    }
}

function onDraftBlur() {
    commitDraft();
}

function onDragStart(index: number, event: DragEvent) {
    dragFromIndex.value = index;
    event.dataTransfer?.setData('text/plain', String(index));
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
}

function onDragOverSlot(index: number, event: DragEvent) {
    event.preventDefault();
    dragOverIndex.value = index;
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
}

function onDragLeaveSlot(index: number) {
    if (dragOverIndex.value === index) {
        dragOverIndex.value = null;
    }
}

function moveToken(from: number, to: number) {
    if (from === to || from < 0 || from >= tokens.value.length) {
        return;
    }

    commitDraft();
    const next = [...tokens.value];
    const [moved] = next.splice(from, 1);
    if (!moved) {
        return;
    }

    let insertAt = to;
    if (from < to) {
        insertAt -= 1;
    }
    insertAt = Math.max(0, Math.min(insertAt, next.length));
    next.splice(insertAt, 0, moved);
    tokens.value = next;
    caretIndex.value = insertAt + 1;
    focusInput();
}

function onDropSlot(index: number, event: DragEvent) {
    event.preventDefault();
    const from = dragFromIndex.value;
    dragFromIndex.value = null;
    dragOverIndex.value = null;

    if (from === null) {
        return;
    }

    moveToken(from, index);
}

function onDragEnd() {
    dragFromIndex.value = null;
    dragOverIndex.value = null;
}

watch(
    () => [props.modelValue, props.variables] as const,
    ([formula, variables]) => {
        const allowed = variables.map((option) => option.value);
        const next = parseFormula(formula ?? '', allowed);
        const nextSerialized = serializeTokens(next);

        if (nextSerialized === serializeTokens(tokens.value) && draft.value === '') {
            return;
        }

        if (document.activeElement === inputRef.value && draft.value !== '') {
            return;
        }

        syncingFromModel = true;
        tokens.value = next;
        caretIndex.value = next.length;
        draft.value = '';
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
                class="min-h-[3.25rem] cursor-text rounded-xl border border-slate-200 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-surface-elevated"
                :class="error ? 'border-red-400' : ''"
                @click="onCanvasClick"
            >
                <div class="flex flex-wrap items-center gap-1">
                    <template v-for="(token, index) in tokens" :key="token.id">
                        <!-- Drop / insert slot before each pill -->
                        <span
                            class="inline-flex h-7 w-1.5 shrink-0 items-center justify-center rounded-full transition"
                            :class="
                                dragOverIndex === index
                                    ? 'bg-brand-500'
                                    : 'bg-transparent hover:bg-slate-200 dark:hover:bg-slate-600'
                            "
                            title="Insert here"
                            @click.stop="setCaret(index)"
                            @dragover="onDragOverSlot(index, $event)"
                            @dragleave="onDragLeaveSlot(index)"
                            @drop="onDropSlot(index, $event)"
                        />

                        <input
                            v-if="caretIndex === index"
                            :key="`caret-${index}`"
                            :ref="bindInputRef"
                            :value="draft"
                            type="text"
                            inputmode="decimal"
                            class="min-w-[1.75rem] max-w-[7rem] border-0 bg-transparent px-0.5 py-1 font-mono text-sm text-slate-900 outline-none placeholder:text-slate-400 dark:text-slate-100"
                            :placeholder="tokens.length === 0 ? 'Number' : ''"
                            @click.stop
                            @input="onDraftInput"
                            @keydown="onDraftKeydown"
                            @blur="onDraftBlur"
                        />

                        <span
                            draggable="true"
                            class="inline-flex cursor-grab items-center gap-1 rounded-full px-2.5 py-1 font-mono text-xs font-medium ring-1 ring-inset active:cursor-grabbing"
                            :class="[
                                token.kind === 'variable'
                                    ? 'bg-sky-50 text-sky-800 ring-sky-200 dark:bg-sky-950/50 dark:text-sky-200 dark:ring-sky-800/60'
                                    : token.kind === 'operator'
                                      ? 'bg-slate-100 text-slate-700 ring-slate-200 dark:bg-slate-800/80 dark:text-slate-300 dark:ring-slate-700'
                                      : 'bg-emerald-50 text-emerald-800 ring-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-200 dark:ring-emerald-800/60',
                                dragFromIndex === index ? 'opacity-40' : '',
                            ]"
                            title="Drag to reorder"
                            @click.stop="setCaret(index + 1)"
                            @dragstart="onDragStart(index, $event)"
                            @dragend="onDragEnd"
                        >
                            <span class="pointer-events-none select-none">{{ displayToken(token) }}</span>
                            <button
                                type="button"
                                class="ml-0.5 rounded-full px-1 text-[10px] opacity-60 transition hover:bg-black/10 hover:opacity-100 dark:hover:bg-white/10"
                                :title="`Remove ${displayToken(token)}`"
                                @click.stop="removeToken(token.id)"
                            >
                                ×
                            </button>
                        </span>
                    </template>

                    <!-- Trailing insert / drop slot -->
                    <span
                        class="inline-flex h-7 w-1.5 shrink-0 items-center justify-center rounded-full transition"
                        :class="
                            dragOverIndex === tokens.length
                                ? 'bg-brand-500'
                                : 'bg-transparent hover:bg-slate-200 dark:hover:bg-slate-600'
                        "
                        title="Insert at end"
                        @click.stop="setCaret(tokens.length)"
                        @dragover="onDragOverSlot(tokens.length, $event)"
                        @dragleave="onDragLeaveSlot(tokens.length)"
                        @drop="onDropSlot(tokens.length, $event)"
                    />

                    <input
                        v-if="caretIndex === tokens.length"
                        :key="`caret-end`"
                        :ref="bindInputRef"
                        :value="draft"
                        type="text"
                        inputmode="decimal"
                        class="min-w-[4rem] flex-1 border-0 bg-transparent px-0.5 py-1 font-mono text-sm text-slate-900 outline-none placeholder:text-slate-400 dark:text-slate-100"
                        :placeholder="tokens.length === 0 ? 'Type a number or pick a variable…' : 'Type a number…'"
                        @click.stop
                        @input="onDraftInput"
                        @keydown="onDraftKeydown"
                        @blur="onDraftBlur"
                    />
                </div>
            </div>
            <p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>
            <p class="mt-1 text-xs text-slate-500">
                Click between pills to type a number, use + − * / keys, and drag pills to reorder.
            </p>
            <p v-if="hasFormula" class="mt-1 font-mono text-[11px] text-slate-500">{{ preview }}</p>
        </div>

        <div>
            <p class="mb-1.5 text-xs font-medium text-slate-500 dark:text-slate-400">Variables</p>
            <div class="flex flex-wrap gap-1.5">
                <button
                    v-for="option in variables"
                    :key="option.value"
                    type="button"
                    class="inline-flex items-center rounded-full bg-sky-50 px-2.5 py-1 text-xs font-medium text-sky-800 ring-1 ring-inset ring-sky-200 transition hover:bg-sky-100 dark:bg-sky-950/50 dark:text-sky-200 dark:ring-sky-800/60 dark:hover:bg-sky-950/80"
                    :title="option.value"
                    @click="addVariable(option.value)"
                >
                    {{ option.label || option.value }}
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
                <UiButton type="button" variant="ghost" size="sm" :disabled="!hasFormula" @click="removeLast">
                    Undo
                </UiButton>
                <UiButton type="button" variant="ghost" size="sm" :disabled="!hasFormula" @click="clearAll">
                    Clear
                </UiButton>
            </div>
        </div>
    </div>
</template>
