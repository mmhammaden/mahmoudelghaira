<script setup lang="ts">
import { watchEffect } from 'vue';
import { Form, Head, Link, setLayoutProps, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import IntroductionController from '@/actions/App/Http/Controllers/Staff/IntroductionController';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { Label } from '@/components/ui/label';
import { index } from '@/routes/staff/introductions';

type IntroductionRequest = {
    id: number;
    status: 'pending' | 'handled' | 'declined';
    requestedAt: string;
    message: string | null;
    note: string | null;
    handledBy: string | null;
    handledAt: string | null;
    company: {
        name: string;
        email: string;
        phone: string | null;
        requestedBy: { name: string; email: string } | null;
    };
    announcement: { title: string; url: string };
    application: {
        referenceCode: string;
        specialty: string | null;
        appliedAt: string;
        withdrawn: boolean;
    };
    candidate: {
        name: string;
        email: string;
        phone: string | null;
        alternativePhone: string | null;
        firstNameAr: string | null;
        lastNameAr: string | null;
    };
    cvFileUrl: string | null;
};

defineProps<{
    pending: IntroductionRequest[];
    resolved: IntroductionRequest[];
}>();

const { t, locale } = useI18n();
const page = usePage();

watchEffect(() =>
    setLayoutProps({
        breadcrumbs: [
            { title: t('staff.introductions.heading'), href: index() },
        ],
    }),
);

// E.g. the request was closed by someone else meanwhile.
const introductionError = computed(
    () => (page.props.errors as Record<string, string>).introduction,
);

const textareaClass =
    'border-input dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:ring-[3px] md:text-sm';

function formatDate(value: string): string {
    return new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'medium',
    }).format(new Date(value));
}
</script>

