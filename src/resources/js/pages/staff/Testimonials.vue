<script setup lang="ts">
import { computed, watchEffect } from 'vue';
import { Check, EyeOff, Trash2 } from '@lucide/vue';
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import TestimonialController from '@/actions/App/Http/Controllers/Staff/TestimonialController';
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
import { index } from '@/routes/staff/testimonials';

type Testimonial = {
    id: number;
    name: string;
    jobTitle: string;
    body: string;
    sentAt: string;
    approvedAt: string | null;
    approvedBy: string | null;
    photoUrl: string | null;
};

const props = defineProps<{
    pending: Testimonial[];
    approved: Testimonial[];
}>();
const testimonials = computed(() => [...props.pending, ...props.approved]);

const { t, locale } = useI18n();

watchEffect(() =>
    setLayoutProps({
        breadcrumbs: [
            { title: t('staff.testimonials.heading'), href: index() },
        ],
    }),
);

function formatDate(value: string): string {
    return new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'medium',
    }).format(new Date(value));
}
</script>

<template>
    <Head :title="t('staff.testimonials.heading')" />

    <div class="flex h-full flex-1 flex-col gap-6 bg-white p-5 md:p-8">
        <h1 class="text-3xl font-normal text-[#5a5c69]">
            {{ t('staff.testimonials.heading') }}
        </h1>
        <div class="overflow-hidden rounded-md border border-[#e3e6f0] shadow-[0_.15rem_1.75rem_0_rgba(58,59,69,.12)]">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-base">
                    <thead class="bg-[#f8f9fc] text-[#4e73df]"><tr><th class="px-4 py-3 text-start">#</th><th class="px-4 py-3 text-start">Nom</th><th class="px-4 py-3 text-start">Poste actuel</th><th class="px-4 py-3 text-start">État</th><th class="px-4 py-3 text-start">Actions</th></tr></thead>
                    <tbody class="divide-y divide-[#e3e6f0]">
                        <tr v-for="item in testimonials" :key="item.id" :data-test="`testimonial-${item.id}`">
                            <td class="px-4 py-3"><img v-if="item.photoUrl" :src="item.photoUrl" :alt="t('staff.testimonials.photoAlt', { name: item.name })" class="size-12 rounded-full border object-cover" :data-test="`testimonial-photo-${item.id}`" /><span v-else>{{ item.id }}</span></td>
                            <td class="px-4 py-3"><strong>{{ item.name }}</strong><div class="max-w-md truncate text-sm text-[#858796]" :title="item.body">{{ item.body }}</div></td>
                            <td class="px-4 py-3">{{ item.jobTitle }}</td>
                            <td class="px-4 py-3">{{ item.approvedAt ? t('staff.testimonials.published') : t('staff.testimonials.waiting') }}</td>
                            <td class="px-4 py-3"><div class="flex gap-0">
                    <Form
                        v-if="!item.approvedAt"
                        v-bind="TestimonialController.approve.form(item.id)"
                        v-slot="{ processing }"
                    >
                        <button
                            type="submit"
                            class="legacy-action bg-[#1cc88a]"
                            :title="t('staff.testimonials.approve')"
                            :disabled="processing"
                            :data-test="`approve-testimonial-${item.id}`"
                            ><Check class="size-4" /></button>
                    </Form>
                    <Form
                        v-else
                        v-bind="TestimonialController.unpublish.form(item.id)"
                        v-slot="{ processing }"
                    >
                        <button
                            type="submit"
                            class="legacy-action bg-[#5a5c69]"
                            :title="t('staff.testimonials.unpublish')"
                            :disabled="processing"
                            :data-test="`unpublish-testimonial-${item.id}`"
                            ><EyeOff class="size-4" /></button>
                    </Form>
                    <Dialog>
                        <DialogTrigger as-child>
                            <button
                                class="legacy-action bg-[#e74a3b]"
                                type="button"
                                :title="t('staff.testimonials.delete')"
                                :data-test="`delete-testimonial-${item.id}`"
                                ><Trash2 class="size-4" /></button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>{{
                                    t('staff.testimonials.deleteTitle')
                                }}</DialogTitle>
                                <DialogDescription>{{
                                    t('staff.testimonials.deleteDescription')
                                }}</DialogDescription>
                            </DialogHeader>
                            <DialogFooter class="gap-2">
                                <DialogClose as-child>
                                    <Button variant="secondary">{{
                                        t('common.cancel')
                                    }}</Button>
                                </DialogClose>
                                <Form
                                    v-bind="
                                        TestimonialController.destroy.form(
                                            item.id,
                                        )
                                    "
                                    v-slot="{ processing }"
                                >
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        :disabled="processing"
                                        :data-test="`confirm-delete-testimonial-${item.id}`"
                                        >{{
                                            t('staff.testimonials.delete')
                                        }}</Button
                                    >
                                </Form>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                            </div></td>
                        </tr>
                        <tr v-if="testimonials.length === 0"><td colspan="5" class="px-4 py-10 text-center text-[#858796]" data-test="testimonials-pending-empty">{{ t('staff.testimonials.empty') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
