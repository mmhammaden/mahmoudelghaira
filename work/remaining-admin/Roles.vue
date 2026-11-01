<script setup lang="ts">
import { watchEffect } from 'vue';
import { Head, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Plus } from '@lucide/vue';
import RoleController from '@/actions/App/Http/Controllers/Staff/RoleController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { index } from '@/routes/staff/roles';

type RoleRow = {
    id: number;
    name: string;
    permissions: string[];
    members: number;
    locked: boolean;
};

type Member = {
    id: number;
    name: string;
    email: string;
    roleId: number | null;
};

const props = defineProps<{
    roles: RoleRow[];
    members: Member[];
    permissions: string[];
}>();

const { t, te, locale } = useI18n();

watchEffect(() =>
    setLayoutProps({
        breadcrumbs: [{ title: t('staff.roles.breadcrumb'), href: index() }],
    }),
);

// Base roles have French labels; roles created by administrators show their name.
const roleLabel = (name: string): string =>
    te(`staff.roles.base.${name}`) ? t(`staff.roles.base.${name}`) : name;

// Sorted by the label people read, not the stored key ("Commercial" is `sales`).
const sortedRoles = computed(() =>
    [...props.roles].sort((a, b) =>
        roleLabel(a.name).localeCompare(roleLabel(b.name), locale.value),
    ),
);

// Per-role editable copy of its privileges, created on first use so roles
// added after the page loaded (e.g. just created) work too.
const edits = reactive<Record<number, string[]>>({});
const perms = (role: RoleRow): string[] =>
    (edits[role.id] ??= [...role.permissions]);
const errors = ref<Record<string, string>>({});

function toggle(list: string[], permission: string) {
    const i = list.indexOf(permission);
    if (i === -1) {
        list.push(permission);
    } else {
        list.splice(i, 1);
    }
}

const onError = (e: Record<string, string>) => (errors.value = e);
const options = {
    preserveScroll: true,
    onError,
    onSuccess: () => (errors.value = {}),
};

function saveRole(role: RoleRow) {
    router.put(
        RoleController.update.url(role.id),
        { permissions: perms(role) },
        options,
    );
}

function deleteRole(role: RoleRow) {
    router.delete(RoleController.destroy.url(role.id), options);
}

const memberRoles = reactive<Record<number, number | null>>(
    Object.fromEntries(props.members.map((m) => [m.id, m.roleId])),
);

function saveMember(member: Member) {
    router.put(
        RoleController.updateMember.url(member.id),
        { role_id: memberRoles[member.id] },
        options,
    );
}

const create = useForm<{ name: string; permissions: string[] }>({
    name: '',
    permissions: [],
});
const createOpen = ref(false);

function createRole() {
    create.post(RoleController.store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            create.reset();
            createOpen.value = false;
        },
    });
}
</script>

