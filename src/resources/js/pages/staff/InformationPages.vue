<script setup lang="ts">
import { Head, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { infoPages, type InfoPage } from '@/content/infoPages';
import { infoPagesAr } from '@/content/infoPagesAr';

type PageRow = {
    id: number;
    slug: string;
    titleFr: string;
    titleAr: string | null;
    bodyFr: string;
    bodyAr: string | null;
    published: boolean;
    system: boolean;
};

const props = defineProps<{ pages: PageRow[] }>();
setLayoutProps({
    breadcrumbs: [{ title: 'Infos', href: '/staff/information-pages' }],
});

const editingId = ref<number | null>(null);
const editorOpen = ref(false);
const form = useForm({
    slug: '',
    title_fr: '',
    title_ar: '',
    body_fr: '',
    body_ar: '',
    is_published: false,
});

function asText(page: InfoPage): string {
    return page.blocks
        .map((block) =>
            block.type === 'list'
                ? block.items.map((item) => `• ${item}`).join('\n')
                : block.text,
        )
        .join('\n\n');
}

const rows = computed(() => {
    const stored = new Map(props.pages.map((page) => [page.slug, page]));
    const defaults = Object.entries(infoPages).map(
        ([slug, page]) =>
            stored.get(slug) ?? {
                id: 0,
                slug,
                titleFr: page.title,
                titleAr: infoPagesAr[slug]?.title ?? null,
                bodyFr: asText(page),
                bodyAr: infoPagesAr[slug] ? asText(infoPagesAr[slug]) : null,
                published: true,
                system: true,
            },
    );
    const custom = props.pages.filter((page) => !infoPages[page.slug]);
    return [...defaults, ...custom];
});

function edit(page: PageRow): void {
    editingId.value = page.id;
    form.slug = page.slug;
    form.title_fr = page.titleFr;
    form.title_ar = page.titleAr ?? '';
    form.body_fr = page.bodyFr;
    form.body_ar = page.bodyAr ?? '';
    form.is_published = page.published;
    form.clearErrors();
    editorOpen.value = true;
}

function create(): void {
    reset();
    editorOpen.value = true;
}

function reset(): void {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    editorOpen.value = false;
}

function submit(): void {
    const options = { preserveScroll: true, onSuccess: reset };
    if (editingId.value)
        form.put(`/staff/information-pages/${editingId.value}`, options);
    else form.post('/staff/information-pages', options);
}

function remove(page: PageRow): void {
    if (
        page.id &&
        !page.system &&
        window.confirm(`Supprimer « ${page.titleFr} » ?`)
    ) {
        router.delete(`/staff/information-pages/${page.id}`, {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head title="Pages d'information" />
    <div class="flex flex-1 flex-col gap-6 bg-white p-5 md:p-8">
        <div class="flex items-end justify-between gap-4">
            <h1 class="text-3xl font-normal text-[#5a5c69]">Avis speciale</h1>
            <Button size="sm" @click="create"><Plus class="size-4" /> Ajouter une info...</Button>
        </div>
        <div class="overflow-hidden rounded-md border border-[#e3e6f0] shadow-[0_.15rem_1.75rem_0_rgba(58,59,69,.12)]">
            <table class="w-full text-base">
                <thead class="bg-[#f8f9fc] text-[#4e73df]"><tr><th class="px-4 py-3 text-start">#</th><th class="px-4 py-3 text-start">Libellé</th><th class="px-4 py-3 text-start">État</th><th class="px-4 py-3 text-start">Actions</th></tr></thead>
                <tbody class="divide-y divide-[#e3e6f0]">
                    <tr v-for="page in rows" :key="page.slug">
                        <td class="px-4 py-3">{{ page.id || '—' }}</td>
                        <td class="px-4 py-3"><strong>{{ page.titleFr }}</strong><div class="text-sm text-[#858796]">/beta/{{ page.slug }}</div></td>
                        <td class="px-4 py-3">{{ page.published ? 'Publiée' : 'Brouillon' }}</td>
                        <td class="px-4 py-3"><div class="flex gap-0"><button type="button" class="legacy-action bg-[#e74a3b]" title="Modifier" @click="edit(page)"><Pencil class="size-4" /></button><button v-if="page.id && !page.system" type="button" class="legacy-action bg-[#e74a3b]" title="Supprimer" @click="remove(page)"><Trash2 class="size-4" /></button></div></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Dialog :open="editorOpen" @update:open="(open) => { if (!open) reset() }">
            <DialogContent class="max-h-[90vh] grid-cols-[minmax(0,1fr)] overflow-y-auto sm:max-w-2xl">
                <DialogHeader><DialogTitle>{{ editingId ? 'Modifier la page' : 'Ajouter une info...' }}</DialogTitle><DialogDescription>Contenu français et arabe de la page.</DialogDescription></DialogHeader>
                <form class="space-y-4" @submit.prevent="submit">
            <label class="grid gap-1 text-sm"
                >Adresse
                <input
                    v-model="form.slug"
                    required
                    maxlength="80"
                    pattern="[a-z0-9]+(?:_[a-z0-9]+)*"
                    class="rounded-md border px-3 py-2"
                    :disabled="
                        !!editingId &&
                        rows.find((p) => p.id === editingId)?.system
                    "
                />
                <InputError :message="form.errors.slug" />
            </label>
            <label class="grid gap-1 text-sm"
                >Titre français
                <input
                    v-model="form.title_fr"
                    required
                    maxlength="200"
                    class="rounded-md border px-3 py-2"
                />
                <InputError :message="form.errors.title_fr" />
            </label>
            <label class="grid gap-1 text-sm"
                >Contenu français
                <textarea
                    v-model="form.body_fr"
                    required
                    maxlength="20000"
                    rows="10"
                    class="rounded-md border px-3 py-2"
                />
                <InputError :message="form.errors.body_fr" />
            </label>
            <label class="grid gap-1 text-sm"
                >Titre arabe
                <input
                    v-model="form.title_ar"
                    dir="rtl"
                    maxlength="200"
                    class="rounded-md border px-3 py-2"
                />
                <InputError :message="form.errors.title_ar" />
            </label>
            <label class="grid gap-1 text-sm"
                >Contenu arabe
                <textarea
                    v-model="form.body_ar"
                    dir="rtl"
                    maxlength="20000"
                    rows="10"
                    class="rounded-md border px-3 py-2"
                />
                <InputError :message="form.errors.body_ar" />
            </label>
            <label class="flex items-center gap-2 text-sm"
                ><input v-model="form.is_published" type="checkbox" />
                Publiée</label
            >
            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing"
                    >Enregistrer</Button
                ><Button
                    v-if="form.slug"
                    type="button"
                    variant="outline"
                    @click="reset"
                    >Annuler</Button
                >
            </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
