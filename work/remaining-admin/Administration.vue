<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import {
    ArrowUp,
    Check,
    Eye,
    Filter,
    FolderOpen,
    Info,
    KeyRound,
    Languages,
    Pencil,
    Plus,
    Power,
    RotateCcw,
    Search,
    Settings,
    Square,
    Trash2,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { computed, ref, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';

type Column = { key: string; label: string; translation?: string };
type RowAction = {
    approve?: string | null;
    deactivate?: string | null;
    access?: string | null;
    edit?: string | null;
    delete?: string | null;
    suspend?: string | null;
    reactivate?: string | null;
    reset?: string | null;
    records?: string | null;
    translate?: string | null;
    review?: string | null;
    feature?: string | null;
    unfeature?: string | null;
    changeType?: string | null;
};
type Row = Record<string, string | number | null | RowAction>;
type Link = { url: string | null; label: string; active: boolean };
type Filter = {
    name: string;
    label: string;
    value: string;
    options: { value: string; label: string }[];
};

const props = defineProps<{
    title: string;
    description: string;
    columns: Column[];
    rows: { data: Row[]; links: Link[]; per_page?: number };
    searchable: boolean;
    primaryAction?: { label: string; url: string } | null;
    filters?: Filter[];
}>();

const { t } = useI18n();
const isAnnouncementTable = computed(() =>
    props.columns.some((column) => column.key === 'category'),
);
const search = ref(new URLSearchParams(window.location.search).get('q') ?? '');
const filterValues = ref<Record<string, string>>(
    Object.fromEntries(
        (props.filters ?? []).map((filter) => [filter.name, filter.value]),
    ),
);

watchEffect(() =>
    setLayoutProps({
        breadcrumbs: [{ title: props.title, href: window.location.pathname }],
    }),
);

function submit(): void {
    const query: Record<string, string> = { ...filterValues.value };
    if (search.value) query.q = search.value;

    router.get(
        window.location.pathname,
        Object.fromEntries(Object.entries(query).filter(([, value]) => value)),
        { preserveState: true, replace: true },
    );
}

function submitFilter(): void {
    submit();
}

function display(row: Row, column: Column): string | number {
    const value = row[column.key];
    if (typeof value === 'object') return '—';
    if (column.translation && typeof value === 'string')
        return t(`${column.translation}.${value}`);
    return value ?? '—';
}

function remove(url: string): void {
    if (window.confirm('Supprimer définitivement cette annonce ?')) {
        router.delete(url);
    }
}

function patch(url: string, confirmation: string): void {
    if (window.confirm(confirmation)) router.patch(url);
}

function post(url: string, confirmation: string): void {
    if (window.confirm(confirmation)) router.post(url);
}
function removeFeature(url: string): void {
    if (window.confirm("Retirer cette annonce du haut de la page d'accueil ?"))
        router.delete(url);
}
</script>

<template>
    <Head :title="title" />
    <div class="flex flex-1 flex-col gap-6 bg-white p-5 md:p-8">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <h1 class="text-3xl font-normal text-[#5a5c69]">{{ title }}</h1>
            </div>
            <div class="flex items-center justify-end gap-3">
                <Button v-if="primaryAction" size="sm" as-child>
                    <Link :href="primaryAction.url"
                        ><Plus class="size-4" />{{ primaryAction.label }}</Link
                    >
                </Button>
            </div>
        </div>

        <form
            v-if="filters?.length"
            class="grid gap-6 rounded-md border border-[#e3e6f0] bg-[#f8f9fc] p-6 sm:grid-cols-2"
            :class="isAnnouncementTable ? 'xl:grid-cols-4' : 'xl:grid-cols-6'"
            @submit.prevent="submit"
        >
            <label
                v-for="filter in filters"
                :key="filter.name"
                class="grid min-w-0 gap-1 text-sm text-[#5a5c69]"
            >
                <span class="flex items-center gap-2 text-lg text-[#858796]">
                    <Filter class="size-5" />
                    {{ filter.label }}
                </span>
                <select
                    v-model="filterValues[filter.name]"
                    class="w-full min-w-0 rounded-md border border-[#d1d3e2] bg-white px-3 py-2"
                    @change="submitFilter"
                >
                    <option value="">Tous</option>
                    <option
                        v-for="option in filter.options"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </label>
        </form>

        <div
            class="flex items-center justify-between border-b border-[#e3e6f0] px-1 pb-4"
        >
            <div class="flex items-center gap-2 text-[#858796]">
                <Eye class="size-5" />
                <input
                    :value="rows.per_page ?? 10"
                    type="number"
                    min="10"
                    max="10"
                    readonly
                    aria-label="Nombre de lignes"
                    class="w-20 px-3 py-2"
                />
            </div>
            <form
                v-if="searchable"
                class="flex items-center gap-4"
                @submit.prevent="submit"
            >
                <Search class="size-6 text-[#858796]" />
                <input
                    v-model="search"
                    class="w-72 border border-[#d1d3e2] px-3 py-2 outline-none focus:border-[#36b9cc]"
                    aria-label="Rechercher"
                />
            </form>
        </div>

        <div
            class="overflow-hidden rounded-md border border-[#e3e6f0] shadow-[0_.15rem_1.75rem_0_rgba(58,59,69,.12)]"
        >
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1050px] text-start text-base">
                    <thead class="bg-white text-base font-bold text-[#858796]">
                        <tr>
                            <th
                                v-for="column in columns"
                                :key="column.key"
                                class="border-b border-[#e3e6f0] px-4 py-4 text-start"
                            >
                                <span
                                    class="flex items-end justify-between gap-2"
                                >
                                    {{ column.label }}
                                    <span
                                        class="text-xs font-normal text-[#d1d3e2]"
                                        aria-hidden="true"
                                        >↑↓</span
                                    >
                                </span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e3e6f0] text-[#5a5c69]">
                        <tr
                            v-for="(row, index) in rows.data"
                            :key="String(row.id ?? row.code ?? index)"
                            class="hover:brightness-[.98]"
                            :class="{
                                'bg-[#fff3cd] text-[#856404]':
                                    row.tone === 'warning',
                                'bg-[#d1f2e8] text-[#006b4f]':
                                    row.tone === 'success',
                                'bg-[#f8d7da] text-[#842029]':
                                    row.tone === 'danger',
                            }"
                        >
                            <td
                                v-for="column in columns"
                                :key="column.key"
                                class="px-4"
                                :class="isAnnouncementTable ? 'py-5' : 'py-3'"
                            >
                                <div
                                    v-if="
                                        isAnnouncementTable &&
                                        column.key === 'actions' &&
                                        typeof row.actions === 'object' &&
                                        row.actions
                                    "
                                    class="flex gap-0"
                                >
                                    <Link
                                        v-if="row.actions.edit"
                                        :href="row.actions.edit"
                                        class="legacy-action bg-[#e74a3b]"
                                        title="Informations"
                                        ><Info class="size-4"
                                    /></Link>
                                    <Link
                                        v-if="row.actions.changeType"
                                        :href="row.actions.changeType"
                                        class="legacy-action bg-[#5a5c69]"
                                        title="Consulter"
                                        ><Eye class="size-4"
                                    /></Link>
                                    <Link
                                        v-if="row.actions.review"
                                        :href="row.actions.review"
                                        class="legacy-action bg-[#1cc88a]"
                                        title="Valider"
                                        ><ArrowUp class="size-4"
                                    /></Link>
                                    <button
                                        v-if="row.actions.feature"
                                        type="button"
                                        class="legacy-action bg-[#f6c23e]"
                                        title="Fixer en haut"
                                        @click="
                                            post(
                                                row.actions.feature,
                                                'Fixer cette annonce en haut de la page d’accueil ?',
                                            )
                                        "
                                    >
                                        <Square class="size-3.5 fill-white" />
                                    </button>
                                    <button
                                        v-else-if="row.actions.unfeature"
                                        type="button"
                                        class="legacy-action bg-[#f6c23e]"
                                        title="Détacher"
                                        @click="
                                            removeFeature(row.actions.unfeature)
                                        "
                                    >
                                        <Square class="size-3.5" />
                                    </button>
                                    <button
                                        v-if="row.actions.delete"
                                        type="button"
                                        class="legacy-action bg-[#e74a3b]"
                                        title="Supprimer"
                                        @click="remove(row.actions.delete)"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                    <Link
                                        v-if="row.actions.translate"
                                        :href="row.actions.translate"
                                        class="legacy-action bg-[#36b9cc]"
                                        title="Traduction en arabe"
                                        ><Languages class="size-4"
                                    /></Link>
                                </div>
                                <div
                                    v-if="
                                        !isAnnouncementTable &&
                                        column.key === 'actions' &&
                                        typeof row.actions === 'object' &&
                                        row.actions
                                    "
                                    class="flex gap-0"
                                >
                                    <button
                                        v-if="row.actions.approve"
                                        type="button"
                                        class="legacy-action bg-[#1cc88a]"
                                        title="Valider"
                                        @click="
                                            patch(
                                                row.actions.approve,
                                                'Valider ce client ?',
                                            )
                                        "
                                    >
                                        <Check class="size-4" />
                                    </button>
                                    <button
                                        v-if="row.actions.deactivate"
                                        type="button"
                                        class="legacy-action bg-[#858796]"
                                        title="Désactiver"
                                        @click="
                                            patch(
                                                row.actions.deactivate,
                                                'Désactiver ce client ?',
                                            )
                                        "
                                    >
                                        <Power class="size-4" />
                                    </button>
                                    <Link
                                        v-if="row.actions.review"
                                        :href="row.actions.review"
                                        class="legacy-action bg-[#1cc88a]"
                                        title="Valider"
                                    >
                                        <Check class="size-4" />
                                    </Link>
                                    <button
                                        v-if="row.actions.feature"
                                        type="button"
                                        class="legacy-action bg-[#f6c23e]"
                                        title="Fixer en haut"
                                        @click="
                                            post(
                                                row.actions.feature,
                                                'Fixer cette annonce en haut de la page d’accueil ?',
                                            )
                                        "
                                    >
                                        <Square class="size-3.5 fill-white" />
                                    </button>
                                    <button
                                        v-if="row.actions.unfeature"
                                        type="button"
                                        class="legacy-action bg-[#f6c23e]"
                                        title="Détacher"
                                        @click="
                                            removeFeature(row.actions.unfeature)
                                        "
                                    >
                                        <Square class="size-3.5" />
                                    </button>
                                    <Link
                                        v-if="row.actions.access"
                                        :href="row.actions.access"
                                        class="legacy-action bg-[#5a5c69]"
                                        title="Configurer l’accès"
                                    >
                                        <Settings class="size-4" />
                                    </Link>
                                    <Link
                                        v-if="row.actions.records"
                                        :href="row.actions.records"
                                        class="legacy-action bg-[#36b9cc]"
                                        title="Dossier"
                                    >
                                        <FolderOpen class="size-4" />
                                    </Link>
                                    <button
                                        v-if="row.actions.reset"
                                        type="button"
                                        class="legacy-action bg-[#4e73df]"
                                        title="Réinitialiser le mot de passe"
                                        @click="
                                            post(
                                                row.actions.reset,
                                                'Envoyer un lien de réinitialisation ?',
                                            )
                                        "
                                    >
                                        <KeyRound class="size-4" />
                                    </button>
                                    <Link
                                        v-if="row.actions.edit"
                                        :href="row.actions.edit"
                                        class="legacy-action bg-[#e74a3b]"
                                        title="Modifier"
                                    >
                                        <Pencil class="size-4" />
                                    </Link>
                                    <Link
                                        v-if="row.actions.changeType"
                                        :href="row.actions.changeType"
                                        class="legacy-action bg-[#5a5c69]"
                                        title="Changer le type"
                                    >
                                        <Settings class="size-4" />
                                    </Link>
                                    <Link
                                        v-if="row.actions.translate"
                                        :href="row.actions.translate"
                                        class="legacy-action bg-[#1cc88a]"
                                        title="Traduire en arabe"
                                    >
                                        <Languages class="size-4" />
                                    </Link>
                                    <button
                                        v-if="row.actions.suspend"
                                        type="button"
                                        class="legacy-action bg-[#e74a3b]"
                                        title="Suspendre"
                                        @click="
                                            patch(
                                                row.actions.suspend,
                                                'Suspendre ce compte ?',
                                            )
                                        "
                                    >
                                        <Power class="size-4" />
                                    </button>
                                    <button
                                        v-if="row.actions.reactivate"
                                        type="button"
                                        class="legacy-action bg-[#1cc88a]"
                                        title="Réactiver"
                                        @click="
                                            patch(
                                                row.actions.reactivate,
                                                'Réactiver ce compte ?',
                                            )
                                        "
                                    >
                                        <RotateCcw class="size-4" />
                                    </button>
                                    <button
                                        v-if="row.actions.delete"
                                        type="button"
                                        class="legacy-action bg-[#e74a3b]"
                                        title="Supprimer"
                                        @click="remove(row.actions.delete)"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                                <template v-else-if="column.key !== 'actions'">
                                    <Link
                                        v-if="
                                            isAnnouncementTable &&
                                            column.key === 'title' &&
                                            typeof row.actions === 'object' &&
                                            row.actions?.edit
                                        "
                                        :href="row.actions.edit"
                                        class="font-semibold text-[#4e73df]"
                                    >
                                        {{ display(row, column) }}
                                    </Link>
                                    <template v-else>
                                        {{ display(row, column) }}
                                    </template>
                                </template>
                            </td>
                        </tr>
                        <tr v-if="!rows.data.length">
                            <td
                                :colspan="columns.length"
                                class="px-4 py-10 text-center text-[#858796]"
                            >
                                Aucun résultat.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <nav
            v-if="rows.links?.length > 3"
            class="flex flex-wrap gap-1"
            aria-label="Pagination"
        >
            <button
                v-for="link in rows.links"
                :key="link.label"
                type="button"
                :disabled="!link.url"
                class="min-w-9 rounded border px-3 py-2 text-sm disabled:opacity-40"
                :class="
                    link.active
                        ? 'border-[#36b9cc] bg-[#36b9cc] text-white'
                        : 'border-[#d1d3e2] text-[#5a5c69]'
                "
                @click="link.url && router.visit(link.url)"
                v-html="link.label"
            />
        </nav>
    </div>
</template>
