<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { KeyRound, Trash2 } from '@lucide/vue';
import { ref } from 'vue';

interface UserRow {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'creator';
    forms_count: number;
    sso: boolean;
    created_at: string;
}

defineProps<{
    users: UserRow[];
}>();

const page = usePage<SharedData>();

const breadcrumbs: BreadcrumbItem[] = [{ title: trans('Users'), href: '/admin/users' }];

const roleCandidate = ref<{ user: UserRow; role: string; select: HTMLSelectElement } | null>(null);

const changeRole = (user: UserRow, event: Event) => {
    const select = event.target as HTMLSelectElement;
    const role = select.value;

    if (role === user.role) {
        return;
    }

    if (role === 'admin') {
        roleCandidate.value = { user, role, select };
        return;
    }

    router.patch(route('admin.users.update', user.id), { role }, { preserveScroll: true });
};

const confirmRoleChange = () => {
    if (!roleCandidate.value) {
        return;
    }

    router.patch(
        route('admin.users.update', roleCandidate.value.user.id),
        { role: roleCandidate.value.role },
        { preserveScroll: true, onFinish: () => (roleCandidate.value = null) },
    );
};

const cancelRoleChange = () => {
    if (roleCandidate.value) {
        roleCandidate.value.select.value = roleCandidate.value.user.role;
    }

    roleCandidate.value = null;
};

const deleteCandidate = ref<UserRow | null>(null);

const confirmDelete = () => {
    if (!deleteCandidate.value) {
        return;
    }

    router.delete(route('admin.users.destroy', deleteCandidate.value.id), {
        preserveScroll: true,
        onSuccess: () => (deleteCandidate.value = null),
    });
};
</script>

<template>
    <Head :title="$t('Users')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <h1 class="text-xl font-semibold">{{ $t('Users') }}</h1>

            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">{{ $t('Name') }}</th>
                            <th class="px-4 py-3 font-medium">{{ $t('Email') }}</th>
                            <th class="px-4 py-3 font-medium">{{ $t('Sign-in') }}</th>
                            <th class="px-4 py-3 font-medium">{{ $t('Forms') }}</th>
                            <th class="px-4 py-3 font-medium">{{ $t('Role') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in users" :key="user.id" class="border-b last:border-0 hover:bg-muted/30">
                            <td class="px-4 py-3 font-medium">{{ user.name }}</td>
                            <td class="px-4 py-3">{{ user.email }}</td>
                            <td class="px-4 py-3">
                                <span v-if="user.sso" class="inline-flex items-center gap-1 text-muted-foreground">
                                    <KeyRound class="h-3.5 w-3.5" />
                                    Microsoft 365
                                </span>
                                <span v-else class="text-muted-foreground">{{ $t('Password') }}</span>
                            </td>
                            <td class="px-4 py-3">{{ user.forms_count }}</td>
                            <td class="px-4 py-3">
                                <select
                                    :value="user.role"
                                    :disabled="user.id === page.props.auth.user.id"
                                    class="h-9 rounded-md border border-input bg-background px-2 text-sm disabled:cursor-not-allowed disabled:opacity-50"
                                    @change="changeRole(user, $event)"
                                >
                                    <option value="creator">{{ $t('Creator') }}</option>
                                    <option value="admin">{{ $t('Administrator') }}</option>
                                </select>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Button
                                    v-if="user.id !== page.props.auth.user.id"
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8 text-muted-foreground hover:text-red-600"
                                    :aria-label="$t('Delete')"
                                    @click="deleteCandidate = user"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Dialog :open="deleteCandidate !== null" @update:open="(value: boolean) => !value && (deleteCandidate = null)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ $t('Delete this user?') }}</DialogTitle>
                    <DialogDescription>
                        {{ $t('Their forms, responses and files will be permanently deleted. This cannot be undone.') }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="deleteCandidate = null">{{ $t('Cancel') }}</Button>
                    <Button variant="destructive" @click="confirmDelete">{{ $t('Delete') }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="roleCandidate !== null" @update:open="(value: boolean) => !value && cancelRoleChange()">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ $t('Make this user an administrator?') }}</DialogTitle>
                    <DialogDescription>
                        {{ $t('Administrators can manage every user, form and site setting. Only grant this role to people you trust.') }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="cancelRoleChange">{{ $t('Cancel') }}</Button>
                    <Button @click="confirmRoleChange">{{ $t('Make administrator') }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
