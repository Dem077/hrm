<script setup lang="ts">
import Underline from '@tiptap/extension-underline';
import Placeholder from '@tiptap/extension-placeholder';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { onBeforeUnmount, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        id?: string;
        label: string;
        modelValue?: string | null;
        error?: string;
        hint?: string;
        required?: boolean;
        placeholder?: string;
        disabled?: boolean;
        minHeightClass?: string;
    }>(),
    {
        modelValue: '',
        required: false,
        disabled: false,
        minHeightClass: 'min-h-[9rem]',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const editor = useEditor({
    extensions: [
        StarterKit.configure({
            heading: { levels: [2, 3] },
        }),
        Underline,
        Placeholder.configure({
            placeholder: props.placeholder ?? '',
        }),
    ],
    content: props.modelValue || '',
    editable: !props.disabled,
    editorProps: {
        attributes: {
            class: `rich-text-editor-body px-3.5 py-2.5 text-sm text-slate-900 outline-none dark:text-slate-100 ${props.minHeightClass}`,
        },
    },
    onUpdate: ({ editor: current }) => {
        emit('update:modelValue', current.getHTML());
    },
});

watch(
    () => props.modelValue,
    (value) => {
        if (!editor.value) {
            return;
        }

        const next = value || '';
        if (next === editor.value.getHTML()) {
            return;
        }

        editor.value.commands.setContent(next, { emitUpdate: false });
    },
);

watch(
    () => props.disabled,
    (disabled) => {
        editor.value?.setEditable(!disabled);
    },
);

onBeforeUnmount(() => {
    editor.value?.destroy();
});
</script>

<template>
    <div>
        <label :for="id" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
            {{ label }}
            <span v-if="required" class="text-red-500">*</span>
        </label>

        <div
            class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-surface-elevated"
            :class="error ? 'border-red-300 dark:border-red-700' : ''"
        >
            <div
                v-if="editor"
                class="flex flex-wrap items-center gap-0.5 border-b border-slate-200 bg-slate-50 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-800/60"
            >
                <button
                    type="button"
                    title="Bold"
                    class="rounded-md px-2 py-1 text-xs font-bold transition hover:bg-white disabled:opacity-40 dark:hover:bg-slate-700"
                    :class="
                        editor.isActive('bold')
                            ? 'bg-white text-brand-700 shadow-sm dark:bg-slate-700 dark:text-brand-300'
                            : 'text-slate-600 dark:text-slate-300'
                    "
                    :disabled="disabled"
                    @click="editor.chain().focus().toggleBold().run()"
                >
                    B
                </button>
                <button
                    type="button"
                    title="Italic"
                    class="rounded-md px-2 py-1 text-xs italic transition hover:bg-white disabled:opacity-40 dark:hover:bg-slate-700"
                    :class="
                        editor.isActive('italic')
                            ? 'bg-white text-brand-700 shadow-sm dark:bg-slate-700 dark:text-brand-300'
                            : 'text-slate-600 dark:text-slate-300'
                    "
                    :disabled="disabled"
                    @click="editor.chain().focus().toggleItalic().run()"
                >
                    I
                </button>
                <button
                    type="button"
                    title="Underline"
                    class="rounded-md px-2 py-1 text-xs underline transition hover:bg-white disabled:opacity-40 dark:hover:bg-slate-700"
                    :class="
                        editor.isActive('underline')
                            ? 'bg-white text-brand-700 shadow-sm dark:bg-slate-700 dark:text-brand-300'
                            : 'text-slate-600 dark:text-slate-300'
                    "
                    :disabled="disabled"
                    @click="editor.chain().focus().toggleUnderline().run()"
                >
                    U
                </button>

                <span class="mx-1 h-4 w-px bg-slate-200 dark:bg-slate-600" />

                <button
                    type="button"
                    title="Heading"
                    class="rounded-md px-2 py-1 text-xs font-semibold transition hover:bg-white disabled:opacity-40 dark:hover:bg-slate-700"
                    :class="
                        editor.isActive('heading', { level: 2 })
                            ? 'bg-white text-brand-700 shadow-sm dark:bg-slate-700 dark:text-brand-300'
                            : 'text-slate-600 dark:text-slate-300'
                    "
                    :disabled="disabled"
                    @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
                >
                    H
                </button>
                <button
                    type="button"
                    title="Bullet list"
                    class="rounded-md px-2 py-1 text-xs transition hover:bg-white disabled:opacity-40 dark:hover:bg-slate-700"
                    :class="
                        editor.isActive('bulletList')
                            ? 'bg-white text-brand-700 shadow-sm dark:bg-slate-700 dark:text-brand-300'
                            : 'text-slate-600 dark:text-slate-300'
                    "
                    :disabled="disabled"
                    @click="editor.chain().focus().toggleBulletList().run()"
                >
                    • List
                </button>
                <button
                    type="button"
                    title="Numbered list"
                    class="rounded-md px-2 py-1 text-xs transition hover:bg-white disabled:opacity-40 dark:hover:bg-slate-700"
                    :class="
                        editor.isActive('orderedList')
                            ? 'bg-white text-brand-700 shadow-sm dark:bg-slate-700 dark:text-brand-300'
                            : 'text-slate-600 dark:text-slate-300'
                    "
                    :disabled="disabled"
                    @click="editor.chain().focus().toggleOrderedList().run()"
                >
                    1. List
                </button>

                <span class="mx-1 h-4 w-px bg-slate-200 dark:bg-slate-600" />

                <button
                    type="button"
                    title="Clear formatting"
                    class="rounded-md px-2 py-1 text-xs text-slate-600 transition hover:bg-white disabled:opacity-40 dark:text-slate-300 dark:hover:bg-slate-700"
                    :disabled="disabled"
                    @click="editor.chain().focus().clearNodes().unsetAllMarks().run()"
                >
                    Clear
                </button>
            </div>

            <EditorContent :id="id" :editor="editor" />
        </div>

        <p v-if="hint && !error" class="mt-1.5 text-xs text-slate-500">{{ hint }}</p>
        <p v-if="error" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ error }}</p>
    </div>
</template>

<style>
.tiptap p.is-editor-empty:first-child::before {
    color: #94a3b8;
    content: attr(data-placeholder);
    float: left;
    height: 0;
    pointer-events: none;
}

.rich-text-editor-body ul,
.tiptap ul {
    list-style: disc;
    padding-left: 1.25rem;
    margin: 0.35rem 0;
}

.rich-text-editor-body ol,
.tiptap ol {
    list-style: decimal;
    padding-left: 1.25rem;
    margin: 0.35rem 0;
}

.rich-text-editor-body h2,
.tiptap h2 {
    font-size: 1.05rem;
    font-weight: 600;
    margin: 0.4rem 0;
}

.rich-text-editor-body h3,
.tiptap h3 {
    font-size: 0.95rem;
    font-weight: 600;
    margin: 0.3rem 0;
}

.rich-text-editor-body p,
.tiptap p {
    margin: 0.25rem 0;
}

.rich-text-editor-body strong,
.tiptap strong {
    font-weight: 700;
}

.rich-text-editor-body em,
.tiptap em {
    font-style: italic;
}

.rich-text-editor-body u,
.tiptap u {
    text-decoration: underline;
}
</style>
