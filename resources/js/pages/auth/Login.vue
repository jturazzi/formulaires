<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { LoaderCircle } from 'lucide-vue-next';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const page = usePage<SharedData>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthBase
        :title="trans('Log in to your account')"
        :description="
            page.props.features.sso ? trans('Sign in with your Microsoft 365 organization account') : trans('Sign in with your email and password')
        "
    >
        <Head :title="$t('Log in')" />

        <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <div class="flex flex-col gap-6">
            <Button v-if="page.props.features.sso" as-child variant="outline" class="w-full">
                <a :href="route('auth.microsoft')">
                    <svg class="mr-2 h-4 w-4" viewBox="0 0 23 23" aria-hidden="true">
                        <rect x="1" y="1" width="10" height="10" fill="#f25022" />
                        <rect x="12" y="1" width="10" height="10" fill="#7fba00" />
                        <rect x="1" y="12" width="10" height="10" fill="#00a4ef" />
                        <rect x="12" y="12" width="10" height="10" fill="#ffb900" />
                    </svg>
                    {{ $t('Sign in with Microsoft 365') }}
                </a>
            </Button>

            <form v-else @submit.prevent="submit" class="flex flex-col gap-6">
                <div class="grid gap-6">
                    <div class="grid gap-2">
                        <Label for="email">{{ $t('Email address') }}</Label>
                        <Input
                            id="email"
                            type="email"
                            required
                            autofocus
                            tabindex="1"
                            autocomplete="email"
                            v-model="form.email"
                            placeholder="email@example.com"
                        />
                        <InputError :message="form.errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <div class="flex items-center justify-between">
                            <Label for="password">{{ $t('Password') }}</Label>
                            <TextLink v-if="canResetPassword" :href="route('password.request')" class="text-sm" tabindex="5">
                                {{ $t('Forgot password?') }}
                            </TextLink>
                        </div>
                        <Input
                            id="password"
                            type="password"
                            required
                            tabindex="2"
                            autocomplete="current-password"
                            v-model="form.password"
                            :placeholder="$t('Password')"
                        />
                        <InputError :message="form.errors.password" />
                    </div>

                    <div class="flex items-center justify-between" tabindex="3">
                        <Label for="remember" class="flex items-center space-x-3">
                            <Checkbox id="remember" v-model:checked="form.remember" tabindex="4" />
                            <span>{{ $t('Remember me') }}</span>
                        </Label>
                    </div>

                    <Button type="submit" class="mt-4 w-full" tabindex="4" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                        {{ $t('Log in') }}
                    </Button>
                </div>

                <div v-if="page.props.features.registration" class="text-center text-sm text-muted-foreground">
                    {{ $t("Don't have an account?") }}
                    <TextLink :href="route('register')" :tabindex="5">{{ $t('Sign up') }}</TextLink>
                </div>
            </form>
        </div>
    </AuthBase>
</template>
