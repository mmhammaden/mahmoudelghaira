<script setup lang="ts">
import { watchEffect } from 'vue';
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import EmployerReviewController from '@/actions/App/Http/Controllers/Staff/EmployerReviewController';
import InputError from '@/components/InputError.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import employers from '@/routes/staff/employers';

type Company = {
    name: string;
    nameAr: string | null;
    organisationType: string | null;
    country: string | null;
    commune: string | null;
    address: string | null;
    email: string | null;
    phone: string | null;
    website: string | null;
    nif: string | null;
    description: string | null;
    representative: {
        name: string | null;
        title: string | null;
        phone: string | null;
        email: string | null;
    };
};

type PendingEmployer = {
    id: number;
    name: string;
    email: string;
    registeredAt: string;
    renamed: { from: string | null; to: string | null; at: string } | null;
    logoUrl: string | null;
    company: Company | null;
};

type Decision = {
    id: number;
    employerName: string | null;
    companyName: string | null;
    employerEmail: string | null;
    decision: 'approved' | 'rejected';
    reviewerName: string | null;
    decidedAt: string;
    reason: string | null;
};

defineProps<{
    pendingEmployers: PendingEmployer[];
    decisions: Decision[];
}>();

const { t, locale } = useI18n();

const regionNames = computed(
    () => new Intl.DisplayNames([locale.value], { type: 'region' }),
);

function location(company: Company): string {
    const country = company.country
        ? (regionNames.value.of(company.country) ?? company.country)
        : '';

    return [company.commune, country].filter(Boolean).join(', ');
}

// Only http(s) addresses become links: the form allows nothing else, but
// imported legacy data (Phase 7) does not go through the form.
function isWebLink(url: string): boolean {
    return /^https?:\/\//i.test(url);
}

function hasRepresentative(company: Company): boolean {
    return Object.values(company.representative).some((v) => v);
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'medium',
    }).format(new Date(value));
}

watchEffect(() =>
    setLayoutProps({
        breadcrumbs: [
            {
                title: t('staff.employers.breadcrumb'),
                href: employers.index(),
            },
        ],
    }),
);
</script>

