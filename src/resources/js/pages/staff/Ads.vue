<script setup lang="ts">
import { watchEffect } from 'vue';
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Pencil, Plus, Power, Trash2 } from '@lucide/vue';
import AdBannerForm from '@/components/AdBannerForm.vue';
import { useI18n } from 'vue-i18n';
import AdBannerController from '@/actions/App/Http/Controllers/Staff/AdBannerController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { index } from '@/routes/staff/ads';

type Banner = {
    id: number;
    title: string;
    imageUrl: string;
    linkUrl: string | null;
    documentName: string | null;
    documentUrl: string | null;
    endsOn: string;
    placement: string;
    employer: string | null;
    active: boolean;
    isEnabled: boolean;
    displayOrder: number;
    employerProfileId: number | null;
    announcementId: number | null;
    announcement: string | null;
    createdBy: string | null;
};

defineProps<{
    banners: Banner[];
    placements: string[];
    employers: { id: number; name: string }[];
    announcements: { id: number; name: string }[];
}>();

const { t, locale } = useI18n();

watchEffect(() =>
    setLayoutProps({
        breadcrumbs: [{ title: t('staff.ads.heading'), href: index() }],
    }),
);

const editing = ref<Banner | null>(null);
const creating = ref(false);

function formatDay(value: string): string {
    return new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'long',
        timeZone: 'UTC',
    }).format(new Date(`${value}T00:00:00Z`));
}
</script>

<template>
    <Head :title="t('staff.ads.heading')" />

    <div class="flex h-full flex-1 flex-col gap-6 bg-white p-5 md:p-8">
        <div class="flex items-end justify-between gap-4">
            <h1 class="text-3xl font-normal text-[#5a5c69]">
                {{ t('staff.ads.heading') }}
            </h1>
            <Button size="sm" @click="creating = true">
                <Plus class="size-4" />
                {{ t('staff.ads.addTitle') }}
            </Button>
        </div>

        <div class="overflow-hidden rounded-md border border-[#e3e6f0] shadow-[0_.15rem_1.75rem_0_rgba(58,59,69,.12)]">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-base">
                    <thead class="bg-[#f8f9fc] text-[#4e73df]">
                        <tr>
                            <th class="px-4 py-3 text-start">#</th>
                            <th class="px-4 py-3 text-start">{{ t('staff.ads.title') }}</th>
                            <th class="px-4 py-3 text-start">{{ t('staff.ads.endsOn') }}</th>
                            <th class="px-4 py-3 text-start">{{ t('staff.ads.status') }}</th>
                            <th class="px-4 py-3 text-start">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e3e6f0]">
                        <tr v-for="b in banners" :key="b.id" :data-test="`ad-banner-${b.id}`">
                            <td class="px-4 py-3">
                                <img :src="b.imageUrl" :alt="b.title" class="h-12 w-20 object-contain" />
                            </td>
                            <td class="px-4 py-3 font-semibold text-[#4e73df]">{{ b.title }}</td>
                            <td class="px-4 py-3">{{ formatDay(b.endsOn) }}</td>
                            <td class="px-4 py-3">
                                {{ !b.isEnabled ? t('staff.ads.disabled') : b.active ? t('staff.ads.active') : t('staff.ads.expired') }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex gap-0">
                                    <button class="legacy-action bg-[#e74a3b]" type="button" :title="t('staff.ads.edit')" @click="editing = b"><Pencil class="size-4" /></button>
                                    <Form v-bind="AdBannerController.enabled.form(b.id)" v-slot="{ processing }">
                                        <input type="hidden" name="is_enabled" :value="b.isEnabled ? '0' : '1'" />
                                        <button class="legacy-action bg-[#5a5c69]" type="submit" :disabled="processing" :title="t(b.isEnabled ? 'staff.ads.disable' : 'staff.ads.enable')"><Power class="size-4" /></button>
                                    </Form>
                                    <Dialog>
                                        <DialogTrigger as-child><button class="legacy-action bg-[#e74a3b]" type="button" :title="t('staff.ads.delete')" :data-test="`delete-ad-${b.id}`"><Trash2 class="size-4" /></button></DialogTrigger>
                                        <DialogContent>
                                            <DialogHeader><DialogTitle>{{ t('staff.ads.deleteTitle') }}</DialogTitle><DialogDescription>{{ t('staff.ads.deleteDescription') }}</DialogDescription></DialogHeader>
                                            <DialogFooter class="gap-2"><DialogClose as-child><Button variant="secondary">{{ t('common.cancel') }}</Button></DialogClose><Form v-bind="AdBannerController.destroy.form(b.id)" v-slot="{ processing }"><Button type="submit" variant="destructive" :disabled="processing" :data-test="`confirm-delete-ad-${b.id}`">{{ t('staff.ads.delete') }}</Button></Form></DialogFooter>
                                        </DialogContent>
                                    </Dialog>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="banners.length === 0"><td colspan="5" class="px-4 py-10 text-center text-[#858796]" data-test="ads-empty">{{ t('staff.ads.empty') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <Dialog :open="creating" @update:open="creating = $event">
            <DialogContent class="max-h-[90vh] grid-cols-[minmax(0,1fr)] overflow-y-auto sm:max-w-2xl">
                <DialogHeader><DialogTitle>{{ t('staff.ads.addTitle') }}</DialogTitle><DialogDescription>{{ t('staff.ads.description') }}</DialogDescription></DialogHeader>
                <AdBannerForm :placements="placements" :employers="employers" :announcements="announcements" @saved="creating = false" />
            </DialogContent>
        </Dialog>
        <Dialog
            :open="editing !== null"
            @update:open="
                (open) => {
                    if (!open) editing = null;
                }
            "
        >
            <DialogContent
                class="max-h-[90vh] grid-cols-[minmax(0,1fr)] overflow-y-auto sm:max-w-2xl"
            >
                <DialogHeader>
                    <DialogTitle>{{ t('staff.ads.edit') }}</DialogTitle>
                    <DialogDescription>{{
                        t('staff.ads.editHelp')
                    }}</DialogDescription>
                </DialogHeader>
                <AdBannerForm
                    v-if="editing"
                    :key="editing.id"
                    :banner="editing"
                    :placements="placements"
                    :employers="employers"
                    :announcements="announcements"
                    @saved="editing = null"
                />
            </DialogContent>
        </Dialog>
    </div>
</template>
