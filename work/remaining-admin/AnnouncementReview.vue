<script setup lang="ts">
import { watchEffect } from 'vue';
import DOMPurify from 'dompurify';
import { Form, Head, setLayoutProps, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AnnouncementReviewController from '@/actions/App/Http/Controllers/Staff/AnnouncementReviewController';
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
import { index } from '@/routes/staff/announcements';

type PendingAnnouncement = {
    id: number;
    version: string;
    category: string;
    title: string;
    description: string;
    descriptionFormat: 'text' | 'html';
    domain: string | null;
    country: string | null;
    wilaya: string | null;
    deadlineAt: string | null;
    externalUrl: string | null;
    submittedAt: string | null;
    republication: boolean;
    liveUrl: string | null;
    company: { name: string; email: string; logoUrl: string | null };
    attachments: { id: number; name: string; size: number; url: string }[];
};

defineProps<{ pending: PendingAnnouncement[] }>();

const { t, locale } = useI18n();
const page = usePage();

watchEffect(() =>
    setLayoutProps({
        breadcrumbs: [
            { title: t('staff.announcements.heading'), href: index() },
        ],
    }),
);

// Errors of a decision (e.g. content changed since the page loaded).
const reviewError = computed(
    () => (page.props.errors as Record<string, string>).review,
);

const regionNames = computed(
    () => new Intl.DisplayNames([locale.value], { type: 'region' }),
);

function location(a: PendingAnnouncement): string {
    const country = a.country
        ? (regionNames.value.of(a.country) ?? a.country)
        : '';

    return [a.wilaya, country].filter(Boolean).join(', ');
}

// Deadlines are Mauritania time = UTC: format without converting.
function formatDeadline(value: string): string {
    return new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'long',
        timeStyle: 'short',
        timeZone: 'UTC',
    }).format(new Date(`${value}:00Z`));
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'medium',
    }).format(new Date(value));
}

function isWebLink(url: string): boolean {
    return /^https?:\/\//i.test(url);
}

const purifyConfig = {
    ALLOWED_TAGS: [
        'p',
        'br',
        'strong',
        'b',
        'em',
        'i',
        'u',
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'ul',
        'ol',
        'li',
        'a',
        'table',
        'thead',
        'tbody',
        'tr',
        'td',
        'th',
        'div',
        'span',
        'blockquote',
        'pre',
    ],
    ALLOWED_ATTR: ['href', 'target', 'rel'],
};

function sanitizedHtml(a: PendingAnnouncement): string | null {
    if (a.descriptionFormat !== 'html') return null;
    return DOMPurify.sanitize(a.description, purifyConfig);
}
</script>

