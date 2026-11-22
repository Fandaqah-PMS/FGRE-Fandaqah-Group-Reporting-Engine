<script setup>
import { computed, reactive } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Icon from './Icon.vue';
import { useI18n } from '../lib/i18n';

/**
 * Hotel health board (replaces the flat "needs attention" list): one card per hotel with a
 * status (Action needed / Watch / Note / On track) and its issues, worst hotels first; hotels
 * with nothing to report fold into one "On track" row. Status = icon + label, never colour alone.
 */
const props = defineProps({ items: { type: Array, default: () => [] }, date: String });
const { t, name } = useI18n();
const page = usePage();

const STATUS = {
    critical: { label: 'Action needed', icon: 'alert', pill: 'bg-coral text-white', strip: 'bg-coral', tint: 'bg-coral-tint text-coral-dark', rank: 0 },
    warning: { label: 'Watch', icon: 'trend', pill: 'bg-peach text-on-peach', strip: 'bg-peach', tint: 'bg-peach-tint text-peach-ink', rank: 1 },
    info: { label: 'Note', icon: 'sparkles', pill: 'bg-neutral text-ink-2', strip: 'bg-muted/50', tint: 'bg-neutral text-ink-2', rank: 2 },
    ok: { label: 'On track', icon: 'check', pill: 'bg-success-tint text-success', strip: 'bg-success', tint: 'bg-success-tint text-success', rank: 3 },
};
const ICON = {
    missing_report: 'inbox', provisional: 'refresh', occupancy_down: 'bed', adr_down: 'tag', revpar_below_group: 'chart',
    below_budget: 'coins', tb_unbalanced: 'scale', ar_growing: 'wallet', needs_review: 'alert',
    guest_checked_out_balance: 'users', guest_large_balance: 'wallet',
};
const LINK = {
    missing_report: '/documents', needs_review: '/documents?status=needs_review', tb_unbalanced: '/trial-balance',
    ar_growing: '/ledgers', guest_checked_out_balance: '/ledgers', guest_large_balance: '/ledgers',
    below_budget: '/manager-flash?view=compare&basis=budget&period=mtd',
};
// Dates inside Arabic sentences stay left to right (LRI … PDI), so 2026-11-20 does not flip.
const msg = (i) => t(`attention_${i.type}`, Object.fromEntries(Object.entries(i.params ?? {}).map(([k, v]) => [k, /^\d{4}-\d\d-\d\d$/.test(String(v)) ? `\u2066${v}\u2069` : v])));
const href = (i) => LINK[i.type] ?? (i.hotel ? `/manager-flash?view=focus&period=mtd&focus=${i.hotel.id}&date=${props.date}` : '/manager-flash');

const scopeHotels = computed(() => {
    const scope = page.props.scope ?? { hotels: [] };
    return scope.current ? scope.hotels.filter((h) => h.id === scope.current) : scope.hotels;
});

const levelOf = (list) => (list.length ? list.reduce((w, i) => (STATUS[i.level].rank < STATUS[w].rank ? i.level : w), 'info') : 'ok');

const cards = computed(() => scopeHotels.value
    .map((h) => {
        const issues = props.items.filter((i) => i.hotel?.id === h.id);
        return { hotel: h, issues, status: levelOf(issues) };
    })
    .sort((a, b) => STATUS[a.status].rank - STATUS[b.status].rank || b.issues.length - a.issues.length));

const flagged = computed(() => cards.value.filter((c) => c.status !== 'ok'));
const onTrack = computed(() => cards.value.filter((c) => c.status === 'ok'));
const groupIssues = computed(() => props.items.filter((i) => !i.hotel));
const counts = computed(() => Object.fromEntries(['critical', 'warning', 'info', 'ok'].map((s) => [s, cards.value.filter((c) => c.status === s).length])));

const expanded = reactive({});
const shown = (c) => (expanded[c.hotel.id] ? c.issues : c.issues.slice(0, 3));
</script>

