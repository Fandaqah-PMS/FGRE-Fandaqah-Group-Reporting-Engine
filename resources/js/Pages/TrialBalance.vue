<script setup>
import { computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import PageHeader from '../Components/PageHeader.vue';
import ViewToggle from '../Components/ViewToggle.vue';
import KpiCard from '../Components/KpiCard.vue';
import Icon from '../Components/Icon.vue';
import ColumnChart from '../Components/Charts/ColumnChart.vue';
import Donut from '../Components/Charts/Donut.vue';
import HotelBars from '../Components/Charts/HotelBars.vue';
import { compact, money } from '../lib/format';
import { BRAND } from '../lib/palette';
import { useViewMode } from '../lib/view';
import { useI18n } from '../lib/i18n';

const props = defineProps({ dates: Array, date: String, periodStart: String, hotels: Array, accounts: Array, summary: Object });
const { t, name } = useI18n();
const page = usePage();
const view = useViewMode();

const scope = computed(() => page.props.scope ?? { current: null, hotels: [] });
const scopeHotel = computed(() => scope.value.hotels.find((h) => h.id === scope.value.current) ?? null);
const multi = computed(() => props.hotels.length > 1);
const currency = computed(() => props.hotels[0]?.currency ?? 'SAR');

// Accounting style: credit balances in parentheses, no "-0" from rounding dust.
const bal = (v) => {
    if (v === undefined || v === null) return '';
    const n = Math.abs(Number(v)) < 0.005 ? 0 : Number(v);
    return n < 0 ? `(${money(-n, 0)})` : money(n, 0);
};
const balanced = computed(() => Math.abs(Number(props.summary?.net ?? 0)) < 0.05);
const net = computed(() => Number(props.summary?.revenue ?? 0) - Number(props.summary?.expenses ?? 0));
const margin = computed(() => (Number(props.summary?.revenue) > 0 ? (net.value / Number(props.summary.revenue)) * 100 : null));

const CLASSES = ['assets', 'liabilities', 'equity', 'revenue', 'expenses', 'other'];
const classLabel = (c) => t(`class_${c}`);
const sections = computed(() => CLASSES.map((c) => ({ key: c, accounts: props.accounts.filter((a) => a.class === c) })).filter((s) => s.accounts.length));
const hotelTotal = (id) => props.accounts.reduce((s, a) => s + Number(a.hotels[id] ?? 0), 0);

// Charts
const hotelCats = computed(() => props.summary.byHotel.map((h) => ({ key: String(h.id), label: name(h).split(' ').slice(0, 2).join(' '), tooltip: name(h) })));
const hotelValues = computed(() => Object.fromEntries(props.summary.byHotel.map((h) => [String(h.id), { revenue: h.revenue, expenses: h.expenses }])));
const revExpSeries = computed(() => [{ key: 'revenue', label: t('Revenue'), color: BRAND.coral }, { key: 'expenses', label: t('Expenses'), color: BRAND.navy }]);

const top = (cls, sign, colors) => {
    const list = props.accounts.filter((a) => a.class === cls).map((a) => ({ key: a.code, label: a.name, value: sign * Number(a.total) })).sort((a, b) => b.value - a.value);
    const head = list.slice(0, colors.length - 1);
    const rest = list.slice(colors.length - 1).reduce((s, a) => s + a.value, 0);
    const out = head.map((a, i) => ({ ...a, color: colors[i] }));
    if (rest > 0) out.push({ key: 'rest', label: t('Other'), value: rest, color: colors[colors.length - 1] });
    return out;
};
const revenueMix = computed(() => top('revenue', -1, [BRAND.navy, BRAND.coral, BRAND.peach, BRAND.muted]));
const expenseRows = computed(() => props.accounts.filter((a) => a.class === 'expenses')
    .map((a) => ({ id: a.code, name: a.name, name_ar: a.name, ty: Number(a.total) })));
const marginRows = computed(() => props.summary.byHotel.map((h) => ({ id: h.id, name: h.name, name_ar: h.name_ar, ty: h.revenue > 0 ? ((h.revenue - h.expenses) / h.revenue) * 100 : null })));
</script>

<template>
    <Head :title="t('Trial Balance')" />

    <PageHeader :eyebrow="t('Reports / Trial Balance')" :title="t('Trial Balance')">
        <template #subtitle>
            <Icon :name="scopeHotel ? 'building' : 'layers'" :size="15" class="text-coral" />
            <b class="text-navy">{{ scopeHotel ? name(scopeHotel) : t('All hotels, consolidated') }}</b>
            <template v-if="date"><span class="text-muted">·</span><bdi class="tabular">{{ periodStart ? `${periodStart} → ` : '' }}{{ date }}</bdi></template>
        </template>
        <template #actions>
            <select v-if="dates.length" :value="date" dir="ltr" class="field !w-auto !py-2 text-sm font-semibold" :aria-label="t('Period end')"
                    @change="router.get('/trial-balance', { date: $event.target.value }, { preserveScroll: true })">
                <option v-for="d in dates" :key="d" :value="d">{{ d }}</option>
            </select>
            <ViewToggle />
        </template>
    </PageHeader>

    <div v-if="!accounts.length" class="card mt-6 p-10 text-center text-ink-2">{{ t('No trial balance received yet.') }}</div>

    <div v-else class="mt-6 space-y-6">
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <KpiCard icon="wallet" :label="t('Revenue')" :value="compact(summary.revenue)" :unit="currency" />
            <KpiCard icon="coins" :label="t('Expenses')" :value="compact(summary.expenses)" :unit="currency" />
            <KpiCard icon="trend" :label="t('Result before other items')" :value="compact(net)" :unit="currency" :note="margin === null ? '' : `${t('Margin')} ${margin.toFixed(1)}%`" />
            <div class="card rise flex flex-col justify-between p-5">
                <div class="flex items-start justify-between gap-3">
                    <span class="text-[13px] font-semibold text-ink-2">{{ t('Debits = credits') }}</span>
                    <span class="grid size-10 place-items-center rounded-xl" :class="balanced ? 'bg-success-tint text-success' : 'bg-coral-tint text-coral'"><Icon :name="balanced ? 'check' : 'alert'" :size="19" /></span>
                </div>
                <div class="mt-2 text-[1.6rem] font-extrabold" :class="balanced ? 'text-success' : 'text-coral-dark'">{{ balanced ? t('Balanced') : t('Not balanced') }}</div>
                <div class="mt-1 text-xs text-muted">{{ t(':n accounts · :h hotels', { n: accounts.length, h: hotels.length }) }}</div>
            </div>
        </section>

        <template v-if="view === 'charts'">
            <section class="grid gap-6 xl:grid-cols-3">
                <div v-if="multi" class="card rise p-6 xl:col-span-2">
                    <h3 class="card-title">{{ t('Revenue and expenses by hotel') }}</h3>
                    <p class="mb-3 text-xs text-muted">{{ currency }}</p>
                    <ColumnChart :categories="hotelCats" :series="revExpSeries" :values="hotelValues" :format="(v, axis) => (axis ? compact(v) : money(v, 0))" :height="280" />
                </div>
                <div class="card rise p-6" :class="!multi && 'xl:col-span-1'">
                    <h3 class="card-title">{{ t('Revenue by account') }}</h3>
                    <p class="mb-5 text-xs text-muted">{{ t('Largest revenue accounts') }}</p>
                    <Donut :segments="revenueMix" :format="(v) => compact(v)" :center-label="t('Revenue')" :size="170" />
                </div>
                <div v-if="!multi" class="card rise p-6 xl:col-span-2">
                    <h3 class="card-title">{{ t('Expenses by account') }}</h3>
                    <p class="mb-4 text-xs text-muted">{{ currency }}</p>
                    <HotelBars :rows="expenseRows" :format="(v) => money(v, 0)" :show-ly="false" :color="BRAND.navy" />
                </div>
            </section>

            <section v-if="multi" class="grid gap-6 xl:grid-cols-2">
                <div class="card rise p-6">
                    <h3 class="card-title">{{ t('Expenses by account') }}</h3>
                    <p class="mb-4 text-xs text-muted">{{ t('All hotels') }} · {{ currency }}</p>
                    <HotelBars :rows="expenseRows" :format="(v) => money(v, 0)" :show-ly="false" :color="BRAND.navy" />
                </div>
                <div class="card rise p-6">
                    <h3 class="card-title">{{ t('Margin by hotel') }}</h3>
                    <p class="mb-4 text-xs text-muted">{{ t('(Revenue − expenses) ÷ revenue') }}</p>
                    <HotelBars :rows="marginRows" :format="(v) => (v === null ? '–' : `${Number(v).toFixed(1)}%`)" :show-ly="false" />
                </div>
            </section>
        </template>

        <section v-else class="card overflow-hidden">
            <div class="px-6 pt-6 pb-4">
                <h3 class="card-title">{{ t('Consolidated trial balance') }}</h3>
                <p class="text-xs text-muted">{{ t('Closing balances by account and hotel. Credit balances in parentheses.') }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="tbl tabular" :style="{ minWidth: `${24 + hotels.length * 9}rem` }">
                    <thead><tr>
                        <th class="sticky start-0 z-10">{{ t('Account') }}</th>
                        <th v-for="h in hotels" :key="h.id" class="!text-end">{{ name(h) }}</th>
                        <th v-if="multi" class="!text-end !text-navy">{{ t('Group') }}</th>
                    </tr></thead>
                    <tbody v-for="s in sections" :key="s.key">
                        <tr><td :colspan="hotels.length + 2" class="!bg-paper !py-2 text-[11px] font-bold tracking-wider text-coral uppercase">{{ classLabel(s.key) }}</td></tr>
                        <tr v-for="a in s.accounts" :key="a.code">
                            <td class="sticky start-0 bg-white"><span class="me-2 font-semibold text-muted" dir="ltr">{{ a.code }}</span>{{ a.name }}</td>
                            <td v-for="h in hotels" :key="h.id" class="text-end"><bdi>{{ bal(a.hotels[h.id]) }}</bdi></td>
                            <td v-if="multi" class="text-end font-bold"><bdi>{{ bal(a.total) }}</bdi></td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-paper font-bold">
                        <tr>
                            <td class="sticky start-0 bg-paper px-4 py-3">{{ t('Net (should be zero)') }}</td>
                            <td v-for="h in hotels" :key="h.id" class="px-4 text-end"><bdi>{{ bal(hotelTotal(h.id)) }}</bdi></td>
                            <td v-if="multi" class="px-4 text-end"><bdi><span :class="balanced ? 'text-success' : 'text-coral-dark'">{{ balanced ? '✓' : '!' }}</span> {{ bal(summary.net) }}</bdi></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
    </div>
</template>
