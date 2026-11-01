<script setup lang="ts">
import DOMPurify from 'dompurify';
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ApplicationController from '@/actions/App/Http/Controllers/Candidate/ApplicationController';
import InputError from '@/components/InputError.vue';
import { useI18n } from 'vue-i18n';
import PublicAnnouncementController from '@/actions/App/Http/Controllers/PublicAnnouncementController';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import { useAnnouncementFormat } from '@/composables/useAnnouncementFormat';
import { login, register } from '@/routes';
import { index } from '@/routes/announcements';
import { show as baseCv } from '@/routes/candidate/cv';
import { index as myApplications } from '@/routes/candidate/applications';
import type { PublicAnnouncement } from '@/types/publicAnnouncement';

const { t, locale } = useI18n();
const format = useAnnouncementFormat();
const page = usePage();
const applyError = computed(
    () => (page.props.errors as Record<string, string>).apply,
);
const featureError = computed(
    () => (page.props.errors as Record<string, string>).feature,
);
function formatDate(value: string): string {
    return new Intl.DateTimeFormat(locale.value, { dateStyle: 'long' }).format(
        new Date(value),
    );
}

const props = defineProps<{
    announcement: PublicAnnouncement;
    company: {
        id: number;
        name: string;
        organisationType: string | null;
        logoUrl: string | null;
        counts: { offers: number; calls: number; notices: number };
    };
    canClose: boolean;
    featuring: { featured: boolean } | null;
    application: {
        viewer: 'guest' | 'candidate' | 'other';
        open: boolean;
        cvComplete: boolean;
        applied: { at: string; status: 'submitted' | 'withdrawn' } | null;
    } | null;
}>();

const pageUrl = computed(() =>
    typeof window === 'undefined' ? '' : window.location.href,
);
const encodedPageUrl = computed(() => encodeURIComponent(pageUrl.value));
const encodedTitle = computed(() =>
    encodeURIComponent(props.announcement.title),
);
const companyAnnouncementsUrl = computed(() =>
    index({ query: { employer: props.company.id } }),
);
const companySectionUrl = (section: 'offers' | 'calls' | 'notices') =>
    index({ query: { employer: props.company.id, section } });

const sanitizedDescription = computed(() => {
    if (props.announcement.descriptionFormat !== 'html') {
        return null;
    }
    return DOMPurify.sanitize(props.announcement.description, {
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
    });
});
</script>