<template>
    <Head :title="t('staff.employers.headTitle')" />

    <div class="flex min-h-full flex-1 flex-col gap-4 bg-white p-5 md:p-8">
        <div>
            <h1 class="text-3xl font-normal text-[#5a5c69]">
                {{ t('staff.employers.heading') }}
            </h1>
            <p class="text-muted-foreground text-sm">
                {{ t('staff.employers.description') }}
            </p>
        </div>

        <p
            v-if="pendingEmployers.length === 0"
            class="text-muted-foreground text-sm"
        >
            {{ t('staff.employers.empty') }}
        </p>

        <Card
            v-for="employer in pendingEmployers"
            :key="employer.id"
            class="legacy-admin-card w-full max-w-5xl"
        >
            <CardHeader class="flex flex-row items-start gap-4">
                <img
                    v-if="employer.logoUrl"
                    :src="employer.logoUrl"
                    :alt="t('settings.company.logo.alt')"
                    class="size-14 shrink-0 rounded-md border object-contain"
                    :data-test="`employer-logo-${employer.id}`"
                />
                <div class="min-w-0 space-y-1">
                    <CardTitle class="[overflow-wrap:anywhere]">{{
                        employer.company?.name ?? employer.name
                    }}</CardTitle>
                    <p
                        v-if="employer.company?.nameAr"
                        dir="rtl"
                        lang="ar"
                        class="text-muted-foreground text-sm"
                    >
                        {{ employer.company.nameAr }}
                    </p>
                    <p
                        v-if="employer.company?.organisationType"
                        class="text-muted-foreground text-sm"
                    >
                        {{
                            t(
                                `company.organisationType.${employer.company.organisationType}`,
                            )
                        }}
                    </p>
                </div>
            </CardHeader>
            <CardContent class="space-y-4 text-sm">
                <div
                    v-if="employer.renamed"
                    class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200"
                    :data-test="`employer-renamed-${employer.id}`"
                >
                    <p class="font-medium">
                        {{ t('staff.employers.renamedTitle') }}
                    </p>
                    <p class="[overflow-wrap:anywhere]">
                        {{
                            t('staff.employers.renamedFromTo', {
                                from: employer.renamed.from,
                                to: employer.renamed.to,
                            })
                        }}
                    </p>
                </div>

                <p
                    v-if="!employer.company"
                    class="text-muted-foreground"
                    :data-test="`employer-no-company-${employer.id}`"
                >
                    {{ t('staff.employers.noCompany') }}
                </p>

                <dl
                    v-else
                    class="grid gap-x-4 gap-y-2 sm:grid-cols-[10rem_1fr]"
                    :data-test="`employer-company-${employer.id}`"
                >
                    <template v-if="location(employer.company)">
                        <dt class="text-muted-foreground">
                            {{ t('staff.employers.location') }}
                        </dt>
                        <dd>{{ location(employer.company) }}</dd>
                    </template>
                    <template v-if="employer.company.address">
                        <dt class="text-muted-foreground">
                            {{ t('settings.company.fields.address') }}
                        </dt>
                        <dd class="[overflow-wrap:anywhere]">
                            {{ employer.company.address }}
                        </dd>
                    </template>
                    <template v-if="employer.company.email">
                        <dt class="text-muted-foreground">
                            {{ t('settings.company.fields.company_email') }}
                        </dt>
                        <dd class="[overflow-wrap:anywhere]">
                            {{ employer.company.email }}
                        </dd>
                    </template>
                    <template v-if="employer.company.phone">
                        <dt class="text-muted-foreground">
                            {{ t('settings.company.fields.company_phone') }}
                        </dt>
                        <dd dir="ltr" class="text-start">
                            {{ employer.company.phone }}
                        </dd>
                    </template>
                    <template v-if="employer.company.website">
                        <dt class="text-muted-foreground">
                            {{ t('settings.company.fields.website') }}
                        </dt>
                        <dd class="[overflow-wrap:anywhere]">
                            <a
                                v-if="isWebLink(employer.company.website)"
                                :href="employer.company.website"
                                target="_blank"
                                rel="noopener noreferrer nofollow"
                                class="underline underline-offset-4"
                                >{{ employer.company.website }}</a
                            >
                            <template v-else>{{
                                employer.company.website
                            }}</template>
                        </dd>
                    </template>
                    <template v-if="employer.company.nif">
                        <dt class="text-muted-foreground">
                            {{ t('settings.company.fields.nif') }}
                        </dt>
                        <dd>{{ employer.company.nif }}</dd>
                    </template>
                    <template v-if="employer.company.description">
                        <dt class="text-muted-foreground">
                            {{ t('settings.company.fields.description') }}
                        </dt>
                        <dd
                            class="[overflow-wrap:anywhere] whitespace-pre-line"
                        >
                            {{ employer.company.description }}
                        </dd>
                    </template>
                    <template v-if="hasRepresentative(employer.company)">
                        <dt class="text-muted-foreground">
                            {{ t('settings.company.representative.heading') }}
                        </dt>
                        <dd class="[overflow-wrap:anywhere]">
                            {{
                                [
                                    employer.company.representative.name,
                                    employer.company.representative.title,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')
                            }}
                            <br
                                v-if="
                                    employer.company.representative.phone ||
                                    employer.company.representative.email
                                "
                            />
                            {{
                                [
                                    employer.company.representative.phone,
                                    employer.company.representative.email,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')
                            }}
                        </dd>
                    </template>
                </dl>

                <div class="text-muted-foreground space-y-1 border-t pt-3">
                    <p class="[overflow-wrap:anywhere]">
                        {{
                            t('staff.employers.account', {
                                name: employer.name,
                                email: employer.email,
                            })
                        }}
                    </p>
                    <p>
                        {{
                            t('staff.employers.registeredOn', {
                                date: formatDate(employer.registeredAt),
                            })
                        }}
                    </p>
                </div>
            </CardContent>
            <CardFooter class="justify-end gap-2 border-t border-[#e3e6f0] pt-4">
                <Form
                    v-bind="EmployerReviewController.approve.form(employer.id)"
                >
                    <Button
                        type="submit"
                        class="!border-[#1cc88a] !bg-[#1cc88a] hover:!bg-[#17a673]"
                        :data-test="`approve-employer-${employer.id}`"
                        >{{ t('staff.employers.approve') }}</Button
                    >
                </Form>

                <Dialog>
                    <DialogTrigger as-child>
                        <Button
                            variant="destructive"
                            :data-test="`reject-employer-${employer.id}`"
                            >{{ t('staff.employers.reject') }}</Button
                        >
                    </DialogTrigger>
                    <DialogContent>
                        <Form
                            v-bind="
                                EmployerReviewController.reject.form(
                                    employer.id,
                                )
                            "
                            reset-on-success
                            v-slot="{ errors, processing }"
                            class="space-y-6"
                        >
                            <DialogHeader class="space-y-3">
                                <DialogTitle>{{
                                    t('staff.employers.rejectDialogTitle')
                                }}</DialogTitle>
                                <DialogDescription>
                                    {{
                                        t(
                                            'staff.employers.rejectDialogDescription',
                                        )
                                    }}
                                </DialogDescription>
                            </DialogHeader>

                            <div class="grid gap-2">
                                <Label :for="`reason-${employer.id}`">{{
                                    t('staff.employers.reasonLabel')
                                }}</Label>
                                <Input
                                    :id="`reason-${employer.id}`"
                                    name="reason"
                                    :placeholder="
                                        t('staff.employers.reasonPlaceholder')
                                    "
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
                                    :data-test="`confirm-reject-employer-${employer.id}`"
                                >
                                    {{ t('staff.employers.confirmReject') }}
                                </Button>
                            </DialogFooter>
                        </Form>
                    </DialogContent>
                </Dialog>
            </CardFooter>
        </Card>

        <div class="mt-4">
            <h2 class="text-lg font-semibold">
                {{ t('staff.employers.historyHeading') }}
            </h2>

            <p
                v-if="decisions.length === 0"
                class="text-muted-foreground mt-2 text-sm"
            >
                {{ t('staff.employers.historyEmpty') }}
            </p>

            <Card
                v-for="decision in decisions"
                :key="decision.id"
                class="mt-4 max-w-2xl"
            >
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>{{
                        decision.companyName ?? decision.employerName
                    }}</CardTitle>
                    <Badge
                        :variant="
                            decision.decision === 'approved'
                                ? 'default'
                                : 'destructive'
                        "
                    >
                        {{
                            decision.decision === 'approved'
                                ? t('staff.employers.approved')
                                : t('staff.employers.rejected')
                        }}
                    </Badge>
                </CardHeader>
                <CardContent class="text-muted-foreground space-y-1 text-sm">
                    <p class="[overflow-wrap:anywhere]">
                        {{
                            decision.companyName
                                ? t('staff.employers.account', {
                                      name: decision.employerName,
                                      email: decision.employerEmail,
                                  })
                                : decision.employerEmail
                        }}
                    </p>
                    <p>
                        {{
                            t('staff.employers.decidedBy', {
                                name: decision.reviewerName,
                                date: formatDate(decision.decidedAt),
                            })
                        }}
                    </p>
                    <p v-if="decision.reason">
                        {{ t('staff.employers.reasonLabel') }}:
                        {{ decision.reason }}
                    </p>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
