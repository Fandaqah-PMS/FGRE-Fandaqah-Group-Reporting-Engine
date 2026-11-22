<script setup>
import { ref } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import Icon from '../Icon.vue';
import { useI18n } from '../../lib/i18n';

// The sign-in form, shared by every login design.
const { t } = useI18n();
const page = usePage();
const form = useForm({ email: '', password: '', remember: true });
const showPassword = ref(false);
const submit = () => form.post('/login', { onFinish: () => form.reset('password') });
</script>

<template>
    <form class="space-y-5" @submit.prevent="submit">
        <div>
            <label class="mb-2 block text-[13px] font-bold text-ink" for="email">{{ t('Email') }}</label>
            <div class="relative">
                <Icon name="mail" :size="18" class="pointer-events-none absolute start-3.5 top-1/2 -translate-y-1/2 text-muted" />
                <input id="email" v-model="form.email" type="email" autocomplete="username" required autofocus dir="ltr"
                       class="field !h-[3.25rem] !ps-11 text-start" placeholder="name@company.com" />
            </div>
        </div>
        <div>
            <div class="mb-2 flex items-center justify-between">
                <label class="text-[13px] font-bold text-ink" for="password">{{ t('Password') }}</label>
                <Link href="/forgot-password" class="text-xs font-bold text-coral hover:underline">{{ t('Forgot password?') }}</Link>
            </div>
            <div class="relative">
                <Icon name="lock" :size="18" class="pointer-events-none absolute start-3.5 top-1/2 -translate-y-1/2 text-muted" />
                <input id="password" v-model="form.password" :type="showPassword ? 'text' : 'password'" autocomplete="current-password" required dir="ltr"
                       class="field !h-[3.25rem] !ps-11 !pe-16" />
                <button type="button" class="absolute end-3 top-1/2 -translate-y-1/2 rounded-md px-1.5 py-1 text-xs font-bold text-coral hover:bg-coral-tint" @click="showPassword = !showPassword">
                    {{ showPassword ? t('Hide') : t('Show') }}
                </button>
            </div>
        </div>

        <p v-if="form.errors.email || page.props.errors?.email" class="flex items-center gap-2 rounded-xl bg-coral-tint px-3.5 py-2.5 text-sm font-semibold text-coral-dark">
            <Icon name="alert" :size="16" />{{ form.errors.email || page.props.errors.email }}
        </p>

        <label class="flex items-center gap-2.5 text-sm font-medium text-ink-2">
            <input v-model="form.remember" type="checkbox" class="size-4 rounded border-line accent-coral" />
            {{ t('Keep me signed in') }}
        </label>

        <button type="submit" :disabled="form.processing" class="btn-coral h-[3.25rem] w-full text-[15px]">
            {{ form.processing ? t('Signing in…') : t('Sign in') }}
            <Icon name="login" :size="18" class="rtl:rotate-180" />
        </button>
    </form>
</template>
