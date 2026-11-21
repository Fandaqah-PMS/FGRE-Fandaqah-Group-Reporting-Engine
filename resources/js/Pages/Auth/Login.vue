<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import Icon from '../../Components/Icon.vue';
import { useDocumentDirection, useI18n } from '../../lib/i18n';

useDocumentDirection();
const { t, locale } = useI18n();
const page = usePage();
const form = useForm({ email: '', password: '', remember: true });
const showPassword = ref(false);

const submit = () => form.post('/login', { onFinish: () => form.reset('password') });
const toggleLocale = () => router.post('/locale', { locale: locale.value === 'ar' ? 'en' : 'ar' });

const features = [
    { icon: 'layers', title: 'All hotels, one view', text: 'Consolidated group figures, or any single hotel in one click.' },
    { icon: 'flash', title: 'Manager Flash and trial balances', text: 'Day, month and year to date, against last year.' },
    { icon: 'sparkles', title: 'Any PMS', text: 'Fandaqah, Opera, Nazeel and more, read and checked automatically.' },
];
</script>

<template>
    <Head :title="t('Sign in')" />

    <div class="grid min-h-screen bg-paper lg:grid-cols-[minmax(0,1fr)_minmax(0,1.08fr)]">
        <!-- Form -->
        <section class="flex flex-col px-6 py-8 sm:px-12 lg:px-16">
            <div class="flex items-center justify-between">
                <img src="/brand/logo.png" alt="Fandaqah" class="h-14 w-auto" />
                <button type="button" class="flex items-center gap-2 rounded-xl border border-line bg-white px-3 py-2 text-sm font-bold text-navy hover:border-coral/40" @click="toggleLocale">
                    <Icon name="globe" :size="16" class="text-coral" />{{ locale === 'ar' ? 'English' : 'العربية' }}
                </button>
            </div>

            <div class="m-auto w-full max-w-[26rem] py-12">
                <div class="eyebrow">{{ t('Group Reporting') }}</div>
                <h1 class="mt-2 font-display text-[2.6rem] leading-[1.1] font-semibold text-navy">{{ t('Welcome back') }}</h1>
                <p class="mt-3 text-[15px] text-ink-2">{{ t('Sign in to see every hotel in your group, in one place.') }}</p>

                <form class="mt-9 space-y-5" @submit.prevent="submit">
                    <div>
                        <label class="mb-2 block text-[13px] font-bold text-navy" for="email">{{ t('Email') }}</label>
                        <div class="relative">
                            <Icon name="mail" :size="18" class="pointer-events-none absolute start-3.5 top-1/2 -translate-y-1/2 text-muted" />
                            <input id="email" v-model="form.email" type="email" autocomplete="username" required autofocus dir="ltr"
                                   class="field !h-[3.25rem] !ps-11 text-start" :placeholder="'name@company.com'" />
                        </div>
                    </div>
                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <label class="text-[13px] font-bold text-navy" for="password">{{ t('Password') }}</label>
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

                <p class="mt-8 text-center text-xs text-muted">{{ t('Accounts are created by your group administrator.') }}</p>
            </div>

            <div class="text-xs text-muted">© {{ new Date().getFullYear() }} Fandaqah</div>
        </section>

        <!-- Brand panel -->
        <section class="relative hidden p-4 lg:block">
            <div class="relative flex h-full flex-col overflow-hidden rounded-[2rem] bg-navy text-white">
                <div class="brand-pattern absolute inset-y-0 end-0 w-[30%] [background-size:460px_auto]" aria-hidden="true" />

                <div class="relative flex flex-1 flex-col justify-between p-12 xl:p-14">
                    <img src="/brand/logo-inverse.png" alt="" class="h-12 w-auto self-start" />

                    <div class="max-w-[25rem] xl:max-w-[27rem]">
                        <div class="eyebrow">{{ t('Fandaqah Group Reporting') }}</div>
                        <h2 class="mt-3 font-display text-[2.6rem] leading-[1.1] xl:text-[2.9rem] font-semibold">{{ t('Every hotel in your group. One clear view.') }}</h2>
                        <p class="mt-4 text-[15px] leading-relaxed text-white/75">{{ t('Daily KPIs, last-year comparison and trial balances, whichever PMS each hotel uses.') }}</p>

                        <ul class="mt-9 space-y-4">
                            <li v-for="f in features" :key="f.title" class="flex items-start gap-4 rounded-2xl bg-white/[0.07] p-4 ring-1 ring-white/10">
                                <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-coral text-white shadow-[var(--shadow-coral)]"><Icon :name="f.icon" :size="20" /></span>
                                <span>
                                    <span class="block text-[15px] font-bold">{{ t(f.title) }}</span>
                                    <span class="mt-0.5 block text-sm text-white/70">{{ t(f.text) }}</span>
                                </span>
                            </li>
                        </ul>
                    </div>

                    <div class="flex items-center gap-3 text-xs font-semibold text-white/60">
                        <span class="h-px w-10 bg-coral" />{{ t('Arabic and English · Any mailbox · Any PMS') }}
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
