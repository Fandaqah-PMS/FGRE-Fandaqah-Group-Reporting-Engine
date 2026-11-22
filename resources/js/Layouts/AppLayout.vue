<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Icon from '../Components/Icon.vue';
import HotelSwitcher from '../Components/HotelSwitcher.vue';
import { useDocumentDirection, useI18n } from '../lib/i18n';

useDocumentDirection();
const page = usePage();
const { t, name, locale } = useI18n();
const drawer = ref(false);
watch(() => page.url, () => (drawer.value = false));

const auth = computed(() => page.props.auth);
const can = computed(() => auth.value?.can ?? {});
const path = computed(() => page.url.split('?')[0]);
const initials = computed(() => (auth.value?.user?.name ?? '?').split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase());
const currentGroup = computed(() => auth.value?.groups?.find((g) => g.id === auth.value.currentGroupId));

const sections = computed(() => [
    { label: t('Overview'), items: [
        { href: '/', icon: 'grid', label: t('Dashboard'), show: can.value['view dashboard'], active: path.value === '/' },
        { href: '/subscriptions', icon: 'mail', label: t('Email subscriptions'), show: can.value['view reports'] },
    ] },
    { label: t('Reports'), items: [
        { href: '/manager-flash', icon: 'flash', label: t('Manager Flash'), show: can.value['view reports'] },
        { href: '/trial-balance', icon: 'scale', label: t('Trial Balance'), show: can.value['view financials'] },
        { href: '/ledgers', icon: 'book', label: t('Front Office Ledgers'), show: can.value['view financials'] },
    ] },
    { label: t('Data'), items: [
        { href: '/budgets', icon: 'calendar', label: t('Budgets'), show: can.value['view reports'] },
        { href: '/documents', icon: 'inbox', label: t('Reports received'), show: can.value['view reports'], badge: page.props.reviewCount },
    ] },
    { label: t('Administration'), items: [
        { href: '/settings/users', icon: 'users', label: t('Users & roles'), show: can.value['manage users'], active: ['/settings/users', '/settings/roles'].includes(path.value) },
        { href: '/settings/profile', icon: 'settings', label: t('Settings'), show: true, active: path.value.startsWith('/settings') && !['/settings/users', '/settings/roles'].includes(path.value) },
    ] },
].map((s) => ({ ...s, items: s.items.filter((i) => i.show).map((i) => ({ ...i, active: i.active ?? path.value.startsWith(i.href) })) })).filter((s) => s.items.length));

const toggleLocale = () => router.post('/locale', { locale: locale.value === 'ar' ? 'en' : 'ar' }, { preserveScroll: true });
const switchGroup = (event) => router.post(`/groups/${event.target.value}/switch`);
const logout = () => router.post('/logout');
</script>

