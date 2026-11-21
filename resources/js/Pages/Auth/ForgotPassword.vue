<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import AuthCard from '../../Components/AuthCard.vue';
import Icon from '../../Components/Icon.vue';
import { useI18n } from '../../lib/i18n';

const { t } = useI18n();
const page = usePage();
const form = useForm({ email: '' });
const submit = () => form.post('/forgot-password', { preserveScroll: true });
</script>

<template>
    <AuthCard :title="t('Forgot your password?')" :text="t('Enter your email and we will send you a link to choose a new one.')" icon="key">
        <p v-if="page.props.flash?.status" class="mb-5 flex items-start gap-2 rounded-xl bg-success-tint px-3.5 py-3 text-sm font-semibold text-success">
            <Icon name="check" :size="16" class="mt-0.5" />{{ page.props.flash.status }}
        </p>
        <form class="space-y-5" @submit.prevent="submit">
            <label class="block">
                <span class="mb-2 block text-[13px] font-bold text-navy">{{ t('Email') }}</span>
                <input v-model="form.email" type="email" class="field !h-12" required autofocus dir="ltr" autocomplete="username" placeholder="name@company.com" />
                <span v-if="form.errors.email" class="mt-1 block text-xs font-semibold text-danger">{{ form.errors.email }}</span>
            </label>
            <button type="submit" class="btn-coral h-12 w-full" :disabled="form.processing">{{ t('Send reset link') }}</button>
        </form>
        <Link href="/login" class="mt-6 inline-flex items-center gap-1.5 text-sm font-bold text-coral hover:underline">
            <Icon name="chevron" :size="15" class="rotate-90 rtl:-rotate-90" />{{ t('Back to sign in') }}
        </Link>
    </AuthCard>
</template>
