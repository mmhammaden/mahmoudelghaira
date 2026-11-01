<script setup lang="ts">
import { Form, Head, router, setLayoutProps } from '@inertiajs/vue3';
import { watchEffect } from 'vue';
import { Save } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import StaffClientLogoField from '@/components/company/StaffClientLogoField.vue';
import { Button } from '@/components/ui/button';

type Client = Record<string, string | number | null> & { id: number };
const props = defineProps<{
    client: Client | null;
    organisationTypes: string[];
    domains: string[];
    countries: string[];
    communes: { id: number; label: string }[];
    statuses: string[];
    logoUrl: string | null;
    documents: {
        id: number;
        label: string;
        type: string;
        name: string;
        size: number;
        downloadUrl: string;
        deleteUrl: string;
    }[];
}>();
watchEffect(() =>
    setLayoutProps({
        breadcrumbs: [
            { title: 'Clients', href: '/staff/clients' },
            {
                title: props.client ? 'Modifier' : 'Ajouter',
                href: window.location.pathname,
            },
        ],
    }),
);
const fields = [
    ['name', 'Nom du compte', 'text'],
    ['email', 'E-mail du compte', 'email'],
    ['company_name', 'Entreprise', 'text'],
    ['company_name_ar', 'Entreprise (arabe)', 'text'],
    ['company_email', 'E-mail entreprise', 'email'],
    ['company_phone', 'Téléphone', 'text'],
    ['website', 'Site web', 'url'],
    ['address', 'Adresse', 'text'],
    ['nif', 'NIF', 'text'],
    ['representative_name', 'Représentant', 'text'],
    ['representative_title', 'Fonction', 'text'],
    ['representative_phone', 'Téléphone représentant', 'text'],
    ['representative_email', 'E-mail représentant', 'email'],
] as const;
function removeDocument(url: string): void {
    if (window.confirm('Supprimer définitivement ce document ?'))
        router.delete(url);
}
</script>