<template>
    <section class="card rise overflow-hidden">
        <!-- Header + status summary -->
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-line px-6 py-5">
            <div class="flex items-center gap-3">
                <span class="grid size-10 place-items-center rounded-xl" :class="flagged.length ? 'bg-coral-tint text-coral' : 'bg-success-tint text-success'">
                    <Icon :name="flagged.length ? 'alert' : 'check'" :size="19" />
                </span>
                <div>
                    <h3 class="card-title">{{ t('Hotel health') }}</h3>
                    <p class="card-sub">{{ flagged.length ? t('What needs your attention today, hotel by hotel.') : t('Every hotel is on track today.') }}</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <div v-for="s in ['critical', 'warning', 'info', 'ok']" v-show="counts[s] || s === 'ok'" :key="s"
                     class="flex items-center gap-2 rounded-xl border border-line bg-surface py-1.5 ps-1.5 pe-3">
                    <span class="grid size-7 place-items-center rounded-lg" :class="STATUS[s].pill"><Icon :name="STATUS[s].icon" :size="14" /></span>
                    <span class="text-base leading-none font-semibold text-ink tabular">{{ counts[s] }}</span>
                    <span class="text-xs font-semibold text-ink-2">{{ t(STATUS[s].label) }}</span>
                </div>
            </div>
        </div>

        <div class="space-y-4 bg-paper/50 p-4 sm:p-5">
            <!-- Group-level issues -->
            <div v-if="groupIssues.length" class="rounded-2xl border border-line bg-surface p-4">
                <div class="mb-2 flex items-center gap-2 text-sm font-bold text-ink"><Icon name="layers" :size="16" class="text-coral" />{{ t('Group') }}</div>
                <Link v-for="(i, n) in groupIssues" :key="n" :href="href(i)" class="flex items-center gap-3 rounded-xl px-2 py-2 hover:bg-paper">
                    <span class="grid size-8 shrink-0 place-items-center rounded-lg" :class="STATUS[i.level].tint"><Icon :name="ICON[i.type] ?? 'alert'" :size="15" /></span>
                    <span class="min-w-0 flex-1 text-sm text-ink-2">{{ msg(i) }}</span>
                </Link>
            </div>

            <!-- Hotels with something to look at -->
            <div v-if="flagged.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <article v-for="c in flagged" :key="c.hotel.id" class="relative flex flex-col overflow-hidden rounded-2xl border border-line bg-surface transition hover:shadow-md">
                    <span class="absolute inset-x-0 top-0 h-1" :class="STATUS[c.status].strip" />
                    <header class="flex items-start justify-between gap-3 px-4 pt-4 pb-3">
                        <div class="min-w-0">
                            <Link :href="`/manager-flash?view=focus&period=mtd&focus=${c.hotel.id}&date=${date}`" class="block truncate font-bold text-ink hover:text-coral">{{ name(c.hotel) }}</Link>
                            <div class="mt-0.5 text-[11px] text-muted">{{ c.hotel.pms }}<template v-if="c.hotel.rooms"> · {{ t(':n rooms', { n: c.hotel.rooms }) }}</template></div>
                        </div>
                        <span class="chip shrink-0 !px-2.5 !py-1 text-[11px]" :class="STATUS[c.status].pill"><Icon :name="STATUS[c.status].icon" :size="12" />{{ t(STATUS[c.status].label) }}</span>
                    </header>
                    <ul class="flex-1 space-y-0.5 px-2 pb-2">
                        <li v-for="(i, n) in shown(c)" :key="n">
                            <Link :href="href(i)" class="group flex items-center gap-3 rounded-xl px-2 py-2 transition hover:bg-paper">
                                <span class="grid size-8 shrink-0 place-items-center rounded-lg" :class="STATUS[i.level].tint"><Icon :name="ICON[i.type] ?? 'alert'" :size="15" /></span>
                                <span class="min-w-0 flex-1 text-[13px] leading-snug text-ink-2 group-hover:text-ink">{{ msg(i) }}</span>
                                <Icon name="chevron" :size="14" class="shrink-0 -rotate-90 text-muted opacity-0 transition group-hover:opacity-100 rtl:rotate-90" />
                            </Link>
                        </li>
                    </ul>
                    <button v-if="c.issues.length > 3" type="button" class="border-t border-line px-4 py-2.5 text-start text-xs font-bold text-coral hover:bg-paper"
                            @click="expanded[c.hotel.id] = !expanded[c.hotel.id]">
                        {{ expanded[c.hotel.id] ? t('Show less') : t('+:n more', { n: c.issues.length - 3 }) }}
                    </button>
                </article>
            </div>

            <!-- Hotels on track, folded into one row -->
            <div v-if="onTrack.length" class="flex flex-wrap items-center gap-2 rounded-2xl border border-success/20 bg-success-tint/50 px-4 py-3">
                <span class="me-1 flex items-center gap-2 text-sm font-bold text-success"><Icon name="check" :size="16" />{{ t('On track') }}</span>
                <Link v-for="c in onTrack" :key="c.hotel.id" :href="`/manager-flash?view=focus&period=mtd&focus=${c.hotel.id}&date=${date}`"
                      class="rounded-full border border-success/25 bg-surface px-3 py-1 text-xs font-semibold text-ink hover:border-success">{{ name(c.hotel) }}</Link>
                <span class="ms-auto hidden text-xs text-success/80 sm:inline">{{ t('No issues found in today’s checks.') }}</span>
            </div>
        </div>
    </section>
</template>