<template>
    <Head :title="announcement.title" />

    <article class="legacy-announcement-page">
        <Alert
            v-if="announcement.closed"
            variant="destructive"
            data-test="announcement-closed"
        >
            <AlertTitle>{{ t('announcements.closedTitle') }}</AlertTitle>
            <AlertDescription>{{
                t('announcements.closedDescription')
            }}</AlertDescription>
        </Alert>

        <section class="legacy-special-shell">
            <div class="legacy-special-topline">
                <h1>{{ company.name }}</h1>
                <Link v-if="!page.props.auth.user" :href="login()" class="legacy-client-account">
                    Compte Client
                </Link>
            </div>
            <nav class="legacy-special-tabs" aria-label="Annonces de l'entreprise">
                <a href="#announcement-detail" class="active" aria-current="page">ⓘ</a>
                <Link :href="companySectionUrl('offers')">
                    ☷ <strong>{{ t('home.offers') }} (<span>{{ company.counts.offers || '-' }}</span>)</strong>
                </Link>
                <Link :href="companySectionUrl('calls')">
                    ☷ <strong>{{ t('home.calls') }} (<span>{{ company.counts.calls || '-' }}</span>)</strong>
                </Link>
                <Link :href="companySectionUrl('notices')">
                    ☷ <strong>{{ t('home.notices') }} (<span>{{ company.counts.notices || '-' }}</span>)</strong>
                </Link>
            </nav>
        </section>

        <div id="announcement-detail" class="legacy-announcement-detail">
            <header class="legacy-offer-header">
                <img
                    v-if="company.logoUrl"
                    :src="company.logoUrl"
                    :alt="t('settings.company.logo.alt')"
                    class="legacy-company-logo"
                />
                <div class="legacy-offer-heading">
                    <p class="legacy-company-name">{{ company.name }}</p>
                    <h1 dir="auto">
                        {{ announcement.title }}
                    </h1>
                    <div class="legacy-offer-meta">
                        <p v-if="announcement.deadlineAt">
                            <strong
                                >{{
                                    t('announcements.deadlineLabel')
                                }}
                                :</strong
                            >
                            <span>{{
                                formatDate(announcement.deadlineAt)
                            }}</span>
                        </p>
                        <p v-if="announcement.location">
                            <strong
                                >{{
                                    t('announcements.locationLabel')
                                }}
                                :</strong
                            >
                            <span>{{
                                format.location(announcement.location)
                            }}</span>
                        </p>
                    </div>
                </div>
            </header>

            <div
                v-if="sanitizedDescription !== null"
                class="legacy-offer-description prose max-w-none [overflow-wrap:anywhere]"
                dir="auto"
                data-test="announcement-description"
                v-html="sanitizedDescription"
            />
            <div
                v-else
                class="legacy-offer-description [overflow-wrap:anywhere] whitespace-pre-line"
                dir="auto"
                data-test="announcement-description"
            >
                {{ announcement.description }}
            </div>

            <footer class="legacy-offer-footer">
                <div class="legacy-publication-row">
                    <p v-if="announcement.publishedAt">
                        <strong>{{ t('announcements.onlineSince') }} :</strong>
                        <span>{{ formatDate(announcement.publishedAt) }}</span>
                    </p>
                    <div
                        class="legacy-share-links"
                        aria-label="Partager cette offre"
                    >
                        <strong>↪ {{ t('announcements.share') }}</strong>
                        <a
                            :href="`https://www.facebook.com/sharer/sharer.php?u=${encodedPageUrl}`"
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="Facebook"
                            class="share-facebook"
                            >f</a
                        >
                        <a
                            :href="`https://www.linkedin.com/sharing/share-offsite/?url=${encodedPageUrl}`"
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="LinkedIn"
                            class="share-linkedin"
                            >in</a
                        >
                        <a
                            :href="`https://wa.me/?text=${encodedTitle}%20${encodedPageUrl}`"
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="WhatsApp"
                            class="share-whatsapp"
                            >W</a
                        >
                    </div>
                </div>
                <div class="legacy-offer-actions">
                    <Link :href="index()">{{
                        t('announcements.completeList')
                    }}</Link>
                    <Link :href="companyAnnouncementsUrl">
                        {{ t('announcements.allFromCompany') }} :
                        <strong>{{ company.name }}</strong>
                    </Link>
                </div>
            </footer>
        </div>

        <section v-if="announcement.attachments.length > 0" class="space-y-2">
            <h2 class="font-semibold">
                {{ t('announcement.attachments.heading') }}
            </h2>
            <ul class="divide-y rounded-md border">
                <li
                    v-for="file in announcement.attachments"
                    :key="file.id"
                    class="flex items-center justify-between gap-4 px-4 py-2 text-sm"
                >
                    <!-- A plain link: the browser must receive the file itself. -->
                    <a
                        :href="file.url"
                        class="[overflow-wrap:anywhere] underline underline-offset-4"
                        >{{ file.name }}</a
                    >
                    <span class="text-muted-foreground shrink-0">{{
                        format.size(file.size)
                    }}</span>
                </li>
            </ul>
        </section>

        <section
            v-if="application"
            class="space-y-3 rounded-md border p-4"
            data-test="apply-area"
        >
            <h2 class="font-semibold">{{ t('applications.heading') }}</h2>

            <template v-if="application.applied">
                <p data-test="already-applied">
                    {{
                        application.applied.status === 'withdrawn'
                            ? t('applications.withdrawnOn', {
                                  date: formatDate(application.applied.at),
                              })
                            : t('applications.appliedOn', {
                                  date: formatDate(application.applied.at),
                              })
                    }}
                </p>
                <Link
                    :href="myApplications()"
                    class="text-sm underline underline-offset-4"
                    >{{ t('applications.mine') }}</Link
                >
            </template>

            <p v-else-if="!application.open" class="text-muted-foreground">
                {{ t('applications.closed') }}
            </p>

            <p v-else-if="application.viewer === 'guest'">
                <Link :href="login()" class="underline underline-offset-4">{{
                    t('applications.login')
                }}</Link>
                ·
                <Link :href="register()" class="underline underline-offset-4">{{
                    t('applications.register')
                }}</Link>
            </p>

            <p
                v-else-if="application.viewer === 'other'"
                class="text-muted-foreground"
            >
                {{ t('applications.candidatesOnly') }}
            </p>

            <div
                v-else-if="!application.cvComplete"
                class="space-y-2"
                data-test="apply-cv-incomplete"
            >
                <p>{{ t('applications.completeCvFirst') }}</p>
                <Button variant="outline" as-child>
                    <Link :href="baseCv()">{{
                        t('applications.completeCv')
                    }}</Link>
                </Button>
            </div>

            <Dialog v-else>
                <DialogTrigger as-child>
                    <Button data-test="apply">{{
                        t('applications.apply')
                    }}</Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{{
                            t('applications.confirmTitle')
                        }}</DialogTitle>
                        <DialogDescription class="space-y-2">
                            <span class="block">{{
                                t('applications.confirmShared')
                            }}</span>
                            <span class="block">{{
                                t('applications.confirmOnce')
                            }}</span>
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button variant="secondary">{{
                                t('common.cancel')
                            }}</Button>
                        </DialogClose>
                        <Form
                            v-bind="
                                ApplicationController.store.form(
                                    announcement.id,
                                )
                            "
                            v-slot="{ processing }"
                        >
                            <Button
                                type="submit"
                                :disabled="processing"
                                data-test="confirm-apply"
                                >{{ t('applications.apply') }}</Button
                            >
                        </Form>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <InputError :message="applyError" />
        </section>

        <Dialog v-if="canClose">
            <DialogTrigger as-child>
                <Button variant="outline" data-test="close-announcement">{{
                    t('announcements.close')
                }}</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{
                        t('announcements.closeTitle')
                    }}</DialogTitle>
                    <DialogDescription>{{
                        t('announcements.closeDescription')
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
                            PublicAnnouncementController.close.form(
                                announcement.id,
                            )
                        "
                        v-slot="{ processing }"
                    >
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="processing"
                            data-test="confirm-close-announcement"
                            >{{ t('announcements.close') }}</Button
                        >
                    </Form>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <section
            v-if="featuring"
            class="flex flex-wrap items-center gap-3 rounded-md border p-4 print:hidden"
            data-test="featuring"
        >
            <p class="flex-1 text-sm">
                {{
                    featuring.featured
                        ? t('announcements.featuredOn')
                        : t('announcements.featuredOff')
                }}
            </p>
            <Form
                v-bind="
                    featuring.featured
                        ? PublicAnnouncementController.unfeature.form(
                              announcement.id,
                          )
                        : PublicAnnouncementController.feature.form(
                              announcement.id,
                          )
                "
                v-slot="{ processing }"
            >
                <Button
                    type="submit"
                    :variant="featuring.featured ? 'outline' : 'default'"
                    :disabled="processing"
                    data-test="toggle-featured"
                    >{{
                        featuring.featured
                            ? t('announcements.unfeature')
                            : t('announcements.feature')
                    }}</Button
                >
            </Form>
            <InputError :message="featureError" class="w-full" />
        </section>
    </article>
</template>

<style scoped>
.legacy-announcement-page {
    max-width: 100%;
}
.legacy-announcement-page > * + * {
    margin-top: 8px;
}
.legacy-special-shell {
    padding: 12px 16px 0;
    background: #fff;
    font: 14px Arial, sans-serif;
}
.legacy-special-topline {
    position: relative;
    min-height: 34px;
    text-align: center;
}
.legacy-special-topline h1 {
    margin: 0;
    color: #079ab8;
    font-size: 17px;
    font-weight: 700;
}
.legacy-client-account {
    position: absolute;
    top: 0;
    right: 0;
    color: #079ab8;
    font-size: 17px;
    text-decoration: none;
}
.legacy-special-tabs {
    display: flex;
    justify-content: center;
    border-bottom: 1px solid #dee2e6;
}
.legacy-special-tabs a {
    margin-bottom: -1px;
    padding: 8px 16px;
    border: 1px solid transparent;
    border-radius: 4px 4px 0 0;
    color: #4e73df;
    text-decoration: none;
}
.legacy-special-tabs a.active {
    border-color: #dee2e6 #dee2e6 #fff;
    background: #fff;
    color: #6e707e;
}
.legacy-special-tabs span {
    color: #e74a3b;
}
.legacy-announcement-detail {
    overflow: hidden;
    border: 1px solid #e3e6f0;
    border-radius: 4px;
    background: #fff;
    font: 14px Arial, sans-serif;
}
.legacy-offer-header {
    display: grid;
    grid-template-columns: 16.6667% minmax(0, 1fr);
    align-items: center;
    min-height: 145px;
    padding: 8px;
    font-family: Arial, sans-serif;
}
.legacy-company-logo {
    width: 120px;
    height: 120px;
    border: 0;
    border-radius: 0;
    object-fit: contain;
}
.legacy-company-name {
    color: #060606;
    font-size: 14px;
    font-weight: 700;
    line-height: 1.2;
}
.legacy-offer-header h1 {
    margin: 0;
    color: #079ab8;
    font-size: 14px;
    font-weight: 700;
    line-height: 1.25;
}
.legacy-offer-meta {
    display: flex;
    justify-content: space-between;
    gap: 24px;
    margin-top: 16px;
    color: #050505;
    font-size: 14px;
}
.legacy-offer-meta span,
.legacy-publication-row p span {
    color: #f04438;
}
.legacy-offer-description {
    min-height: 120px;
    padding: 8px 10px 35px;
    color: #000;
    font-family: Arial, sans-serif;
    font-size: 14px;
    line-height: 1.5;
}
.legacy-offer-description:deep(p) {
    margin-top: 0.9em;
    margin-bottom: 0.9em;
}
.legacy-offer-description:deep(a) {
    color: #4c6ef5;
    font-weight: 700;
    text-decoration: none;
}
.legacy-offer-description:deep(ul),
.legacy-offer-description:deep(ol) {
    padding-inline-start: 2em;
}
.legacy-publication-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    min-height: 51px;
    padding: 9px 24px;
    border-top: 1px solid #dfe3eb;
    background: #f8f9fc;
    font:
        14px Arial,
        sans-serif;
}
.legacy-share-links {
    display: flex;
    align-items: center;
    gap: 10px;
    white-space: nowrap;
}
.legacy-share-links a {
    display: inline-flex;
    width: 21px;
    height: 21px;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    color: #fff;
    font:
        700 13px Arial,
        sans-serif;
    text-decoration: none;
}
.share-facebook {
    background: #5b7bd5;
}
.share-linkedin {
    background: #4f73c5;
    border-radius: 2px !important;
}
.share-whatsapp {
    background: #18bd75;
}
.legacy-offer-actions {
    display: flex;
    gap: 4px;
    padding: 0 10px 15px;
    background: #fff;
    font:
        14px Arial,
        sans-serif;
}
.legacy-offer-actions a {
    display: inline-block;
    overflow: hidden;
    padding: 10px 12px;
    border-radius: 3px;
    background: #4d70df;
    box-shadow: 0 2px 5px rgb(51 75 160 / 35%);
    color: #fff;
    text-decoration: none;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.legacy-offer-actions a:hover {
    background: #3f62d2;
}
.legacy-announcement-page > section {
    border-radius: 3px;
}
.legacy-announcement-page > section[data-test='apply-area'] {
    background: #f8f9fc;
}
@media (max-width: 767px) {
    .legacy-special-topline {
        padding-bottom: 8px;
    }
    .legacy-client-account {
        position: static;
        display: block;
        margin-top: 6px;
    }
    .legacy-special-tabs {
        justify-content: flex-start;
        overflow-x: auto;
    }
    .legacy-special-tabs a {
        flex: 0 0 auto;
        padding: 8px 10px;
    }
    .legacy-offer-header {
        grid-template-columns: 82px minmax(0, 1fr);
        align-items: start;
        min-height: 0;
        padding: 16px 12px;
    }
    .legacy-company-logo {
        width: 72px;
        height: 72px;
    }
    .legacy-offer-meta,
    .legacy-publication-row,
    .legacy-offer-actions {
        align-items: flex-start;
        flex-direction: column;
    }
    .legacy-offer-meta {
        gap: 6px;
        margin-top: 14px;
    }
    .legacy-offer-description {
        padding: 12px 14px 35px;
        font-size: 14px;
    }
    .legacy-publication-row {
        padding: 12px 14px;
    }
    .legacy-share-links {
        flex-wrap: wrap;
        white-space: normal;
    }
    .legacy-offer-actions {
        gap: 6px;
        padding: 8px 10px 12px;
    }
    .legacy-offer-actions a {
        width: 100%;
        white-space: normal;
    }
}
</style>
