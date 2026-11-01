<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Plus, Save } from '@lucide/vue';
import NativeSelect from '@/components/candidate/NativeSelect.vue';
import InputError from '@/components/InputError.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Option = { value: string; label: string };
type Announcement = {
    id: number;
    employer_profile_id: number;
    category: string;
    title: string;
    description: string;
    title_ar: string | null;
    description_ar: string | null;
    domain: string | null;
    country: string | null;
    wilaya_id: number | null;
    deadline_at: string | null;
    external_url: string | null;
    attachments: { id: number; name: string; size: number }[];
};
const props = defineProps<{
    family: string;
    announcement: Announcement | null;
    employers: Option[];
    options: {
        category: { value: string; externalLink: boolean }[];
        domain: string[];
        country: string[];
        wilaya: { id: number; fr: string; ar: string }[];
    };
    descriptionLimit: number;
}>();
const { t, locale } = useI18n();
setLayoutProps({ breadcrumbs: [{ title: 'Ajouter une annonce', href: '' }] });

const form = useForm({
    employer_profile_id: props.announcement
        ? String(props.announcement.employer_profile_id)
        : '',
    category: props.announcement?.category ?? '',
    title: props.announcement?.title ?? '',
    description: props.announcement?.description ?? '',
    title_ar: props.announcement?.title_ar ?? '',
    description_ar: props.announcement?.description_ar ?? '',
    domain: props.announcement?.domain ?? '',
    country: props.announcement?.country ?? 'MR',
    wilaya_id: props.announcement?.wilaya_id
        ? String(props.announcement.wilaya_id)
        : '',
    deadline_at: props.announcement?.deadline_at ?? '',
    external_url: props.announcement?.external_url ?? '',
    documents: [] as File[],
});
const categories = computed(() =>
    props.options.category.map((x) => ({
        value: x.value,
        label: t(`announcement.category.${x.value}`),
    })),
);
const domains = computed(() =>
    props.options.domain.map((value) => ({
        value,
        label: t(`baseCv.domain.${value}`),
    })),
);
const countries = computed(() => {
    const names = new Intl.DisplayNames([locale.value], { type: 'region' });
    return props.options.country.map((value) => ({
        value,
        label: names.of(value) ?? value,
    }));
});
const wilayas = computed(() =>
    props.options.wilaya.map((x) => ({
        value: String(x.id),
        label: locale.value === 'ar' ? x.ar : x.fr,
    })),
);
const showExternalLink = computed(
    () =>
        props.options.category.find((x) => x.value === form.category)
            ?.externalLink ?? false,
);
watch(
    () => form.country,
    (country) => {
        if (country !== 'MR') form.wilaya_id = '';
    },
);
watch(showExternalLink, (show) => {
    if (!show) form.external_url = '';
});
const backUrl = computed(() =>
    props.family === 'all'
        ? '/staff/announcements/all'
        : `/staff/${props.family === 'offer' ? 'offers' : props.family === 'call' ? 'calls' : 'notices'}`,
);
const showArabic = ref(
    Boolean(props.announcement?.title_ar || props.announcement?.description_ar),
);
const pageHeading = computed(() => {
    if (props.announcement) return 'Modifier une annonce';
    if (props.family === 'offer') return 'Nouvelle offre';
    if (props.family === 'call') return "Nouvel appel d'offres";
    if (props.family === 'notice') return 'Nouvel avis';
    return 'Nouvelle annonce';
});
function submit() {
    if (props.announcement) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(
            `/staff/announcements/${props.announcement.id}`,
            { forceFormData: true },
        );
    } else {
        form.post('/staff/announcements', { forceFormData: true });
    }
}
function chooseDocuments(event: Event): void {
    const input = event.target as HTMLInputElement;
    form.documents = Array.from(input.files ?? []);
}
function removeAttachment(id: number): void {
    if (
        props.announcement &&
        window.confirm('Supprimer définitivement ce document ?')
    ) {
        router.delete(
            `/staff/announcements/${props.announcement.id}/attachments/${id}`,
        );
    }
}
const fieldClass = 'grid content-start gap-1 text-sm';
</script>

