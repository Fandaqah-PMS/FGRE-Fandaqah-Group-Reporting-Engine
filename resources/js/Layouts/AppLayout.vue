<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useDocumentDirection, useI18n } from '../lib/i18n';

useDocumentDirection();
const page = usePage();
const { t, name, locale } = useI18n();

const auth = computed(() => page.props.auth);
const can = computed(() => auth.value?.can ?? {});

const nav = computed(() => [
    { href: '/', label: t('Dashboard'), show: can.value['view dashboard'], active: page.url === '/' || page.url.startsWith('/?') || page.url.startsWith('/hotels') },
    { href: '/financials', label: t('Financials'), show: can.value['view financials'], active: page.url.startsWith('/financials') },
    { href: '/documents', label: t('Reports received'), show: can.value['view reports'], active: page.url.startsWith('/documents'), badge: page.props.reviewCount },
].filter((i) => i.show));

const switchGroup = (event) => router.post(`/groups/${event.target.value}/switch`);
const toggleLocale = () => router.post('/locale', { locale: locale.value === 'ar' ? 'en' : 'ar' }, { preserveScroll: true });
const logout = () => router.post('/logout');
</script>

<template>
    <div class="min-h-screen bg-off-white">
        <header class="top-band text-white">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-6 gap-y-3 px-4 py-3 sm:px-6">
                <Link href="/" class="flex items-baseline gap-2 me-2">
                    <span class="font-display text-2xl font-bold tracking-wide">fandaqah</span>
                    <span class="hidden text-xs font-medium uppercase tracking-[0.18em] text-peach sm:inline">{{ t('Group Reporting') }}</span>
                </Link>

                <nav class="order-3 flex w-full gap-1 overflow-x-auto sm:order-none sm:w-auto">
                    <Link
                        v-for="item in nav"
                        :key="item.href"
                        :href="item.href"
                        class="relative flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium whitespace-nowrap transition"
                        :class="item.active ? 'bg-white/12 text-white' : 'text-white/70 hover:bg-white/6 hover:text-white'"
                    >
                        {{ item.label }}
                        <span v-if="item.badge" class="rounded-full bg-coral px-1.5 text-[11px] font-semibold leading-5 text-white" :title="t('Waiting for review')">{{ item.badge }}</span>
                        <span v-if="item.active" class="absolute inset-x-3 -bottom-[13px] hidden h-[3px] rounded-full bg-coral sm:block" />
                    </Link>
                </nav>

                <div class="ms-auto flex items-center gap-2">
                    <select
                        v-if="auth?.groups?.length > 1"
                        :value="auth.currentGroupId"
                        class="max-w-48 truncate rounded-lg border border-white/15 bg-white/8 px-2.5 py-1.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-coral/60"
                        :aria-label="t('Hotel group')"
                        @change="switchGroup"
                    >
                        <option v-for="g in auth.groups" :key="g.id" :value="g.id" class="text-navy">{{ name(g) }}</option>
                    </select>
                    <span v-else-if="auth?.groups?.length" class="hidden text-sm text-white/80 md:inline">{{ name(auth.groups[0]) }}</span>

                    <button type="button" class="rounded-lg border border-white/15 px-2.5 py-1.5 text-sm font-medium hover:bg-white/8" @click="toggleLocale">
                        {{ locale === 'ar' ? 'English' : 'العربية' }}
                    </button>

                    <div class="flex items-center gap-2 border-s border-white/15 ps-3">
                        <span class="hidden text-sm text-white/80 lg:inline">{{ auth?.user?.name }}</span>
                        <button type="button" class="rounded-lg px-2 py-1.5 text-sm text-white/70 hover:text-white" @click="logout">{{ t('Sign out') }}</button>
                    </div>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8">
            <div v-if="page.props.flash?.status" class="mb-5 rounded-xl border border-success/20 bg-success-soft px-4 py-3 text-sm text-success">
                {{ page.props.flash.status }}
            </div>
            <slot />
        </main>
    </div>
</template>
