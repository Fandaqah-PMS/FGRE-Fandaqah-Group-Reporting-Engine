<script setup>
import { computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import Icon from '../../Components/Icon.vue';
import LoginForm from '../../Components/Auth/LoginForm.vue';
import { useDocumentDirection, useI18n } from '../../lib/i18n';

/**
 * Sign-in page in three designs (all on the Fandaqah identity; brand art kept away from text):
 *   split   form on paper + navy brand panel with the art in its corner (default)
 *   canvas  full-bleed brand art, sign-in card floating on it
 *   stage   navy stage, art in two corners, logo and card centred
 * Preview with /login?design=canvas; the chosen one becomes the default.
 */
useDocumentDirection();
const { t, locale } = useI18n();
const page = usePage();
const design = computed(() => {
    const d = new URLSearchParams(page.url.split('?')[1] ?? '').get('design');
    return ['split', 'canvas', 'stage'].includes(d) ? d : 'split';
});
const toggleLocale = () => router.post('/locale', { locale: locale.value === 'ar' ? 'en' : 'ar' }, { preserveState: true });

const features = [
    { icon: 'layers', title: 'All hotels, one view', text: 'Consolidated group figures, or any single hotel in one click.' },
    { icon: 'flash', title: 'Manager Flash and trial balances', text: 'Day, month and year to date, against last year.' },
    { icon: 'sparkles', title: 'Any PMS', text: 'Fandaqah, Opera, Nazeel and more, read and checked automatically.' },
];
</script>

<template>
    <Head :title="t('Sign in')" />

    <!-- ───────── Design 1: split ───────── -->
    <div v-if="design === 'split'" class="grid min-h-screen bg-paper lg:grid-cols-[minmax(0,1fr)_minmax(0,1.08fr)]">
        <section class="relative flex flex-col overflow-hidden px-6 py-8 sm:px-12 lg:px-16">
            <!-- art cluster in the far corner of the form side, faded long before the form -->
            <div class="brand-pattern-full absolute inset-x-0 top-0 h-[7.5rem] opacity-45 [background-size:200px_auto]" aria-hidden="true" />
            <div class="relative flex items-center justify-between">
                <span class="rounded-2xl border border-line bg-white px-4 py-2 shadow-sm"><img src="/brand/logo.png" alt="Fandaqah" class="h-11 w-auto" /></span>
                <button type="button" class="flex items-center gap-2 rounded-xl border border-line bg-white px-3 py-2 text-sm font-bold text-navy hover:border-coral/40" @click="toggleLocale">
                    <Icon name="globe" :size="16" class="text-coral" />{{ locale === 'ar' ? 'English' : 'العربية' }}
                </button>
            </div>

            <div class="relative m-auto w-full max-w-[26rem] py-12">
                <div class="eyebrow">{{ t('Group Reporting') }}</div>
                <h1 class="mt-2 font-display text-[2.6rem] leading-[1.1] font-semibold text-navy">{{ t('Welcome back') }}</h1>
                <p class="mt-3 text-[15px] text-ink-2">{{ t('Sign in to see every hotel in your group, in one place.') }}</p>
                <LoginForm class="mt-9" />
                <p class="mt-8 text-center text-xs text-muted">{{ t('Accounts are created by your group administrator.') }}</p>
            </div>
            <div class="relative text-xs text-muted">© {{ new Date().getFullYear() }} Fandaqah</div>
        </section>

        <section class="relative hidden p-4 lg:block">
            <div class="relative flex h-full flex-col overflow-hidden rounded-[2rem] bg-navy text-white">
                <!-- the art, large and rotated like the identity hero; a navy scrim keeps the copy readable -->
                <div class="art-end absolute inset-y-0 end-0 w-1/2 opacity-80 [background-size:260px_auto]" aria-hidden="true" />

                <div class="relative flex flex-1 flex-col justify-between p-12 xl:p-14">
                    <img src="/brand/logo-inverse.png" alt="" class="h-12 w-auto self-start" />
                    <div class="max-w-[27rem]">
                        <div class="eyebrow">{{ t('Fandaqah Group Reporting') }}</div>
                        <h2 class="mt-3 font-display text-[2.6rem] leading-[1.1] font-semibold xl:text-[2.9rem]">{{ t('Every hotel in your group. One clear view.') }}</h2>
                        <p class="mt-4 text-[15px] leading-relaxed text-white/75">{{ t('Daily KPIs, last-year comparison and trial balances, whichever PMS each hotel uses.') }}</p>
                        <ul class="mt-9 space-y-3">
                            <li v-for="f in features" :key="f.title" class="flex items-start gap-4 rounded-2xl bg-white/[0.07] p-4 ring-1 ring-white/10 backdrop-blur-sm">
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

    <!-- ───────── Design 2: canvas ───────── -->
    <div v-else-if="design === 'canvas'" class="relative flex min-h-screen flex-col overflow-hidden bg-paper">
        <div class="art-end absolute inset-y-0 end-0 w-1/2 [background-size:300px_auto]" aria-hidden="true" />
        <!-- paper wash: strongest in the middle where the card sits -->

        <header class="relative flex items-center justify-between px-6 py-6 sm:px-10">
            <span class="rounded-2xl bg-white px-4 py-2.5 shadow-sm"><img src="/brand/logo.png" alt="Fandaqah" class="h-10 w-auto" /></span>
            <button type="button" class="flex items-center gap-2 rounded-xl bg-white px-3 py-2 text-sm font-bold text-navy shadow-sm hover:text-coral" @click="toggleLocale">
                <Icon name="globe" :size="16" class="text-coral" />{{ locale === 'ar' ? 'English' : 'العربية' }}
            </button>
        </header>

        <main class="relative m-auto w-full max-w-[28rem] px-5 py-10">
            <div class="overflow-hidden rounded-[1.75rem] bg-white shadow-[0_30px_80px_-20px_rgba(38,46,67,0.35)]">
                <div class="relative overflow-hidden bg-navy px-8 py-7 text-white">
                    <div class="art-end absolute inset-y-0 end-0 w-2/3 opacity-60 [background-size:200px_auto]" aria-hidden="true" />
                    <div class="absolute inset-0 bg-gradient-to-r from-navy via-navy/85 to-navy/0 rtl:bg-gradient-to-l" aria-hidden="true" />
                    <div class="relative">
                        <div class="eyebrow">{{ t('Group Reporting') }}</div>
                        <h1 class="mt-2 font-display text-[2rem] leading-tight font-semibold">{{ t('Welcome back') }}</h1>
                        <p class="mt-1.5 text-sm text-white/70">{{ t('Every hotel in your group. One clear view.') }}</p>
                    </div>
                </div>
                <div class="px-8 py-8">
                    <LoginForm />
                    <p class="mt-6 text-center text-xs text-muted">{{ t('Accounts are created by your group administrator.') }}</p>
                </div>
            </div>
        </main>
        <footer class="relative px-6 pb-6 text-center text-xs font-semibold text-navy/70 sm:px-10">
            <span class="rounded-full bg-white/80 px-3 py-1">{{ t('Arabic and English · Any mailbox · Any PMS') }}</span>
        </footer>
    </div>

    <!-- ───────── Design 3: stage ───────── -->
    <div v-else class="relative flex min-h-screen flex-col overflow-hidden bg-navy text-white">
        <div class="art-end absolute inset-y-0 end-0 w-1/2 opacity-75 max-lg:w-full max-lg:opacity-25 [background-size:280px_auto]" aria-hidden="true" />

        <header class="relative flex items-center justify-between px-6 py-6 sm:px-10">
            <img src="/brand/logo-inverse.png" alt="Fandaqah" class="h-11 w-auto" />
            <button type="button" class="flex items-center gap-2 rounded-xl bg-navy px-3 py-2 text-sm font-bold text-white shadow-lg ring-1 ring-white/20 hover:bg-navy-light" @click="toggleLocale">
                <Icon name="globe" :size="16" class="text-coral" />{{ locale === 'ar' ? 'English' : 'العربية' }}
            </button>
        </header>

        <main class="relative m-auto grid w-full max-w-5xl items-center gap-10 px-6 py-10 lg:grid-cols-[1fr_27rem]">
            <div class="max-lg:text-center">
                <div class="eyebrow">{{ t('Fandaqah Group Reporting') }}</div>
                <h2 class="mt-3 font-display text-[2.4rem] leading-[1.1] font-semibold sm:text-[2.9rem]">{{ t('Every hotel in your group. One clear view.') }}</h2>
                <p class="mt-4 max-w-md text-[15px] leading-relaxed text-white/75 max-lg:mx-auto">{{ t('Daily KPIs, last-year comparison and trial balances, whichever PMS each hotel uses.') }}</p>
                <div class="mt-7 flex flex-wrap gap-2 max-lg:justify-center">
                    <span v-for="f in features" :key="f.title" class="chip bg-white/10 !px-3.5 !py-2 text-white ring-1 ring-white/15 backdrop-blur">
                        <Icon :name="f.icon" :size="15" class="text-coral" />{{ t(f.title) }}
                    </span>
                </div>
            </div>
            <div class="rounded-[1.75rem] bg-white p-8 text-navy shadow-2xl">
                <h1 class="font-display text-[1.9rem] leading-tight font-semibold">{{ t('Welcome back') }}</h1>
                <p class="mt-1.5 mb-7 text-sm text-ink-2">{{ t('Sign in to see every hotel in your group, in one place.') }}</p>
                <LoginForm />
                <p class="mt-6 text-center text-xs text-muted">{{ t('Accounts are created by your group administrator.') }}</p>
            </div>
        </main>
        <footer class="relative px-6 pb-6 text-center text-xs sm:px-10"><span class="rounded-full bg-navy px-3 py-1 text-white/60">© {{ new Date().getFullYear() }} Fandaqah</span></footer>
    </div>
</template>
