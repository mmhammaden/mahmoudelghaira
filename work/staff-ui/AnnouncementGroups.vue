<script setup lang="ts">
import { Head, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { ref, watchEffect } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Pencil, Plus, Trash2 } from '@lucide/vue';

type Group = {
    id: number;
    title: string;
    titleAr: string | null;
    published: boolean;
    announcementIds: number[];
};

const props = defineProps<{
    groups: Group[];
    announcements: { id: number; title: string }[];
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Annonces regroupées', href: '/staff/announcement-groups' },
    ],
});

const editing = ref<Group | null>(null);
const formOpen = ref(false);
const form = useForm({
    title: '',
    title_ar: '',
    is_published: false,
    announcement_ids: [] as number[],
});

function edit(group: Group): void {
    editing.value = group;
    form.title = group.title;
    form.title_ar = group.titleAr ?? '';
    form.is_published = group.published;
    form.announcement_ids = [...group.announcementIds];
    formOpen.value = true;
}

function createNew(): void {
    reset();
    formOpen.value = true;
}

function reset(): void {
    editing.value = null;
    form.reset();
    form.clearErrors();
    formOpen.value = false;
}

function submit(): void {
    const options = { preserveScroll: true, onSuccess: reset };
    if (editing.value)
        form.put(`/staff/announcement-groups/${editing.value.id}`, options);
    else form.post('/staff/announcement-groups', options);
}

function remove(group: Group): void {
    if (window.confirm(`Supprimer le regroupement « ${group.title} » ?`)) {
        router.delete(`/staff/announcement-groups/${group.id}`, {
            preserveScroll: true,
        });
    }
}

watchEffect(() => {
    if (
        editing.value &&
        !props.groups.some((group) => group.id === editing.value?.id)
    )
        reset();
});
</script>

<template>
    <Head title="Annonces regroupées" />
    <div class="flex flex-1 flex-col bg-white p-5 md:p-8">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h1 class="text-3xl font-normal text-[#5a5c69]">Annonces regroupées</h1>
            <Button size="sm" class="shadow-sm" @click="createNew">
                <Plus class="size-4" /> Nouveau regroupement
            </Button>
        </div>

        <div class="overflow-x-auto rounded border border-[#e3e6f0]">
            <table class="w-full min-w-[680px] text-sm">
                <thead class="bg-[#f8f9fc] text-left">
                    <tr><th class="p-3">#</th><th class="p-3">Titre</th><th class="p-3">Annonces</th><th class="p-3">État</th><th class="p-3">Actions</th></tr>
                </thead>
                <tbody>
                    <tr v-for="group in groups" :key="group.id" class="border-t">
                        <td class="p-3">{{ group.id }}</td>
                        <td class="p-3"><div>{{ group.title }}</div><div v-if="group.titleAr" dir="rtl" class="text-xs text-[#858796]">{{ group.titleAr }}</div></td>
                        <td class="p-3">{{ group.announcementIds.length }}</td>
                        <td class="p-3">{{ group.published ? 'Publié' : 'Brouillon' }}</td>
                        <td class="p-3"><div class="flex gap-1">
                            <button class="legacy-action bg-[#e74a3b]" type="button" title="Modifier" @click="edit(group)"><Pencil class="size-4" /></button>
                            <button class="legacy-action bg-[#e74a3b]" type="button" title="Supprimer" @click="remove(group)"><Trash2 class="size-4" /></button>
                        </div></td>
                    </tr>
                    <tr v-if="!groups.length"><td colspan="5" class="p-4 text-center text-[#858796]">Aucun regroupement.</td></tr>
                </tbody>
            </table>
        </div>

        <Dialog v-model:open="formOpen">
          <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
            <DialogHeader><DialogTitle>{{ editing ? 'Modifier le regroupement' : 'Nouveau regroupement' }}</DialogTitle></DialogHeader>
            <form class="space-y-4" @submit.prevent="submit">
            <label class="grid gap-1 text-sm">
                <span>Titre français</span>
                <input
                    v-model="form.title"
                    class="rounded-md border px-3 py-2"
                    required
                    maxlength="200"
                />
                <InputError :message="form.errors.title" />
            </label>
            <label class="grid gap-1 text-sm">
                <span>Titre arabe</span>
                <input
                    v-model="form.title_ar"
                    dir="rtl"
                    class="rounded-md border px-3 py-2"
                    maxlength="200"
                />
                <InputError :message="form.errors.title_ar" />
            </label>
            <fieldset class="space-y-2">
                <legend class="text-sm font-medium">
                    Annonces (minimum 2)
                </legend>
                <label
                    v-for="announcement in announcements"
                    :key="announcement.id"
                    class="flex gap-2 text-sm"
                >
                    <input
                        v-model="form.announcement_ids"
                        type="checkbox"
                        :value="announcement.id"
                    />
                    <span
                        >#{{ announcement.id }} — {{ announcement.title }}</span
                    >
                </label>
                <InputError :message="form.errors.announcement_ids" />
            </fieldset>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.is_published" type="checkbox" /> Publié sur
                le portail
            </label>
            <div class="flex justify-end gap-2">
                <Button type="submit" :disabled="form.processing">{{
                    editing ? 'Enregistrer' : 'Créer'
                }}</Button>
                <Button
                    v-if="editing"
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