<template>
    <div class="min-h-screen bg-paper">
        <!-- Sidebar -->
        <aside class="print:hidden fixed inset-y-0 start-0 z-50 flex w-[17rem] flex-col overflow-hidden border-e border-line bg-white transition-transform duration-300"
               :class="drawer ? 'translate-x-0' : 'max-lg:ltr:-translate-x-full max-lg:rtl:translate-x-full'">
            <!-- brand art: the half of the sidebar away from the menu text, top to bottom -->
            <div class="art-end absolute inset-y-0 end-0 w-[42%] opacity-60 [background-size:200px_auto]" aria-hidden="true" />
            <div class="relative flex h-[4.5rem] items-center justify-between border-b border-line px-6">
                <Link href="/" aria-label="Fandaqah" class="relative"><img src="/brand/logo.png" alt="Fandaqah" class="h-[2.6rem] w-auto" /></Link>
                <button type="button" class="relative rounded-lg bg-white p-1.5 text-ink-2 shadow-sm lg:hidden" :aria-label="t('Close menu')" @click="drawer = false"><Icon name="close" /></button>
            </div>

            <nav class="relative flex-1 overflow-y-auto px-4 py-5">
                <div v-for="section in sections" :key="section.label" class="mb-6">
                    <div class="mb-2 px-3 text-[10px] font-bold tracking-[0.2em] text-muted uppercase">{{ section.label }}</div>
                    <Link v-for="item in section.items" :key="item.href" :href="item.href"
                          class="group mb-1 flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13.5px] font-semibold transition"
                          :class="item.active ? 'bg-coral text-white shadow-[var(--shadow-coral)]' : 'text-navy hover:bg-paper'">
                        <Icon :name="item.icon" :size="18" :class="item.active ? 'text-white' : 'text-ink-2 group-hover:text-coral'" />
                        <span class="flex-1">{{ item.label }}</span>
                        <span v-if="item.badge" class="rounded-full px-1.5 text-[11px] leading-5 font-bold"
                              :class="item.active ? 'bg-white text-coral' : 'bg-coral text-white'">{{ item.badge }}</span>
                        <span v-else-if="item.active" class="size-1.5 rounded-full bg-white" />
                    </Link>
                </div>
            </nav>

            <div class="relative p-4">
                <div class="rounded-2xl border border-line bg-white px-4 py-3.5 shadow-sm">
                    <div class="eyebrow">{{ t('Hotel group') }}</div>
                    <select v-if="auth?.groups?.length > 1" :value="auth.currentGroupId" class="mt-1.5 w-full truncate rounded-lg border border-line bg-white px-2 py-1.5 text-sm font-bold text-navy outline-none focus:ring-2 focus:ring-coral/30"
                            :aria-label="t('Hotel group')" @change="switchGroup">
                        <option v-for="g in auth.groups" :key="g.id" :value="g.id">{{ name(g) }}</option>
                    </select>
                    <div v-else class="mt-1.5 text-sm font-bold text-navy">{{ name(currentGroup) }}</div>
                    <div class="mt-1 text-xs text-muted">{{ t('Group Reporting Engine') }}</div>
                </div>
            </div>
        </aside>
        <div v-if="drawer" class="fixed inset-0 z-40 bg-navy/40 backdrop-blur-[2px] lg:hidden" @click="drawer = false" />

        <!-- Main -->
        <div class="lg:ps-[17rem] print:!ps-0">
            <header class="print:hidden sticky top-0 z-30 border-b border-line bg-white/90 backdrop-blur">
                <!-- brand art: the left half of the header, fading from left to right (both languages) -->
                <div class="art-left pointer-events-none absolute inset-y-0 left-0 w-1/2 opacity-60 [background-size:200px_auto]" aria-hidden="true" />
                <div class="relative flex h-[4.5rem] items-center gap-3 px-4 sm:px-8">
                    <button type="button" class="rounded-xl border border-line bg-white p-2 text-navy shadow-sm lg:hidden" :aria-label="t('Open menu')" @click="drawer = true"><Icon name="menu" /></button>
                    <HotelSwitcher class="min-w-0 flex-1 sm:flex-none" />
                    <div class="ms-auto flex items-center gap-2 sm:gap-3">
                        <button type="button" class="grid h-10 min-w-10 place-items-center rounded-xl border border-line bg-white px-3 text-sm font-bold text-navy shadow-sm hover:border-coral/40" @click="toggleLocale">
                            {{ locale === 'ar' ? 'EN' : 'العربية' }}
                        </button>
                        <Link href="/settings/profile" class="hidden items-center gap-3 rounded-xl border border-line bg-white p-1 shadow-sm sm:flex xl:pe-4 hover:border-coral/40" :title="t('Profile')">
                            <span class="grid size-8 place-items-center rounded-lg bg-peach text-xs font-bold text-navy">{{ initials }}</span>
                            <div class="hidden leading-tight xl:block">
                                <div class="text-sm font-bold text-navy">{{ auth?.user?.name }}</div>
                                <div class="text-xs text-muted">{{ t(`role_${auth?.role}`) }}</div>
                            </div>
                        </Link>
                        <button type="button" class="grid size-10 place-items-center rounded-xl border border-line bg-white text-ink-2 shadow-sm hover:border-coral/40 hover:text-coral" :title="t('Sign out')" :aria-label="t('Sign out')" @click="logout">
                            <Icon name="logout" />
                        </button>
                    </div>
                </div>
            </header>

            <main class="px-4 py-7 sm:px-8 sm:py-8">
                <div v-if="page.props.flash?.status" class="mb-5 flex items-center gap-2 rounded-xl border border-success/20 bg-success-tint px-4 py-3 text-sm font-semibold text-success">
                    <Icon name="check" :size="16" />{{ page.props.flash.status }}
                </div>
                <slot />
            </main>
        </div>
    </div>
</template>
