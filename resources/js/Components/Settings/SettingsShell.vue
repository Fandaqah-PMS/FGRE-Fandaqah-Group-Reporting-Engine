<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import PageHeader from '../PageHeader.vue';
import Icon from '../Icon.vue';
import { useI18n } from '../../lib/i18n';

// Settings pages: one header, a section menu (only what the user may open), then the page.
defineProps({ title: String, subtitle: String });
const { t } = useI18n();
const page = usePage();
const can = computed(() => page.props.auth?.can ?? {});
const path = computed(() => page.url.split('?')[0]);

const sections = computed(() => [
    { label: t('Your account'), items: [
        { href: '/settings/profile', icon: 'user', label: t('Profile'), text: t('Name, language, password'), show: true },
        { href: '/subscriptions', icon: 'mail', label: t('Email subscriptions'), text: t('Reports by email'), show: can.value['view reports'] },
    ] },
    { label: t('Group administration'), items: [
        { href: '/settings/users', icon: 'users', label: t('Users'), text: t('Who has access, and to which hotels'), show: can.value['manage users'] },
        { href: '/settings/roles', icon: 'shield', label: t('Roles & permissions'), text: t('What each role may do'), show: can.value['manage users'] },
        { href: '/settings/hotels', icon: 'building', label: t('Hotels'), text: t('Rooms, PMS, report senders'), show: can.value['manage properties'] },
        { href: '/settings/group', icon: 'settings', label: t('Group'), text: t('Name, currency, timezone'), show: can.value['manage settings'] },
        { href: '/settings/activity', icon: 'history', label: t('Activity log'), text: t('Who changed what'), show: can.value['manage settings'] },
    ] },
].map((s) => ({ ...s, items: s.items.filter((i) => i.show) })).filter((s) => s.items.length));
</script>

<template>
    <Head :title="`${title} · ${t('Settings')}`" />
    <PageHeader :eyebrow="t('Settings')" :title="title" :subtitle="subtitle">
        <template v-if="$slots.actions" #actions><slot name="actions" /></template>
    </PageHeader>

    <div class="mt-6 grid gap-6 lg:grid-cols-[16rem_minmax(0,1fr)]">
        <nav class="card h-fit p-3 max-lg:flex max-lg:gap-1 max-lg:overflow-x-auto" :aria-label="t('Settings')">
            <div v-for="s in sections" :key="s.label" class="max-lg:contents lg:mb-3 lg:last:mb-0">
                <div class="px-3 pt-2 pb-1.5 text-[10px] font-bold tracking-[0.18em] text-muted uppercase max-lg:hidden rtl:tracking-normal">{{ s.label }}</div>
                <Link v-for="i in s.items" :key="i.href" :href="i.href"
                      class="group flex shrink-0 items-start gap-3 rounded-xl px-3 py-2.5 transition"
                      :class="path === i.href ? 'bg-paper' : 'hover:bg-paper/70'">
                    <span class="grid size-8 shrink-0 place-items-center rounded-lg transition" :class="path === i.href ? 'bg-coral text-white' : 'bg-paper text-ink-2 group-hover:text-coral'">
                        <Icon :name="i.icon" :size="16" />
                    </span>
                    <span class="min-w-0">
                        <span class="block text-[13.5px] font-bold whitespace-nowrap text-ink">{{ i.label }}</span>
                        <span class="block text-[11px] text-muted max-lg:hidden">{{ i.text }}</span>
                    </span>
                </Link>
            </div>
        </nav>
        <div class="min-w-0 space-y-6"><slot /></div>
    </div>
</template>