<template>
    <Head :title="client ? 'Modifier le client' : 'Ajouter un client'" />
    <div class="w-full bg-white p-5 md:p-8">
        <h1 class="mb-6 text-3xl font-normal text-[#5a5c69]">
            {{ client ? 'Modifier le client' : 'Nouveau employeur' }}
        </h1>

        <Form
            v-if="!client"
            action="/staff/clients"
            method="post"
            v-slot="{ errors, processing }"
            class="legacy-admin-card max-w-5xl rounded border border-[#e3e6f0] shadow-[0_0.15rem_1.75rem_0_rgba(58,59,69,0.15)]"
        >
            <div class="border-b border-[#e3e6f0] bg-[#f8f9fc] px-5 py-3">
                <h2 class="font-bold text-[#4e73df]">Nouveau employeur</h2>
            </div>
            <div class="grid gap-x-6 gap-y-4 p-5 md:grid-cols-2">
                <label class="grid gap-1 text-sm md:col-span-2">
                    <span>Logo</span>
                    <input
                        name="logo"
                        type="file"
                        accept="image/png,image/jpeg,image/webp"
                        class="bg-white"
                    />
                    <small class="text-[#858796]">PNG, JPEG ou WebP — 2 Mo maximum.</small>
                    <InputError :message="errors.logo" />
                </label>
                <label class="grid gap-1 text-sm">
                    <span>Libellé <span class="text-[#e74a3b]">*</span></span>
                    <input name="company_name" required maxlength="150" />
                    <InputError :message="errors.company_name" />
                </label>
                <label id="arabic-translation" class="grid gap-1 text-sm">
                    <span
                        >Libellé arabe
                        <span class="text-[#e74a3b]">*</span></span
                    >
                    <input name="company_name_ar" dir="rtl" maxlength="150" />
                    <InputError :message="errors.company_name_ar" />
                </label>
                <label class="grid gap-1 text-sm md:col-span-2">
                    <span>Description</span>
                    <textarea name="description" rows="4" maxlength="5000" />
                    <InputError :message="errors.description" />
                </label>
                <label class="grid gap-1 text-sm">
                    <span>Type <span class="text-[#e74a3b]">*</span></span>
                    <select name="organisation_type" required>
                        <option value="" selected disabled>
                            Sélectionner...
                        </option>
                        <option
                            v-for="v in organisationTypes"
                            :key="v"
                            :value="v"
                        >
                            {{ v }}
                        </option>
                    </select>
                    <InputError :message="errors.organisation_type" />
                </label>
                <label class="grid gap-1 text-sm">
                    <span>Domaine <span class="text-[#e74a3b]">*</span></span>
                    <select name="domain" required>
                        <option value="" selected disabled>
                            Sélectionner...
                        </option>
                        <option v-for="v in domains" :key="v" :value="v">
                            {{ v }}
                        </option>
                    </select>
                    <InputError :message="errors.domain" />
                </label>
                <label class="grid gap-1 text-sm">
                    <span>E-mail <span class="text-[#e74a3b]">*</span></span>
                    <input
                        name="company_email"
                        type="email"
                        required
                        maxlength="255"
                    />
                    <InputError :message="errors.company_email" />
                </label>
                <label class="grid gap-1 text-sm">
                    <span>Téléphone</span>
                    <input name="company_phone" type="tel" required />
                    <InputError :message="errors.company_phone" />
                </label>
                <div class="flex justify-end md:col-span-2">
                    <Button
                        type="submit"
                        :disabled="processing"
                        class="!border-[#1cc88a] !bg-[#1cc88a] hover:!bg-[#17a673]"
                    >
                        <Save class="size-4" /> Ajouter
                    </Button>
                </div>
            </div>
        </Form>

        <Form
            v-else
            :action="client ? `/staff/clients/${client.id}` : '/staff/clients'"
            :method="client ? 'put' : 'post'"
            v-slot="{ errors, processing }"
            class="grid gap-5 sm:grid-cols-2"
        >
            <StaffClientLogoField
                v-if="client"
                :client-id="client.id"
                :logo-url="logoUrl"
            />
            <label
                v-for="field in fields"
                :key="field[0]"
                :id="
                    field[0] === 'company_name_ar'
                        ? 'arabic-translation'
                        : undefined
                "
                class="grid gap-1 text-sm"
            >
                <span>{{ field[1] }}</span>
                <input
                    :name="field[0]"
                    :type="field[2]"
                    :value="client?.[field[0]] ?? ''"
                    class="rounded-md border px-3 py-2"
                />
                <InputError :message="errors[field[0]]" />
            </label>
            <label class="grid gap-1 text-sm"
                ><span>Domaine</span
                ><select name="domain" class="rounded-md border px-3 py-2">
                    <option
                        v-for="v in domains"
                        :key="v"
                        :value="v"
                        :selected="client?.domain === v"
                    >
                        {{ v }}
                    </option></select
                ><InputError :message="errors.domain"
            /></label>
            <label class="grid gap-1 text-sm"
                ><span>Type</span
                ><select
                    name="organisation_type"
                    class="rounded-md border px-3 py-2"
                >
                    <option
                        v-for="v in organisationTypes"
                        :key="v"
                        :value="v"
                        :selected="client?.organisation_type === v"
                    >
                        {{ v }}
                    </option></select
                ><InputError :message="errors.organisation_type"
            /></label>
            <label v-if="client" class="grid gap-1 text-sm"
                ><span>Pays</span
                ><select name="country" class="rounded-md border px-3 py-2">
                    <option
                        v-for="v in countries"
                        :key="v"
                        :value="v"
                        :selected="client?.country === v"
                    >
                        {{ v }}
                    </option></select
                ><InputError :message="errors.country"
            /></label>
            <label v-if="client" class="grid gap-1 text-sm"
                ><span>Commune (Mauritanie)</span
                ><select name="commune_id" class="rounded-md border px-3 py-2">
                    <option value="">—</option>
                    <option
                        v-for="v in communes"
                        :key="v.id"
                        :value="v.id"
                        :selected="client?.commune_id === v.id"
                    >
                        {{ v.label }}
                    </option></select
                ><InputError :message="errors.commune_id"
            /></label>
            <label v-if="client" class="grid gap-1 text-sm"
                ><span>État</span
                ><select
                    name="approval_status"
                    class="rounded-md border px-3 py-2"
                >
                    <option
                        v-for="v in statuses"
                        :key="v"
                        :value="v"
                        :selected="client?.approval_status === v"
                    >
                        {{ v }}
                    </option></select
                ><InputError :message="errors.approval_status"
            /></label>
            <label class="grid gap-1 text-sm sm:col-span-2">
                <span>Description</span>
                <RichTextEditor
                    name="description"
                    :model-value="String(client?.description ?? '')"
                />
                <InputError :message="errors.description" />
            </label>
            <div class="sm:col-span-2">
                <Button type="submit" :disabled="processing"
                    >Enregistrer</Button
                >
            </div>
        </Form>

        <section v-if="client" class="mt-8 grid gap-4 border-t pt-6">
            <div>
                <h2 class="text-xl font-semibold">Documents</h2>
                <p class="text-muted-foreground text-sm">
                    Pièces administratives privées du client.
                </p>
            </div>
            <Form
                :action="`/staff/clients/${client.id}/documents`"
                method="post"
                class="grid gap-3 rounded-md border p-4 sm:grid-cols-3"
                v-slot="{ errors, processing }"
            >
                <label class="grid gap-1 text-sm"
                    ><span>Libellé</span
                    ><input
                        name="label"
                        required
                        class="rounded-md border px-3 py-2" /><InputError
                        :message="errors.label"
                /></label>
                <label class="grid gap-1 text-sm"
                    ><span>Type de document</span
                    ><input
                        name="document_type"
                        required
                        class="rounded-md border px-3 py-2"
                        placeholder="Contrat, agrément…" /><InputError
                        :message="errors.document_type"
                /></label>
                <label class="grid gap-1 text-sm"
                    ><span>Fichier</span
                    ><input
                        name="file"
                        type="file"
                        required
                        accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                        class="rounded-md border px-3 py-2" /><InputError
                        :message="errors.file"
                /></label>
                <div class="sm:col-span-3">
                    <Button type="submit" :disabled="processing"
                        >Ajouter le document</Button
                    >
                </div>
            </Form>
            <div class="overflow-x-auto rounded-md border">
                <table class="w-full text-sm">
                    <thead class="bg-muted">
                        <tr>
                            <th class="p-3 text-start">Libellé</th>
                            <th class="p-3 text-start">Type</th>
                            <th class="p-3 text-start">Fichier</th>
                            <th class="p-3 text-start">Taille</th>
                            <th class="p-3 text-start">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="document in documents"
                            :key="document.id"
                            class="border-t"
                        >
                            <td class="p-3">{{ document.label }}</td>
                            <td class="p-3">{{ document.type }}</td>
                            <td class="p-3">{{ document.name }}</td>
                            <td class="p-3">
                                {{ Math.ceil(document.size / 1024) }} Ko
                            </td>
                            <td class="flex gap-2 p-3">
                                <Button size="sm" variant="outline" as-child
                                    ><a :href="document.downloadUrl"
                                        >Télécharger</a
                                    ></Button
                                ><Button
                                    size="sm"
                                    variant="destructive"
                                    type="button"
                                    @click="removeDocument(document.deleteUrl)"
                                    >Supprimer</Button
                                >
                            </td>
                        </tr>
                        <tr v-if="!documents.length">
                            <td
                                colspan="5"
                                class="text-muted-foreground p-6 text-center"
                            >
                                Aucun document.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
