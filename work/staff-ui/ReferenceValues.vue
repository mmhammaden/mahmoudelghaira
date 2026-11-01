<script setup lang="ts">
import { Head, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Pencil, Plus, Trash2 } from '@lucide/vue';

type Value = {
    id: number;
    type: string;
    code: string;
    labelFr: string;
    labelAr: string;
    order: number;
    active: boolean;
    inUse: boolean;
};
defineProps<{ values: Value[] }>();
setLayoutProps({
    breadcrumbs: [{ title: 'Référentiels', href: '/staff/references' }],
});
const editing = ref<number | null>(null);
const createOpen = ref(false);
const form = useForm({
    label_fr: '',
    label_ar: '',
    display_order: 1,
    is_active: true,
});
const creating = useForm({
    type: 'domain',
    label_fr: '',
    label_ar: '',
    display_order: 1,
});
function edit(value: Value): void {
    editing.value = value.id;
    Object.assign(form, {
        label_fr: value.labelFr,
        label_ar: value.labelAr,
        display_order: value.order,
        is_active: value.active,
    });
    form.clearErrors();
}
function save(): void {
    if (editing.value)
        form.put(`/staff/references/${editing.value}`, {
            preserveScroll: true,
            onSuccess: () => (editing.value = null),
        });
}
function create(): void {
    creating.post('/staff/references', {
        preserveScroll: true,
        onSuccess: () => {
            creating.reset();
            createOpen.value = false;
        },
    });
}
function remove(value: Value): void {
    if (value.inUse || !window.confirm(`Supprimer « ${value.labelFr} » ?`))
        return;
    router.delete(`/staff/references/${value.id}`, {
        preserveScroll: true,
        onError: (errors) => {
            if (errors.reference) window.alert(errors.reference);
        },
    });
}
</script>

<template>
    <Head title="Référentiels" />
    <div class="flex flex-1 flex-col gap-6 bg-white p-5 md:p-8">
        <div class="flex items-center justify-between gap-3">
            <div><h1 class="text-3xl font-normal text-[#5a5c69]">Référentiels</h1>
            <p class="mt-2 text-sm text-[#858796]">Codes stables, libellés bilingues, ordre et disponibilité.</p></div>
            <Button size="sm" class="shadow-sm" @click="createOpen = true"><Plus class="size-4" /> Ajouter</Button>
        </div>
        <Dialog v-model:open="createOpen">
          <DialogContent class="sm:max-w-xl">
            <DialogHeader><DialogTitle>Ajouter un référentiel</DialogTitle></DialogHeader>
            <form class="grid gap-3" @submit.prevent="create">
            <label class="grid gap-1 text-sm"
                ><span>Type</span
                ><select
                    v-model="creating.type"
                    class="rounded border px-2 py-2"
                >
                    <option value="domain">Domaine</option>
                    <option value="language">Langue</option>
                </select></label
            >
            <label class="grid gap-1 text-sm"
                ><span>Français</span
                ><input
                    v-model="creating.label_fr"
                    required
                    maxlength="160"
                    class="rounded border px-2 py-2" /><InputError
                    :message="creating.errors.label_fr"
            /></label>
            <label class="grid gap-1 text-sm"
                ><span>Arabe</span
                ><input
                    v-model="creating.label_ar"
                    required
                    maxlength="160"
                    dir="rtl"
                    class="rounded border px-2 py-2" /><InputError
                    :message="creating.errors.label_ar"
            /></label>
            <label class="grid gap-1 text-sm"
                ><span>Ordre</span
                ><input
                    v-model.number="creating.display_order"
                    type="number"
                    min="1"
                    max="999"
                    required
                    class="rounded border px-2 py-2" /><InputError
                    :message="creating.errors.display_order"
            /></label>
            <div class="flex justify-end">
                <Button type="submit" :disabled="creating.processing"
                    >Ajouter</Button
                >
            </div>
            </form>
          </DialogContent>
        </Dialog>
        <div class="overflow-x-auto rounded-md border border-[#e3e6f0]">
            <table class="w-full min-w-[900px] text-sm">
                <thead class="bg-[#f8f9fc] text-left">
                    <tr>
                        <th class="p-3">Type</th>
                        <th class="p-3">Code</th>
                        <th class="p-3">Français</th>
                        <th class="p-3">Arabe</th>
                        <th class="p-3">Ordre</th>
                        <th class="p-3">Actif</th>
                        <th class="p-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="value in values"
                        :key="value.id"
                        class="border-t"
                    >
                        <td class="p-3">{{ value.type }}</td>
                        <td class="p-3 font-mono text-xs">{{ value.code }}</td>
                        <template v-if="editing === value.id"
                            ><td class="p-3">
                                <input
                                    v-model="form.label_fr"
                                    required
                                    maxlength="160"
                                    class="w-full rounded border px-2 py-1"
                                /><InputError :message="form.errors.label_fr" />
                            </td>
                            <td class="p-3">
                                <input
                                    v-model="form.label_ar"
                                    dir="rtl"
                                    required
                                    maxlength="160"
                                    class="w-full rounded border px-2 py-1"
                                /><InputError :message="form.errors.label_ar" />
                            </td>
                            <td class="p-3">
                                <input
                                    v-model.number="form.display_order"
                                    type="number"
                                    min="1"
                                    max="999"
                                    class="w-20 rounded border px-2 py-1"
                                />
                            </td>
                            <td class="p-3">
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    :disabled="value.inUse"
                                /><InputError
                                    :message="form.errors.is_active"
                                />
                            </td>
                            <td class="p-3">
                                <Button size="sm" @click="save"
                                    >Enregistrer</Button
                                >
                            </td></template
                        ><template v-else
                            ><td class="p-3">{{ value.labelFr }}</td>
                            <td class="p-3" dir="rtl">{{ value.labelAr }}</td>
                            <td class="p-3">{{ value.order }}</td>
                            <td class="p-3">
                                {{ value.active ? 'Oui' : 'Non'
                                }}<span
                                    v-if="value.inUse"
                                    class="ml-1 text-xs text-[#858796]"
                                    >(utilisée)</span
                                >
                            </td>
                            <td class="p-3">
                                <div class="flex gap-2">
                                    <button class="legacy-action bg-[#e74a3b]" type="button" title="Modifier" @click="edit(value)"><Pencil class="size-4" /></button>
                                    <button
                                        class="legacy-action bg-[#e74a3b]"
                                        type="button"
                                        :disabled="value.inUse"
                                        title="Supprimer"
                                        @click="remove(value)"
                                        ><Trash2 class="size-4" /></button>
                                </div>
                            </td></template
                        >
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
