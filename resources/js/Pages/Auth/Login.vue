<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { useDocumentDirection, useI18n } from '../../lib/i18n';

useDocumentDirection();
const { t, locale } = useI18n();
const page = usePage();
const form = useForm({ email: '', password: '', remember: true });

const submit = () => form.post('/login', { onFinish: () => form.reset('password') });
const toggleLocale = () => router.post('/locale', { locale: locale.value === 'ar' ? 'en' : 'ar' });
</script>

<template>
    <Head :title="t('Sign in')" />
    <div class="grid min-h-screen lg:grid-cols-[1.1fr_1fr]">
        <!-- Brand panel -->
        <div class="top-band relative hidden overflow-hidden p-12 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="pattern absolute inset-0 opacity-[0.16]" aria-hidden="true" />
            <div class="relative">
                <div class="font-display text-4xl font-bold">fandaqah</div>
                <div class="mt-1 text-sm font-medium uppercase tracking-[0.2em] text-peach">{{ t('Group Reporting') }}</div>
            </div>
            <div class="relative max-w-md">
                <h1 class="font-display text-4xl leading-tight font-semibold">{{ t('Every hotel in your group, in one place.') }}</h1>
                <p class="mt-4 text-white/75">{{ t('Daily KPIs, last-year comparison and trial balances, whichever PMS each hotel uses.') }}</p>
            </div>
            <div class="relative text-xs text-white/50">© Fandaqah</div>
        </div>

        <!-- Form -->
        <div class="flex flex-col bg-off-white px-6 py-8 sm:px-12">
            <div class="flex justify-between">
                <div class="font-display text-2xl font-bold text-navy lg:invisible">fandaqah</div>
                <button type="button" class="text-sm font-medium text-secondary hover:text-navy" @click="toggleLocale">
                    {{ locale === 'ar' ? 'English' : 'العربية' }}
                </button>
            </div>

            <form class="m-auto w-full max-w-sm" @submit.prevent="submit">
                <h2 class="font-display text-3xl font-semibold text-navy">{{ t('Sign in') }}</h2>
                <p class="mt-2 text-sm text-secondary">{{ t('Use the account your group administrator created for you.') }}</p>

                <label class="mt-8 block text-sm font-medium text-navy" for="email">{{ t('Email') }}</label>
                <input id="email" v-model="form.email" type="email" autocomplete="username" required autofocus dir="ltr"
                       class="mt-1.5 w-full rounded-xl border border-line bg-white px-3.5 py-2.5 text-navy outline-none focus:border-coral focus:ring-2 focus:ring-coral/25" />

                <label class="mt-4 block text-sm font-medium text-navy" for="password">{{ t('Password') }}</label>
                <input id="password" v-model="form.password" type="password" autocomplete="current-password" required dir="ltr"
                       class="mt-1.5 w-full rounded-xl border border-line bg-white px-3.5 py-2.5 text-navy outline-none focus:border-coral focus:ring-2 focus:ring-coral/25" />

                <p v-if="form.errors.email || page.props.errors?.email" class="mt-3 text-sm text-danger">{{ form.errors.email || page.props.errors.email }}</p>

                <label class="mt-4 flex items-center gap-2 text-sm text-secondary">
                    <input v-model="form.remember" type="checkbox" class="rounded border-line text-coral focus:ring-coral" />
                    {{ t('Keep me signed in') }}
                </label>

                <button type="submit" :disabled="form.processing"
                        class="mt-6 w-full rounded-xl bg-coral px-4 py-3 font-semibold text-white shadow-sm transition hover:bg-coral-text disabled:opacity-60">
                    {{ t('Sign in') }}
                </button>
            </form>
        </div>
    </div>
</template>
