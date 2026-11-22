<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import SettingsShell from '../../Components/Settings/SettingsShell.vue';
import Icon from '../../Components/Icon.vue';
import { useI18n } from '../../lib/i18n';
import { setTheme, themePref } from '../../lib/theme';

const props = defineProps({ profile: Object, memberships: Array, subscriptionCount: Number });
const { t, name, locale } = useI18n();

const THEMES = [
    { key: 'light', icon: 'sun', label: 'Light', text: 'Warm paper and white cards.' },
    { key: 'dark', icon: 'moon', label: 'Dark', text: 'Deep navy, easy on the eyes at night.' },
    { key: 'system', icon: 'monitor', label: 'System', text: 'Follows your device setting.' },
];

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
                <span class="grid size-16 place-items-center rounded-2xl bg-peach font-display text-2xl font-semibold text-on-peach">{{ initials }}</span>
                <div class="min-w-0">
                    <div class="font-display text-xl font-semibold text-ink">{{ profile.name }}</div>
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
                    <span class="mb-1.5 block text-[13px] font-bold text-ink">{{ t('Full name') }}</span>
                    <input v-model="details.name" class="field" required maxlength="120" autocomplete="name" />
                    <span v-if="details.errors.name" class="mt-1 block text-xs font-semibold text-danger">{{ details.errors.name }}</span>
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-[13px] font-bold text-ink">{{ t('Email') }}</span>
                    <input v-model="details.email" type="email" class="field" required dir="ltr" autocomplete="email" />
                    <span v-if="details.errors.email" class="mt-1 block text-xs font-semibold text-danger">{{ details.errors.email }}</span>
                </label>
                <label v-if="emailChanged" class="block sm:col-span-2">
                    <span class="mb-1.5 block text-[13px] font-bold text-ink">{{ t('Current password, to confirm the new email') }}</span>
                    <input v-model="details.current_password" type="password" class="field sm:max-w-sm" dir="ltr" autocomplete="current-password" />
                    <span v-if="details.errors.current_password" class="mt-1 block text-xs font-semibold text-danger">{{ details.errors.current_password }}</span>
                </label>
                <div class="sm:col-span-2">
                    <span class="mb-1.5 block text-[13px] font-bold text-ink">{{ t('Language') }}</span>
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

        <!-- Appearance -->
        <section class="card rise p-6">
            <h3 class="card-title">{{ t('Appearance') }}</h3>
            <p class="card-sub mb-5">{{ t('Saved on your account, so every device follows it.') }}</p>
            <div class="grid gap-3 sm:grid-cols-3" role="radiogroup" :aria-label="t('Theme')">
                <button v-for="o in THEMES" :key="o.key" type="button" role="radio" :aria-checked="themePref === o.key"
                        class="group relative overflow-hidden rounded-2xl border-2 p-3 text-start transition"
                        :class="themePref === o.key ? 'border-coral' : 'border-line hover:border-muted/60'" @click="setTheme(o.key, true)">
                    <!-- mini preview -->
                    <div class="flex h-24 overflow-hidden rounded-xl ring-1 ring-black/5" aria-hidden="true">
                        <template v-for="mode in (o.key === 'system' ? ['light', 'dark'] : [o.key])" :key="mode">
                            <div class="flex flex-1" :class="mode === 'dark' ? 'bg-[#0e1320]' : 'bg-[#f8f7f4]'">
                                <div class="w-1/4 border-e" :class="mode === 'dark' ? 'border-[#263046] bg-[#161c2b]' : 'border-[#ece9e3] bg-white'">
                                    <div class="m-1.5 h-2 rounded-full bg-[#f15f5a]" />
                                    <div class="mx-1.5 mt-1 h-1.5 rounded-full" :class="mode === 'dark' ? 'bg-[#263046]' : 'bg-[#ece9e3]'" />
                                    <div class="mx-1.5 mt-1 h-1.5 rounded-full" :class="mode === 'dark' ? 'bg-[#263046]' : 'bg-[#ece9e3]'" />
                                </div>
                                <div class="flex-1 space-y-1.5 p-2">
                                    <div class="h-6 rounded-md" :class="mode === 'dark' ? 'bg-[#33415f]' : 'bg-[#262e43]'" />
                                    <div class="flex gap-1.5">
                                        <div class="h-8 flex-1 rounded-md" :class="mode === 'dark' ? 'bg-[#161c2b]' : 'bg-white'" />
                                        <div class="h-8 flex-1 rounded-md" :class="mode === 'dark' ? 'bg-[#161c2b]' : 'bg-white'" />
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div class="mt-3 flex items-center gap-2 font-bold text-ink">
                        <Icon :name="o.icon" :size="16" :class="themePref === o.key ? 'text-coral' : 'text-muted'" />{{ t(o.label) }}
                    </div>
                    <div class="mt-0.5 text-xs text-ink-2">{{ t(o.text) }}</div>
                    <Icon v-if="themePref === o.key" name="check" :size="16" class="absolute end-4 top-4 rounded-full bg-coral p-0.5 text-white" />
                </button>
            </div>
        </section>

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
                    <span class="mb-1.5 block text-[13px] font-bold text-ink">{{ t('Current password') }}</span>
                    <input v-model="pw.current_password" type="password" class="field" required dir="ltr" autocomplete="current-password" />
                    <span v-if="pw.errors.current_password" class="mt-1 block text-xs font-semibold text-danger">{{ pw.errors.current_password }}</span>
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-[13px] font-bold text-ink">{{ t('New password') }}</span>
                    <input v-model="pw.password" type="password" class="field" required dir="ltr" autocomplete="new-password" />
                    <span v-if="pw.errors.password" class="mt-1 block text-xs font-semibold text-danger">{{ pw.errors.password }}</span>
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-[13px] font-bold text-ink">{{ t('Confirm new password') }}</span>
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
                    <span class="flex-1 font-bold text-ink">{{ name(m) }}</span>
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