<template>
    <Head :title="pageHeading" />
    <div class="flex flex-1 flex-col gap-6 bg-white p-5 md:p-8">
        <h1 class="text-3xl font-normal text-[#5a5c69]">{{ pageHeading }}</h1>
        <form
            class="legacy-admin-card max-w-5xl rounded border border-[#e3e6f0] shadow-[0_0.15rem_1.75rem_0_rgba(58,59,69,0.15)]"
            novalidate
            @submit.prevent="submit"
        >
            <div class="border-b border-[#e3e6f0] bg-[#f8f9fc] px-5 py-3">
                <h2 class="font-bold text-[#4e73df]">{{ pageHeading }}</h2>
            </div>
            <div class="grid gap-4 p-5 md:grid-cols-2">
                <div :class="fieldClass" class="md:col-span-2">
                    <Label for="title"
                        >{{ t('announcement.fields.title') }}
                        <span class="text-[#e74a3b]">*</span></Label
                    >
                    <RichTextEditor v-model="form.title" name="title" />
                    <InputError :message="form.errors.title" />
                </div>

                <div
                    id="category-section"
                    :class="fieldClass"
                    class="scroll-mt-6 md:col-span-2"
                >
                    <Label for="category"
                        >{{ t('announcement.fields.category') }}
                        <span class="text-[#e74a3b]">*</span></Label
                    >
                    <NativeSelect
                        id="category"
                        v-model="form.category"
                        :options="categories"
                        :placeholder="t('announcement.form.choose')"
                        required
                        :aria-invalid="!!form.errors.category"
                    />
                    <InputError :message="form.errors.category" />
                </div>

                <div :class="fieldClass" class="md:col-span-2">
                    <Label for="description"
                        >{{ t('announcement.fields.description') }}
                        <span class="text-[#e74a3b]">*</span></Label
                    >
                    <RichTextEditor v-model="form.description" name="description" />
                    <InputError :message="form.errors.description" />
                </div>

                <div class="md:col-span-2">
                    <Button
                        type="button"
                        size="sm"
                        class="!border-[#36b9cc] !bg-[#36b9cc] hover:!bg-[#2c9faf]"
                        @click="showArabic = !showArabic"
                    >
                        <Plus class="size-4" /> Traduction en arabe
                    </Button>
                </div>

                <section
                    v-show="showArabic"
                    id="arabic-translation"
                    class="grid scroll-mt-6 gap-4 rounded border border-[#e3e6f0] bg-[#f8f9fc] p-4 md:col-span-2"
                >
                    <div
                        class="rounded border border-[#ffeeba] bg-[#fff3cd] px-4 py-3 text-sm text-[#856404]"
                    >
                        Pour ajouter la traduction, renseignez le titre et la
                        description arabes ensemble.
                    </div>
                    <div :class="fieldClass">
                        <Label for="title_ar">Titre arabe</Label>
                        <RichTextEditor v-model="form.title_ar" name="title_ar" />
                        <InputError :message="form.errors.title_ar" />
                    </div>
                    <div :class="fieldClass">
                        <Label for="description_ar">Description arabe</Label>
                        <RichTextEditor v-model="form.description_ar" name="description_ar" />
                        <InputError :message="form.errors.description_ar" />
                    </div>
                </section>

                <div :class="fieldClass" class="md:col-span-2">
                    <Label for="employer"
                        >Employeur <span class="text-[#e74a3b]">*</span></Label
                    >
                    <NativeSelect
                        id="employer"
                        v-model="form.employer_profile_id"
                        :options="employers"
                        placeholder="Sélectionner..."
                        required
                        :disabled="!!announcement"
                        :aria-invalid="!!form.errors.employer_profile_id"
                    />
                    <InputError :message="form.errors.employer_profile_id" />
                </div>

                <div :class="fieldClass">
                    <Label for="deadline"
                        >{{ t('announcement.fields.deadline_at') }}
                        <span class="text-[#e74a3b]">*</span></Label
                    >
                    <Input
                        id="deadline"
                        v-model="form.deadline_at"
                        type="datetime-local"
                        :min="new Date().toISOString().slice(0, 16)"
                        required
                        :aria-invalid="!!form.errors.deadline_at"
                    />
                    <InputError :message="form.errors.deadline_at" />
                </div>
                <div :class="fieldClass">
                    <Label for="domain">{{
                        t('announcement.fields.domain')
                    }}</Label>
                    <NativeSelect
                        id="domain"
                        v-model="form.domain"
                        :options="domains"
                        :placeholder="t('announcement.form.choose')"
                        :aria-invalid="!!form.errors.domain"
                    />
                    <InputError :message="form.errors.domain" />
                </div>
                <div :class="fieldClass">
                    <Label for="country"
                        >{{ t('announcement.fields.country') }}
                        <span class="text-[#e74a3b]">*</span></Label
                    >
                    <NativeSelect
                        id="country"
                        v-model="form.country"
                        :options="countries"
                        :placeholder="t('announcement.form.choose')"
                        required
                        :aria-invalid="!!form.errors.country"
                    />
                    <InputError :message="form.errors.country" />
                </div>
                <div v-if="form.country === 'MR'" :class="fieldClass">
                    <Label for="wilaya"
                        >Lieu <span class="text-[#e74a3b]">*</span></Label
                    >
                    <NativeSelect
                        id="wilaya"
                        v-model="form.wilaya_id"
                        :options="wilayas"
                        placeholder="Sélectionner..."
                        required
                        :aria-invalid="!!form.errors.wilaya_id"
                    />
                    <InputError :message="form.errors.wilaya_id" />
                </div>
                <div
                    v-if="showExternalLink"
                    :class="fieldClass"
                    class="md:col-span-2"
                >
                    <Label for="external_url">{{
                        t('announcement.fields.external_url')
                    }}</Label>
                    <Input
                        id="external_url"
                        v-model="form.external_url"
                        type="url"
                        maxlength="255"
                        placeholder="https://"
                        dir="ltr"
                        :aria-invalid="!!form.errors.external_url"
                    />
                    <InputError :message="form.errors.external_url" />
                </div>

                <fieldset
                    class="space-y-3 rounded border border-[#e3e6f0] p-4 md:col-span-2"
                >
                    <legend class="px-1 font-medium">Documents joints</legend>
                    <p class="text-xs text-[#858796]">
                        PDF, Word, Excel ou ZIP — 10 Mo maximum par fichier.
                    </p>
                    <input
                        id="documents"
                        type="file"
                        multiple
                        accept=".pdf,.doc,.docx,.xlsx,.zip"
                        class="block w-full bg-white text-sm"
                        @change="chooseDocuments"
                    />
                    <InputError :message="form.errors.documents" />
                    <ul
                        v-if="announcement?.attachments.length"
                        class="divide-y rounded border text-sm"
                    >
                        <li
                            v-for="file in announcement.attachments"
                            :key="file.id"
                            class="flex items-center justify-between gap-3 p-2"
                        >
                            <span class="min-w-0 truncate">{{
                                file.name
                            }}</span>
                            <Button
                                type="button"
                                size="sm"
                                variant="destructive"
                                @click="removeAttachment(file.id)"
                                >Supprimer</Button
                            >
                        </li>
                    </ul>
                </fieldset>

                <div class="flex justify-end gap-2 md:col-span-2">
                    <Button variant="outline" as-child
                        ><Link :href="backUrl">Annuler</Link></Button
                    >
                    <Button
                        type="submit"
                        :disabled="form.processing"
                        class="!border-[#1cc88a] !bg-[#1cc88a] hover:!bg-[#17a673]"
                    >
                        <Save class="size-4" />
                        {{ announcement ? 'Enregistrer' : 'Ajouter' }}
                    </Button>
                </div>
            </div>
        </form>
    </div>
</template>