<template>
    <Head :title="t('staff.roles.headTitle')" />

    <div class="flex flex-1 flex-col gap-6 bg-white p-5 md:p-8">
        <div>
            <h1 class="text-3xl font-normal text-[#5a5c69]">
                {{ t('staff.roles.heading') }}
            </h1>
            <p class="text-muted-foreground text-sm">
                {{ t('staff.roles.description') }}
            </p>
        </div>

        <p
            v-if="errors.role"
            class="text-sm text-red-600"
            data-test="roles-error"
        >
            {{ errors.role }}
        </p>

        <Card class="legacy-admin-card">
            <CardHeader>
                <CardTitle>{{ t('staff.roles.rolesTitle') }}</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div
                    v-for="role in sortedRoles"
                    :key="role.id"
                    class="rounded border border-[#e3e6f0] p-4"
                    :data-test="`role-${role.name}`"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <span class="flex items-center gap-2 font-medium">
                            {{ roleLabel(role.name) }}
                            <Badge v-if="role.locked" variant="secondary">{{
                                t('staff.roles.locked')
                            }}</Badge>
                        </span>
                        <span class="text-muted-foreground text-sm">{{
                            t(
                                'staff.roles.memberCount',
                                { count: role.members },
                                role.members,
                            )
                        }}</span>
                    </div>
                    <fieldset
                        class="mt-3 grid gap-2 sm:grid-cols-2"
                        :disabled="role.locked"
                    >
                        <legend class="sr-only">
                            {{ t('staff.roles.privileges') }}
                        </legend>
                        <label
                            v-for="permission in permissions"
                            :key="permission"
                            class="flex items-center gap-2 text-sm"
                        >
                            <input
                                type="checkbox"
                                :checked="perms(role).includes(permission)"
                                :data-test="`perm-${role.name}-${permission}`"
                                @change="toggle(perms(role), permission)"
                            />
                            {{ t(`staff.roles.permissions.${permission}`) }}
                        </label>
                    </fieldset>
                    <div
                        v-if="!role.locked"
                        class="mt-3 flex justify-end gap-2"
                    >
                        <Dialog>
                            <DialogTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    :disabled="role.members > 0"
                                    :data-test="`delete-role-${role.name}`"
                                    >{{ t('candidate.cv.delete') }}</Button
                                >
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>{{
                                        t('staff.roles.deleteTitle', {
                                            name: roleLabel(role.name),
                                        })
                                    }}</DialogTitle>
                                    <DialogDescription>{{
                                        t('staff.roles.deleteDescription')
                                    }}</DialogDescription>
                                </DialogHeader>
                                <DialogFooter class="gap-2">
                                    <DialogClose as-child>
                                        <Button variant="secondary">{{
                                            t('common.cancel')
                                        }}</Button>
                                    </DialogClose>
                                    <DialogClose as-child>
                                        <Button
                                            variant="destructive"
                                            :data-test="`confirm-delete-role-${role.name}`"
                                            @click="deleteRole(role)"
                                            >{{
                                                t('candidate.cv.delete')
                                            }}</Button
                                        >
                                    </DialogClose>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                        <Button
                            size="sm"
                            :data-test="`save-role-${role.name}`"
                            @click="saveRole(role)"
                            >{{ t('candidate.cv.save') }}</Button
                        >
                    </div>
                </div>
            </CardContent>
        </Card>

        <div class="flex justify-end">
            <Button size="sm" class="shadow-sm" @click="createOpen = true">
                <Plus class="size-4" /> {{ t('staff.roles.createTitle') }}
            </Button>
        </div>
        <Dialog v-model:open="createOpen">
          <DialogContent>
            <DialogHeader><DialogTitle>{{ t('staff.roles.createTitle') }}</DialogTitle></DialogHeader>
                <form
                    class="space-y-3"
                    data-test="create-role"
                    @submit.prevent="createRole"
                >
                    <div class="grid gap-2">
                        <Label for="role-name">{{
                            t('staff.roles.name')
                        }}</Label>
                        <Input
                            id="role-name"
                            v-model="create.name"
                            maxlength="50"
                            :aria-invalid="!!create.errors.name"
                        />
                        <InputError :message="create.errors.name" />
                    </div>
                    <fieldset class="grid gap-2 sm:grid-cols-2">
                        <legend class="mb-1 text-sm font-medium">
                            {{ t('staff.roles.privileges') }}
                        </legend>
                        <label
                            v-for="permission in permissions"
                            :key="permission"
                            class="flex items-center gap-2 text-sm"
                        >
                            <input
                                type="checkbox"
                                :checked="
                                    create.permissions.includes(permission)
                                "
                                :data-test="`new-perm-${permission}`"
                                @change="toggle(create.permissions, permission)"
                            />
                            {{ t(`staff.roles.permissions.${permission}`) }}
                        </label>
                    </fieldset>
                    <div class="flex justify-end">
                        <Button
                            type="submit"
                            :disabled="create.processing"
                            data-test="submit-role"
                            >{{ t('staff.roles.create') }}</Button
                        >
                    </div>
                </form>
          </DialogContent>
        </Dialog>

        <Card class="legacy-admin-card">
            <CardHeader>
                <CardTitle>{{ t('staff.roles.membersTitle') }}</CardTitle>
            </CardHeader>
            <CardContent>
                <ul class="divide-y">
                    <li
                        v-for="member in members"
                        :key="member.id"
                        class="flex flex-wrap items-center justify-between gap-3 py-3"
                        :data-test="`member-${member.id}`"
                    >
                        <span class="min-w-0">
                            <span class="block font-medium">{{
                                member.name
                            }}</span>
                            <span
                                class="text-muted-foreground block text-sm [overflow-wrap:anywhere]"
                                >{{ member.email }}</span
                            >
                        </span>
                        <span class="flex items-center gap-2">
                            <select
                                v-model="memberRoles[member.id]"
                                class="border-input h-9 rounded-md border bg-transparent px-2 text-sm"
                                :aria-label="
                                    t('staff.roles.memberRole', {
                                        name: member.name,
                                    })
                                "
                                :data-test="`member-role-${member.id}`"
                            >
                                <option :value="null" disabled>
                                    {{ t('staff.roles.noRole') }}
                                </option>
                                <option
                                    v-for="role in sortedRoles"
                                    :key="role.id"
                                    :value="role.id"
                                >
                                    {{ roleLabel(role.name) }}
                                </option>
                            </select>
                            <Button
                                size="sm"
                                variant="outline"
                                :data-test="`save-member-${member.id}`"
                                @click="saveMember(member)"
                                >{{ t('candidate.cv.save') }}</Button
                            >
                        </span>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>
</template>
