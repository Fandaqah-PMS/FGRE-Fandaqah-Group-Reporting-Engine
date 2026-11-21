<script setup>
import { Head, router } from '@inertiajs/vue3';
import Icon from './Icon.vue';
import { useDocumentDirection, useI18n } from '../lib/i18n';

// Small centred card for the password pages (same brand as the sign-in page).
defineProps({ title: String, text: String, icon: { type: String, default: 'key' } });
useDocumentDirection();
const { t, locale } = useI18n();
const toggleLocale = () => router.post('/locale', { locale: locale.value === 'ar' ? 'en' : 'ar' });
</script>

<template>
    <Head :title="title" />
    <div class="relative flex min-h-screen flex-col overflow-hidden bg-paper px-6 py-8">
        <div class="art-corner absolute -end-10 -top-10 size-[26rem] opacity-60 [background-size:240px_auto]" aria-hidden="true" />
        <div class="relative flex items-center justify-between">
            <img src="/brand/logo.png" alt="Fandaqah" class="h-12 w-auto" />
            <button type="button" class="flex items-center gap-2 rounded-xl border border-line bg-white px-3 py-2 text-sm font-bold text-navy hover:border-coral/40" @click="toggleLocale">
                <Icon name="globe" :size="16" class="text-coral" />{{ locale === 'ar' ? 'English' : 'العربية' }}
            </button>
        </div>
        <div class="relative m-auto w-full max-w-md py-10">
            <div class="card p-8 shadow-xl">
                <span class="grid size-12 place-items-center rounded-2xl bg-coral text-white shadow-[var(--shadow-coral)]"><Icon :name="icon" :size="22" /></span>
                <h1 class="mt-5 font-display text-[1.9rem] leading-tight font-semibold text-navy">{{ title }}</h1>
                <p class="mt-2 text-sm text-ink-2">{{ text }}</p>
                <div class="mt-7"><slot /></div>
            </div>
        </div>
        <div class="relative text-xs text-muted">© {{ new Date().getFullYear() }} Fandaqah</div>
    </div>
</template>
