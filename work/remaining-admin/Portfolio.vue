<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3';
import { reactive } from 'vue';
import NativeSelect from '@/components/candidate/NativeSelect.vue';
import { Button } from '@/components/ui/button';

type Option = { value: number | string; label: string };
type Row = {
    id: number;
    date: string;
    company: string;
    announcement: string;
    category: string;
    status: string;
    staff: string;
};
type Page = {
    data: Row[];
    links: { url: string | null; label: string; active: boolean }[];
};

const props = defineProps<{
    rows: Page;
    filters: {
        date_from?: string;
        date_to?: string;
        employer?: number;
        staff?: number;
        category?: string;
    };
    options: { employers: Option[]; staff: Option[]; categories: Option[] };
}>();
setLayoutProps({
    breadcrumbs: [{ title: 'Porte feuille', href: '/staff/portfolio' }],
});

const filters = reactive({
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    employer: props.filters.employer ? String(props.filters.employer) : '',
    staff: props.filters.staff ? String(props.filters.staff) : '',
    category: props.filters.category ?? '',
});

function query(): Record<string, string> {
    return Object.fromEntries(
        Object.entries(filters).filter(([, value]) => value !== ''),
    );
}
function apply(): void {
    router.get('/staff/portfolio', query(), {
        preserveState: true,
        replace: true,
    });
}
function reset(): void {
    Object.assign(filters, {
        date_from: '',
        date_to: '',
        employer: '',
        staff: '',
        category: '',
    });
    apply();
}
function exportUrl(): string {
    const params = new URLSearchParams(query());
    return `/staff/portfolio.pdf${params.size ? `?${params}` : ''}`;
}
</script>

<template>
    <Head title="Suivi de porte feuille" />
    <div class="flex flex-1 flex-col gap-6 bg-white p-5 md:p-8">
        <div>
            <h1 class="text-3xl font-normal text-[#5a5c69]">
                Le suivi de porte feuille
            </h1>
            <p class="mt-2 text-sm text-[#858796]">
                Suivi des demandes de mise en relation, sans données
                personnelles dans l’export.
            </p>
        </div>
        <form
            class="grid gap-4 rounded border border-[#e3e6f0] bg-[#f8f9fc] p-4 md:grid-cols-5"
            @submit.prevent="apply"
        >
            <label class="grid gap-1 text-sm"
                >Date début<input
                    v-model="filters.date_from"
                    type="date"
                    class="rounded-md border px-3 py-2"
            /></label>
            <label class="grid gap-1 text-sm"
                >Date fin<input
                    v-model="filters.date_to"
                    type="date"
                    :min="filters.date_from"
                    class="rounded-md border px-3 py-2"
            /></label>
            <label class="grid gap-1 text-sm"
                >Employeur<NativeSelect
                    v-model="filters.employer"
                    :options="
                        options.employers.map((x) => ({
                            value: String(x.value),
                            label: x.label,
                        }))
                    "
                    placeholder="Tous"
            /></label>
            <label class="grid gap-1 text-sm"
                >Utilisateur staff<NativeSelect
                    v-model="filters.staff"
                    :options="
                        options.staff.map((x) => ({
                            value: String(x.value),
                            label: x.label,
                        }))
                    "
                    placeholder="Tous"
            /></label>
            <label class="grid gap-1 text-sm"
                >Catégorie<NativeSelect
                    v-model="filters.category"
                    :options="
                        options.categories.map((x) => ({
                            value: String(x.value),
                            label: x.label,
                        }))
                    "
                    placeholder="Toutes"
            /></label>
            <div class="flex flex-wrap gap-2 md:col-span-5">
                <Button type="submit">Générer</Button
                ><Button type="button" variant="outline" @click="reset"
                    >Réinitialiser</Button
                ><Button as-child variant="outline"
                    ><a :href="exportUrl()">Exporter en PDF</a></Button
                >
            </div>
        </form>
        <div class="overflow-x-auto rounded border border-[#e3e6f0]">
            <table class="w-full min-w-[850px] text-sm">
                <thead class="bg-[#f8f9fc] text-left">
                    <tr>
                        <th class="p-3">Date</th>
                        <th class="p-3">Employeur</th>
                        <th class="p-3">Annonce</th>
                        <th class="p-3">Catégorie</th>
                        <th class="p-3">État</th>
                        <th class="p-3">Utilisateur</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows.data" :key="row.id" class="border-t">
                        <td class="p-3">{{ row.date }}</td>
                        <td class="p-3">{{ row.company }}</td>
                        <td class="p-3">{{ row.announcement }}</td>
                        <td class="p-3">{{ row.category }}</td>
                        <td class="p-3">{{ row.status }}</td>
                        <td class="p-3">{{ row.staff }}</td>
                    </tr>
                    <tr v-if="!rows.data.length">
                        <td colspan="6" class="p-8 text-center text-[#858796]">
                            Aucun résultat.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <nav v-if="rows.links.length > 3" class="flex flex-wrap gap-1">
            <button
                v-for="link in rows.links"
                :key="link.label"
                type="button"
                :disabled="!link.url"
                class="rounded border px-3 py-2 text-sm disabled:opacity-40"
                :class="link.active ? 'bg-[#36b9cc] text-white' : ''"
                @click="link.url && router.visit(link.url)"
                v-html="link.label"
            />
        </nav>
    </div>
</template>
