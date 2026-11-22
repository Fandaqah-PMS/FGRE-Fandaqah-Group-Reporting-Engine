<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AuthCard from '../../Components/AuthCard.vue';
import Icon from '../../Components/Icon.vue';
import { useI18n } from '../../lib/i18n';

const props = defineProps({ token: String, email: String, invite: Boolean });
const { t } = useI18n();
const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '', invite: props.invite });
const checks = computed(() => [
    { ok: form.password.length >= 10, label: t('At least 10 characters') },
    { ok: /[A-Za-z]/.test(form.password), label: t('A letter') },
    { ok: /\d/.test(form.password), label: t('A number') },
    { ok: form.password !== '' && form.password === form.password_confirmation, label: t('Both entries match') },
]);
const submit = () => form.post('/reset-password', { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <AuthCard :title="invite ? t('Welcome to FGRE') : t('Choose a new password')"
              :text="invite ? t('Choose a password to finish setting up your account.') : t('Use at least 10 characters with letters and numbers.')" :icon="invite ? 'sparkles' : 'lock'">
        <form class="space-y-5" @submit.prevent="submit">
            <label class="block">
                <span class="mb-2 block text-[13px] font-bold text-ink">{{ t('Email') }}</span>
                <input v-model="form.email" type="email" class="field !h-12" required dir="ltr" autocomplete="username" />
                <span v-if="form.errors.email" class="mt-1 block text-xs font-semibold text-danger">{{ form.errors.email }}</span>
            </label>
            <label class="block">
                <span class="mb-2 block text-[13px] font-bold text-ink">{{ t('New password') }}</span>
                <input v-model="form.password" type="password" class="field !h-12" required autofocus dir="ltr" autocomplete="new-password" />
                <span v-if="form.errors.password" class="mt-1 block text-xs font-semibold text-danger">{{ form.errors.password }}</span>
            </label>
            <label class="block">
                <span class="mb-2 block text-[13px] font-bold text-ink">{{ t('Confirm new password') }}</span>
                <input v-model="form.password_confirmation" type="password" class="field !h-12" required dir="ltr" autocomplete="new-password" />
            </label>
            <ul class="flex flex-wrap gap-2">
                <li v-for="c in checks" :key="c.label" class="chip !py-1" :class="c.ok ? 'bg-success-tint text-success' : 'bg-paper text-muted'">
                    <Icon :name="c.ok ? 'check' : 'close'" :size="12" />{{ c.label }}
                </li>
            </ul>
            <button type="submit" class="btn-coral h-12 w-full" :disabled="form.processing || !checks.every((c) => c.ok)">
                {{ invite ? t('Set password and sign in') : t('Save password and sign in') }}
            </button>
        </form>
        <Link href="/login" class="mt-6 inline-flex items-center gap-1.5 text-sm font-bold text-coral hover:underline">
            <Icon name="chevron" :size="15" class="rotate-90 rtl:-rotate-90" />{{ t('Back to sign in') }}
        </Link>
    </AuthCard>
</template>