<template>
    <Head :title="t('staff.introductions.heading')" />

    <div class="flex min-h-full flex-1 flex-col gap-4 bg-white p-5 md:p-8">
        <div class="max-w-3xl">
            <h1 class="text-3xl font-normal text-[#5a5c69]">
                {{ t('staff.introductions.heading') }}
            </h1>
            <p class="text-muted-foreground text-sm">
                {{ t('staff.introductions.description') }}
            </p>
        </div>

        <Alert
            v-if="introductionError"
            variant="destructive"
            class="max-w-3xl"
            data-test="introduction-error"
        >
            <AlertDescription>{{ introductionError }}</AlertDescription>
        </Alert>

        <h2 class="font-medium">
            {{ t('staff.introductions.pendingTitle') }} ({{ pending.length }})
        </h2>
        <p
            v-if="pending.length === 0"
            class="text-muted-foreground text-sm"
            data-test="introductions-empty"
        >
            {{ t('staff.introductions.empty') }}
        </p>

        <template
            v-for="group in [
                { items: pending, resolved: false },
                { items: resolved, resolved: true },
            ]"
            :key="String(group.resolved)"
        >
            <h2
                v-if="group.resolved && group.items.length > 0"
                class="mt-4 font-medium"
            >
                {{ t('staff.introductions.resolvedTitle') }}
            </h2>

            <Card
                v-for="r in group.items"
                :key="r.id"
                class="legacy-admin-card w-full max-w-5xl"
                :data-test="`introduction-${r.id}`"
            >
                <CardHeader class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge
                            :variant="
                                r.status === 'pending' ? 'default' : 'secondary'
                            "
                            :data-test="`introduction-status-${r.id}`"
                            >{{
                                t(`staff.introductions.status.${r.status}`)
                            }}</Badge
                        >
                        <Badge
                            v-if="r.application.withdrawn"
                            variant="destructive"
                            :data-test="`introduction-withdrawn-${r.id}`"
                            >{{ t('staff.introductions.withdrawn') }}</Badge
                        >
                        <span class="text-muted-foreground text-xs">{{
                            t('staff.introductions.requestedAt', {
                                date: formatDate(r.requestedAt),
                            })
                        }}</span>
                    </div>
                    <CardTitle class="[overflow-wrap:anywhere]">
                        {{ r.company.name }} →
                        {{ r.application.referenceCode }}
                    </CardTitle>
                </CardHeader>

                <CardContent class="space-y-4 text-sm">
                    <dl
                        class="grid gap-x-4 gap-y-2 sm:grid-cols-[11rem_1fr] [&_dd]:[overflow-wrap:anywhere]"
                    >
                        <dt class="text-muted-foreground">
                            {{ t('staff.introductions.company') }}
                        </dt>
                        <dd>
                            {{
                                [
                                    r.company.name,
                                    r.company.email,
                                    r.company.phone,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')
                            }}
                        </dd>
                        <template v-if="r.company.requestedBy">
                            <dt class="text-muted-foreground">
                                {{ t('staff.introductions.requestedBy') }}
                            </dt>
                            <dd>
                                {{ r.company.requestedBy.name }} ·
                                {{ r.company.requestedBy.email }}
                            </dd>
                        </template>
                        <dt class="text-muted-foreground">
                            {{ t('staff.introductions.announcement') }}
                        </dt>
                        <dd>
                            <Link
                                :href="r.announcement.url"
                                class="underline underline-offset-4"
                                >{{ r.announcement.title }}</Link
                            >
                        </dd>
                        <dt class="text-muted-foreground">
                            {{ t('staff.introductions.application') }}
                        </dt>
                        <dd>
                            {{
                                [
                                    r.application.referenceCode,
                                    r.application.specialty,
                                    t('staff.introductions.appliedAt', {
                                        date: formatDate(
                                            r.application.appliedAt,
                                        ),
                                    }),
                                ]
                                    .filter(Boolean)
                                    .join(' · ')
                            }}
                        </dd>
                    </dl>

                    <div
                        class="bg-muted/50 space-y-2 rounded-md border p-3"
                        :data-test="`introduction-candidate-${r.id}`"
                    >
                        <p class="text-muted-foreground text-xs font-medium">
                            {{ t('staff.introductions.candidate') }}
                        </p>
                        <p class="[overflow-wrap:anywhere]">
                            <span class="font-medium">{{
                                r.candidate.name
                            }}</span>
                            ·
                            <a
                                :href="`mailto:${r.candidate.email}`"
                                class="underline underline-offset-4"
                                >{{ r.candidate.email }}</a
                            >
                        </p>
                        <p
                            v-if="
                                r.candidate.firstNameAr ||
                                r.candidate.lastNameAr
                            "
                            dir="rtl"
                            lang="ar"
                            class="text-start [overflow-wrap:anywhere]"
                            :data-test="`introduction-name-ar-${r.id}`"
                        >
                            {{
                                [
                                    r.candidate.firstNameAr,
                                    r.candidate.lastNameAr,
                                ]
                                    .filter(Boolean)
                                    .join(' ')
                            }}
                        </p>
                        <p
                            v-if="
                                r.candidate.phone ||
                                r.candidate.alternativePhone
                            "
                            class="[overflow-wrap:anywhere]"
                            :data-test="`introduction-phones-${r.id}`"
                        >
                            <a
                                v-if="r.candidate.phone"
                                :href="`tel:${r.candidate.phone}`"
                                class="underline underline-offset-4"
                                >{{ r.candidate.phone }}</a
                            >
                            <template
                                v-if="
                                    r.candidate.phone &&
                                    r.candidate.alternativePhone
                                "
                            >
                                ·
                            </template>
                            <a
                                v-if="r.candidate.alternativePhone"
                                :href="`tel:${r.candidate.alternativePhone}`"
                                class="underline underline-offset-4"
                                >{{ r.candidate.alternativePhone }}</a
                            >
                        </p>
                        <a
                            v-if="r.cvFileUrl"
                            :href="r.cvFileUrl"
                            class="inline-block underline underline-offset-4"
                            :data-test="`introduction-cv-${r.id}`"
                            >{{ t('staff.introductions.cvFile') }}</a
                        >
                    </div>

                    <div v-if="r.message" class="space-y-1">
                        <p class="text-muted-foreground text-xs font-medium">
                            {{ t('staff.introductions.message') }}
                        </p>
                        <p class="[overflow-wrap:anywhere] whitespace-pre-line">
                            {{ r.message }}
                        </p>
                    </div>

                    <div v-if="r.status !== 'pending'" class="space-y-1">
                        <p class="text-muted-foreground text-xs">
                            {{
                                r.handledBy
                                    ? t('staff.introductions.handledBy', {
                                          status: t(
                                              `staff.introductions.status.${r.status}`,
                                          ),
                                          name: r.handledBy,
                                          date: formatDate(r.handledAt ?? ''),
                                      })
                                    : t(
                                          'staff.introductions.handledByUnknown',
                                          {
                                              status: t(
                                                  `staff.introductions.status.${r.status}`,
                                              ),
                                              date: formatDate(
                                                  r.handledAt ?? '',
                                              ),
                                          },
                                      )
                            }}
                        </p>
                        <p
                            v-if="r.note"
                            class="[overflow-wrap:anywhere] whitespace-pre-line"
                        >
                            {{ r.note }}
                        </p>
                    </div>
                </CardContent>

                <CardFooter
                    v-if="r.status === 'pending'"
                    class="flex-wrap justify-end gap-2 border-t border-[#e3e6f0] pt-4"
                >
                    <Dialog
                        v-for="action in ['handle', 'decline'] as const"
                        :key="action"
                    >
                        <DialogTrigger as-child>
                            <Button
                                :variant="
                                    action === 'decline' ? 'outline' : 'default'
                                "
                                :class="action === 'handle' ? '!border-[#1cc88a] !bg-[#1cc88a] hover:!bg-[#17a673]' : ''"
                                :data-test="`${action}-${r.id}`"
                                >{{
                                    t(`staff.introductions.${action}`)
                                }}</Button
                            >
                        </DialogTrigger>
                        <DialogContent>
                            <Form
                                v-bind="
                                    IntroductionController[action].form(r.id)
                                "
                                v-slot="{ errors, processing }"
                                class="space-y-6"
                            >
                                <DialogHeader class="space-y-3">
                                    <DialogTitle>{{
                                        t(`staff.introductions.${action}Title`)
                                    }}</DialogTitle>
                                    <DialogDescription>{{
                                        t(
                                            `staff.introductions.${action}Description`,
                                        )
                                    }}</DialogDescription>
                                </DialogHeader>
                                <div class="grid gap-2">
                                    <Label :for="`note-${action}-${r.id}`">{{
                                        action === 'decline'
                                            ? t(
                                                  'staff.introductions.noteRequired',
                                              )
                                            : t(
                                                  'staff.introductions.noteOptional',
                                              )
                                    }}</Label>
                                    <textarea
                                        :id="`note-${action}-${r.id}`"
                                        name="note"
                                        rows="4"
                                        maxlength="2000"
                                        :required="action === 'decline'"
                                        :class="textareaClass"
                                    />
                                    <InputError :message="errors.note" />
                                </div>
                                <DialogFooter class="gap-2">
                                    <DialogClose as-child>
                                        <Button variant="secondary">{{
                                            t('common.cancel')
                                        }}</Button>
                                    </DialogClose>
                                    <Button
                                        type="submit"
                                        :variant="
                                            action === 'decline'
                                                ? 'destructive'
                                                : 'default'
                                        "
                                        :disabled="processing"
                                        :data-test="`confirm-${action}-${r.id}`"
                                        >{{
                                            t(
                                                action === 'decline'
                                                    ? 'staff.introductions.confirmDecline'
                                                    : 'staff.introductions.confirmHandle',
                                            )
                                        }}</Button
                                    >
                                </DialogFooter>
                            </Form>
                        </DialogContent>
                    </Dialog>
                </CardFooter>
            </Card>
        </template>
    </div>
</template>
