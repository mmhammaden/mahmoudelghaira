<script setup lang="ts">
import DOMPurify from 'dompurify';
import { nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps<{ name: string; modelValue?: string | null }>();
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();
const editor = ref<HTMLElement | null>(null);
const html = ref(DOMPurify.sanitize(props.modelValue ?? ''));
const sourceMode = ref(false);
watch(html, (value) => emit('update:modelValue', value));

onMounted(() => {
    if (editor.value) editor.value.innerHTML = html.value;
});

function sync(): void {
    if (!sourceMode.value && editor.value) html.value = editor.value.innerHTML;
    emit('update:modelValue', html.value);
}

function command(name: string, value?: string): void {
    editor.value?.focus();
    document.execCommand(name, false, value);
    sync();
}

function link(): void {
    const url = window.prompt('Adresse du lien (https:// ou mailto:)');
    if (url && /^(https?:\/\/|mailto:)/i.test(url)) command('createLink', url);
}

async function toggleSource(): Promise<void> {
    if (!sourceMode.value) sync();
    else emit('update:modelValue', html.value);
    sourceMode.value = !sourceMode.value;
    await nextTick();
    if (!sourceMode.value && editor.value) editor.value.innerHTML = html.value;
}
</script>

<template>
    <div class="overflow-hidden rounded-md border bg-white">
        <div
            class="flex flex-wrap gap-1 border-b bg-slate-50 p-2"
            role="toolbar"
            aria-label="Mise en forme de la description"
        >
            <button
                type="button"
                class="rounded border px-2 py-1 text-xs"
                @click="toggleSource"
            >
                Source
            </button>
            <template v-if="!sourceMode">
                <button
                    type="button"
                    class="rounded border px-2 py-1 font-bold"
                    title="Gras"
                    @click="command('bold')"
                >
                    B
                </button>
                <button
                    type="button"
                    class="rounded border px-2 py-1 italic"
                    title="Italique"
                    @click="command('italic')"
                >
                    I
                </button>
                <button
                    type="button"
                    class="rounded border px-2 py-1 underline"
                    title="Souligné"
                    @click="command('underline')"
                >
                    U
                </button>
                <button
                    type="button"
                    class="rounded border px-2 py-1 text-xs"
                    @click="command('formatBlock', 'h2')"
                >
                    Titre
                </button>
                <button
                    type="button"
                    class="rounded border px-2 py-1 text-xs"
                    @click="command('formatBlock', 'p')"
                >
                    Paragraphe
                </button>
                <button
                    type="button"
                    class="rounded border px-2 py-1"
                    title="Liste à puces"
                    @click="command('insertUnorderedList')"
                >
                    • Liste
                </button>
                <button
                    type="button"
                    class="rounded border px-2 py-1"
                    title="Liste numérotée"
                    @click="command('insertOrderedList')"
                >
                    1. Liste
                </button>
                <button
                    type="button"
                    class="rounded border px-2 py-1"
                    title="Aligner à gauche"
                    @click="command('justifyLeft')"
                >
                    ⇤
                </button>
                <button
                    type="button"
                    class="rounded border px-2 py-1"
                    title="Centrer"
                    @click="command('justifyCenter')"
                >
                    ≡
                </button>
                <button
                    type="button"
                    class="rounded border px-2 py-1"
                    title="Aligner à droite"
                    @click="command('justifyRight')"
                >
                    ⇥
                </button>
                <button
                    type="button"
                    class="rounded border px-2 py-1 text-xs"
                    @click="link"
                >
                    Lien
                </button>
                <button
                    type="button"
                    class="rounded border px-2 py-1 text-xs"
                    @click="command('unlink')"
                >
                    Supprimer le lien
                </button>
                <button
                    type="button"
                    class="rounded border px-2 py-1 text-xs"
                    @click="command('removeFormat')"
                >
                    Effacer le format
                </button>
            </template>
        </div>
        <textarea
            v-if="sourceMode"
            v-model="html"
            rows="10"
            class="w-full resize-y p-3 font-mono text-sm outline-none"
        />
        <div
            v-else
            ref="editor"
            contenteditable="true"
            class="prose prose-sm min-h-52 max-w-none p-3 outline-none"
            @input="sync"
        />
        <input type="hidden" :name="name" :value="html" />
    </div>
</template>