<template>
    <Head :title="t('staff.announcements.heading')" />

    <div class="flex min-h-full flex-1 flex-col gap-4 bg-white p-5 md:p-8">
        <div>
            <h1 class="text-3xl font-normal text-[#5a5c69]">
                {{ t('staff.announcements.heading') }}
            </h1>
            <p class="text-muted-foreground text-sm">
                {{ t('staff.announcements.description') }}
            </p>
        </div>

        <Alert
            v-if="reviewError"
            variant="destructive"
            class="legacy-admin-card w-full max-w-5xl"
            data-test="review-error"
        >
            <AlertDescription>{{ reviewError }}</AlertDescription>
        </Alert>

        <p
            v-if="pending.length === 0"
            class="text-muted-foreground text-sm"
            data-test="review-empty"
        >
            {{ t('staff.announcements.empty') }}
        </p>

        <Card
            v-for="a in pending"
            :key="a.id"
            :id="`announcement-${a.id}`"
            class="max-w-3xl"
            :data-test="`review-${a.id}`"
        >
            <CardHeader class="flex flex-row items-start gap-4">
                <img
                    v-if="a.company.logoUrl"
                    :src="a.company.logoUrl"
                    :alt="t('settings.company.logo.alt')"
                    class="size-12 shrink-0 rounded-md border object-contain"
                />
                <div class="min-w-0 flex-1 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge variant="secondary">{{
                            t(`announcement.category.${a.category}`)
                        }}</Badge>
                        <Badge
                            v-if="a.republication"
                            variant="outline"
                            :data-test="`review-republication-${a.id}`"
                            >{{ t('staff.announcements.republication') }}</Badge
                        >
                    </div>
                    <CardTitle class="[overflow-wrap:anywhere]">{{
                        a.title
                    }}</CardTitle>
                    <a
                        v-if="a.liveUrl"
                        :href="a.liveUrl"
                        target="_blank"
                        class="text-sm underline underline-offset-4"
                        :data-test="`review-live-${a.id}`"
                        >{{ t('staff.announcements.viewLive') }}</a
                    >
                    <p
                        class="text-muted-foreground text-sm [overflow-wrap:anywhere]"
                    >
                        {{ a.company.name }} · {{ a.company.email }}
                    </p>
                </div>
            </CardHeader>

            <CardContent class="space-y-4 text-sm">
                <dl class="grid gap-x-4 gap-y-2 sm:grid-cols-[10rem_1fr]">
                    <template v-if="a.domain">
                        <dt class="text-muted-foreground">
                            {{ t('announcement.fields.domain') }}
                        </dt>
                        <dd>{{ t(`baseCv.domain.${a.domain}`) }}</dd>
                    </template>
                    <dt class="text-muted-foreground">
                        {{ t('announcement.fields.location') }}
                    </dt>
                    <dd>{{ location(a) }}</dd>
                    <template v-if="a.deadlineAt">
                        <dt class="text-muted-foreground">
                            {{ t('announcement.fields.deadline_at') }}
                        </dt>
                        <dd>{{ formatDeadline(a.deadlineAt) }}</dd>
                    </template>
                    <template v-if="a.externalUrl">
                        <dt class="text-muted-foreground">
                            {{ t('announcement.fields.external_url') }}
                        </dt>
                        <dd class="[overflow-wrap:anywhere]">
                            <a
                                v-if="isWebLink(a.externalUrl)"
                                :href="a.externalUrl"
                                target="_blank"
                                rel="noopener noreferrer nofollow"
                                class="underline underline-offset-4"
                                >{{ a.externalUrl }}</a
                            >
                            <template v-else>{{ a.externalUrl }}</template>
                        </dd>
                    </template>
                    <template v-if="a.submittedAt">
                        <dt class="text-muted-foreground">
                            {{ t('staff.announcements.submittedAt') }}
                        </dt>
                        <dd>{{ formatDate(a.submittedAt) }}</dd>
                    </template>
                </dl>

                <div class="rounded-md border p-3">
                    <div
                        v-if="sanitizedHtml(a) !== null"
                        class="prose prose-sm max-w-none [overflow-wrap:anywhere]"
                        :data-test="`review-description-${a.id}`"
                        v-html="sanitizedHtml(a)"
                    />
                    <p
                        v-else
                        class="[overflow-wrap:anywhere] whitespace-pre-line"
                        :data-test="`review-description-${a.id}`"
                    >
                        {{ a.description }}
                    </p>
                </div>

                <div v-if="a.attachments.length > 0" class="space-y-1">
                    <p class="text-muted-foreground">
                        {{ t('announcement.attachments.heading') }}
                    </p>
                    <ul class="space-y-1">
                        <li v-for="file in a.attachments" :key="file.id">
                            <a
                                :href="file.url"
                                class="[overflow-wrap:anywhere] underline underline-offset-4"
                                >{{ file.name }}</a
                            >
                        </li>
                    </ul>
                </div>
            </CardContent>

            <CardFooter class="justify-end gap-2 border-t border-[#e3e6f0] pt-4">
                <Form v-bind="AnnouncementReviewController.approve.form(a.id)">
                    <input type="hidden" name="version" :value="a.version" />
                    <Button type="submit" class="!border-[#1cc88a] !bg-[#1cc88a] hover:!bg-[#17a673]" :data-test="`approve-${a.id}`">{{
                        t('staff.announcements.approve')
                    }}</Button>
                </Form>

                <Dialog>
                    <DialogTrigger as-child>
                        <Button
                            variant="destructive"
                            :data-test="`reject-${a.id}`"
                            >{{ t('staff.announcements.reject') }}</Button
                        >
                    </DialogTrigger>
                    <DialogContent>
                        <Form
                            v-bind="
                                AnnouncementReviewController.reject.form(a.id)
                            "
                            reset-on-success
                            v-slot="{ errors, processing }"
                            class="space-y-6"
                        >
                            <input
                                type="hidden"
                                name="version"
                                :value="a.version"
                            />
                            <DialogHeader class="space-y-3">
                                <DialogTitle>{{
                                    t('staff.announcements.rejectTitle')
                                }}</DialogTitle>
                                <DialogDescription>{{
                                    t('staff.announcements.rejectDescription')
                                }}</DialogDescription>
                            </DialogHeader>
                            <div class="grid gap-2">
                                <Label :for="`reason-${a.id}`">{{
                                    t('staff.announcements.reason')
                                }}</Label>
                                <textarea
                                    :id="`reason-${a.id}`"
                                    name="reason"
                                    rows="4"
                                    maxlength="1000"
                                    class="border-input dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:ring-[3px] md:text-sm"
                                />
                                <InputError :message="errors.reason" />
                            </div>
                            <DialogFooter class="gap-2">
                                <DialogClose as-child>
                                    <Button variant="secondary">{{
                                        t('common.cancel')
                                    }}</Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    :disabled="processing"
                                    :data-test="`confirm-reject-${a.id}`"
                                    >{{
                                        t('staff.announcements.confirmReject')
                                    }}</Button
                                >
                            </DialogFooter>
                        </Form>
                    </DialogContent>
                </Dialog>
            </CardFooter>
        </Card>
    </div>
</template>
