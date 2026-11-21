<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import SettingsShell from '../../Components/Settings/SettingsShell.vue';
import Icon from '../../Components/Icon.vue';
import { useI18n } from '../../lib/i18n';

const props = defineProps({ profile: Object, memberships: Array, subscriptionCount: Number });
const { t, name, locale } = useI18n();

const details = useForm({ name: props.profile.name, email: props.profile.email, locale: props.profile.locale, current_password: '' });
const emailChanged = computed(() => details.email.trim().toLowerCase() !== props.profile.email.toLowerCase());
const saveDetails = () => details.put('/settings/profile', { preserveScroll: true, onSuccess: () => details.reset('current_password') });

const pw = useForm({ current_password: '', password: '', password_confirmation: '' });
const checks = computed(() => [
    { ok: pw.password.length >= 10, label: t('At least 10 characters') },
    { ok: /[A-Za-z]/.test(pw.password), label: t('A letter') },
    { ok: /\d/.test(pw.password), label: t('A number') },
    { ok: pw.password !== '' && pw.password === pw.password_confirmation, label: t('Both entries match') },
]);
const savePassword = () => pw.put('/settings/password', { preserveScroll: true, onSuccess: () => pw.reset() });

const initials = computed(() => props.profile.name.split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase());
const lastLogin = computed(() => (props.profile.last_login_at
    ? new Intl.DateTimeFormat(locale.value === 'ar' ? 'ar-SA-u-ca-gregory-nu-latn' : 'en-GB', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(props.profile.last_login_at))
    : null));
</script>

