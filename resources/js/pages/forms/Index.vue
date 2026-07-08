<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type FormStatus } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { Copy, ExternalLink, Inbox, LinkIcon, MoreHorizontal, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

interface FormRow {
    id: number;
    title: string;
    status: FormStatus;
    slug: string;
    responses_count: number;
    expires_at: string | null;
    updated_at: string;
    is_owner: boolean;
    is_shared_with_me: boolean;
    owner_name: string;
    can_delete: boolean;
}

defineProps<{
    forms: FormRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: trans('Forms'),
        href: '/forms',
    },
];

const createOpen = ref(false);

const createForm = useForm({
    title: '',
});

const submitCreate = () => {
    createForm.post(route('forms.store'), {
        onSuccess: () => {
            createOpen.value = false;
            createForm.reset();
        },
    });
};

const deleteCandidate = ref<FormRow | null>(null);

const confirmDelete = () => {
    if (!deleteCandidate.value) {
        return;
    }

    router.delete(route('forms.destroy', deleteCandidate.value.id), {
        onSuccess: () => (deleteCandidate.value = null),
    });
};

const duplicate = (form: FormRow) => {
    router.post(route('forms.duplicate', form.id));
};

const copyLink = async (form: FormRow) => {
    await navigator.clipboard.writeText(`${window.location.origin}/f/${form.slug}`);
};

const statusClass: Record<FormStatus, string> = {
    draft: 'bg-muted text-muted-foreground',
    published: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
    closed: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
};

const statusLabel: Record<FormStatus, string> = {
    draft: 'Draft',
    published: 'Published',
    closed: 'Closed',
};

const formatDate = (value: string) => new Date(value).toLocaleDateString();
</script>

<template>
    <Head :title="$t('Forms')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">{{ $t('My forms') }}</h1>

                <Dialog v-model:open="createOpen">
                    <DialogTrigger as-child>
                        <Button>
                            <Plus class="mr-1 h-4 w-4" />
                            {{ $t('New form') }}
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <form @submit.prevent="submitCreate">
                            <DialogHeader>
                                <DialogTitle>{{ $t('New form') }}</DialogTitle>
                                <DialogDescription>{{ $t('Give your form a title. You can change it later.') }}</DialogDescription>
                            </DialogHeader>
                            <div class="grid gap-2 py-4">
                                <Label for="new-form-title">{{ $t('Title') }}</Label>
                                <Input
                                    id="new-form-title"
                                    v-model="createForm.title"
                                    required
                                    autofocus
                                    :placeholder="$t('e.g. Registration 2026')"
                                />
                                <p v-if="createForm.errors.title" class="text-sm text-red-600">{{ createForm.errors.title }}</p>
                            </div>
                            <DialogFooter>
                                <Button type="submit" :disabled="createForm.processing">{{ $t('Create') }}</Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>

            <div v-if="forms.length === 0" class="rounded-xl border border-dashed p-16 text-center text-muted-foreground">
                {{ $t('No forms yet. Create your first form!') }}
            </div>

            <div v-else class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">{{ $t('Title') }}</th>
                            <th class="px-4 py-3 font-medium">{{ $t('Status') }}</th>
                            <th class="px-4 py-3 font-medium">{{ $t('Responses') }}</th>
                            <th class="px-4 py-3 font-medium">{{ $t('Last update') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="form in forms" :key="form.id" class="border-b last:border-0 hover:bg-muted/30">
                            <td class="px-4 py-3">
                                <Link :href="route('forms.edit', form.id)" class="font-medium hover:underline">{{ form.title }}</Link>
                                <span v-if="form.is_shared_with_me" class="ml-2 rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                                    {{ $t('Shared by :name', { name: form.owner_name }) }}
                                </span>
                                <span v-else-if="!form.is_owner" class="ml-2 rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                                    {{ $t('Owned by :name', { name: form.owner_name }) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statusClass[form.status]">
                                    {{ $t(statusLabel[form.status]) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <Link :href="route('forms.responses.index', form.id)" class="inline-flex items-center gap-1 hover:underline">
                                    <Inbox class="h-4 w-4 text-muted-foreground" />
                                    {{ form.responses_count }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ formatDate(form.updated_at) }}</td>
                            <td class="px-4 py-3 text-right">
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" size="icon" :aria-label="$t('Actions')">
                                            <MoreHorizontal class="h-4 w-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem as-child>
                                            <Link :href="route('forms.edit', form.id)" class="flex w-full items-center">
                                                <Pencil class="mr-2 h-4 w-4" />
                                                {{ $t('Edit') }}
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem as-child>
                                            <Link :href="route('forms.responses.index', form.id)" class="flex w-full items-center">
                                                <Inbox class="mr-2 h-4 w-4" />
                                                {{ $t('Responses') }}
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem v-if="form.status === 'published'" @click="copyLink(form)">
                                            <LinkIcon class="mr-2 h-4 w-4" />
                                            {{ $t('Copy public link') }}
                                        </DropdownMenuItem>
                                        <DropdownMenuItem v-if="form.status === 'published'" as-child>
                                            <a :href="`/f/${form.slug}`" target="_blank" class="flex w-full items-center">
                                                <ExternalLink class="mr-2 h-4 w-4" />
                                                {{ $t('Open public page') }}
                                            </a>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem @click="duplicate(form)">
                                            <Copy class="mr-2 h-4 w-4" />
                                            {{ $t('Duplicate') }}
                                        </DropdownMenuItem>
                                        <template v-if="form.can_delete">
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem class="text-red-600" @click="deleteCandidate = form">
                                                <Trash2 class="mr-2 h-4 w-4" />
                                                {{ $t('Delete') }}
                                            </DropdownMenuItem>
                                        </template>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Dialog :open="deleteCandidate !== null" @update:open="(value: boolean) => !value && (deleteCandidate = null)">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{{ $t('Delete this form?') }}</DialogTitle>
                        <DialogDescription>
                            {{ $t('All responses and uploaded files of this form will be permanently deleted. This cannot be undone.') }}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" @click="deleteCandidate = null">{{ $t('Cancel') }}</Button>
                        <Button variant="destructive" @click="confirmDelete">{{ $t('Delete') }}</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