<template>
    <SettingsShell :title="t('Profile')" :subtitle="t('Your name, sign-in email, language and password.')">
        <!-- Identity -->
        <section class="card rise relative overflow-hidden p-6">
            <div class="brand-pattern pointer-events-none absolute inset-y-0 end-0 w-1/3 opacity-[0.06]" />
            <div class="relative flex flex-wrap items-center gap-4">
                <span class="grid size-16 place-items-center rounded-2xl bg-peach font-display text-2xl font-semibold text-navy">{{ initials }}</span>
                <div class="min-w-0">
                    <div class="font-display text-xl font-semibold text-navy">{{ profile.name }}</div>
                    <div class="text-sm text-ink-2" dir="ltr">{{ profile.email }}</div>
                    <div v-if="lastLogin" class="mt-1 text-xs text-muted">{{ t('Last sign-in') }} <bdi>{{ lastLogin }}</bdi></div>
                </div>
            </div>
        </section>

        <!-- Details -->
        <form class="card rise p-6" @submit.prevent="saveDetails">
            <h3 class="card-title">{{ t('Personal details') }}</h3>
            <p class="card-sub mb-5">{{ t('Changing your email changes how you sign in.') }}</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="mb-1.5 block text-[13px] font-bold text-navy">{{ t('Full name') }}</span>
                    <input v-model="details.name" class="field" required maxlength="120" autocomplete="name" />
                    <span v-if="details.errors.name" class="mt-1 block text-xs font-semibold text-danger">{{ details.errors.name }}</span>
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-[13px] font-bold text-navy">{{ t('Email') }}</span>
                    <input v-model="details.email" type="email" class="field" required dir="ltr" autocomplete="email" />
                    <span v-if="details.errors.email" class="mt-1 block text-xs font-semibold text-danger">{{ details.errors.email }}</span>
                </label>
                <label v-if="emailChanged" class="block sm:col-span-2">
                    <span class="mb-1.5 block text-[13px] font-bold text-navy">{{ t('Current password, to confirm the new email') }}</span>
                    <input v-model="details.current_password" type="password" class="field sm:max-w-sm" dir="ltr" autocomplete="current-password" />
                    <span v-if="details.errors.current_password" class="mt-1 block text-xs font-semibold text-danger">{{ details.errors.current_password }}</span>
                </label>
                <div class="sm:col-span-2">
                    <span class="mb-1.5 block text-[13px] font-bold text-navy">{{ t('Language') }}</span>
                    <div class="seg w-fit" role="radiogroup">
                        <button type="button" role="radio" :aria-checked="details.locale === 'ar'" :aria-pressed="details.locale === 'ar'" @click="details.locale = 'ar'">العربية</button>
                        <button type="button" role="radio" :aria-checked="details.locale === 'en'" :aria-pressed="details.locale === 'en'" @click="details.locale = 'en'">English</button>
                    </div>
                    <p class="mt-1.5 text-xs text-muted">{{ t('Used for the screens and the emails FGRE sends you.') }}</p>
                </div>
            </div>
            <div class="mt-6 flex justify-end">
                <button type="submit" class="btn-coral !py-2.5 text-sm" :disabled="details.processing || !details.isDirty">{{ t('Save') }}</button>
            </div>
        </form>

        <!-- Password -->
        <form class="card rise p-6" @submit.prevent="savePassword">
            <div class="flex items-center gap-3">
                <span class="grid size-10 place-items-center rounded-xl bg-coral-tint text-coral"><Icon name="key" :size="18" /></span>
                <div>
                    <h3 class="card-title">{{ t('Password') }}</h3>
                    <p class="card-sub">{{ t('Other browsers signed in with the old password are signed out.') }}</p>
                </div>
            </div>
            <div class="mt-5 grid gap-4 sm:grid-cols-3">
                <label class="block">
                    <span class="mb-1.5 block text-[13px] font-bold text-navy">{{ t('Current password') }}</span>
                    <input v-model="pw.current_password" type="password" class="field" required dir="ltr" autocomplete="current-password" />
                    <span v-if="pw.errors.current_password" class="mt-1 block text-xs font-semibold text-danger">{{ pw.errors.current_password }}</span>
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-[13px] font-bold text-navy">{{ t('New password') }}</span>
                    <input v-model="pw.password" type="password" class="field" required dir="ltr" autocomplete="new-password" />
                    <span v-if="pw.errors.password" class="mt-1 block text-xs font-semibold text-danger">{{ pw.errors.password }}</span>
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-[13px] font-bold text-navy">{{ t('Confirm new password') }}</span>
                    <input v-model="pw.password_confirmation" type="password" class="field" required dir="ltr" autocomplete="new-password" />
                </label>
            </div>
            <ul class="mt-4 flex flex-wrap gap-2">
                <li v-for="c in checks" :key="c.label" class="chip !py-1" :class="c.ok ? 'bg-success-tint text-success' : 'bg-paper text-muted'">
                    <Icon :name="c.ok ? 'check' : 'close'" :size="12" />{{ c.label }}
                </li>
            </ul>
            <div class="mt-6 flex justify-end">
                <button type="submit" class="btn-coral !py-2.5 text-sm" :disabled="pw.processing || !checks.every((c) => c.ok) || !pw.current_password">{{ t('Change password') }}</button>
            </div>
        </form>

        <!-- Access -->
        <section class="card rise p-6">
            <h3 class="card-title">{{ t('Your access') }}</h3>
            <p class="card-sub mb-4">{{ t('Your role in each group. Ask a group owner to change it.') }}</p>
            <ul class="divide-y divide-line">
                <li v-for="m in memberships" :key="m.id" class="flex flex-wrap items-center gap-3 py-3">
                    <Icon name="layers" :size="17" class="text-coral" />
                    <span class="flex-1 font-bold text-navy">{{ name(m) }}</span>
                    <span class="chip bg-navy text-white">{{ t(`role_${m.role}`) }}</span>
                    <span class="chip bg-paper text-ink-2">{{ m.hotels === null ? t('All hotels') : t(':n hotels', { n: m.hotels }) }}</span>
                </li>
            </ul>
            <Link href="/subscriptions" class="mt-4 inline-flex items-center gap-2 text-sm font-bold text-coral hover:underline">
                <Icon name="mail" :size="15" />{{ t('Email subscriptions') }} ({{ subscriptionCount }})
            </Link>
        </section>
    </SettingsShell>
</template>
