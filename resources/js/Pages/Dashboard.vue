<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PageHeader from '../Components/PageHeader.vue';
import ViewToggle from '../Components/ViewToggle.vue';
import Hero from '../Components/Hero.vue';
import KpiCard from '../Components/KpiCard.vue';
import Delta from '../Components/Delta.vue';
import Icon from '../Components/Icon.vue';
import AreaTrend from '../Components/Charts/AreaTrend.vue';
import ColumnChart from '../Components/Charts/ColumnChart.vue';
import Donut from '../Components/Charts/Donut.vue';
import Gauge from '../Components/Charts/Gauge.vue';
import Heatmap from '../Components/Charts/Heatmap.vue';
import HotelBars from '../Components/Charts/HotelBars.vue';
import SegmentBar from '../Components/Charts/SegmentBar.vue';
import HealthBoard from '../Components/HealthBoard.vue';
import SubscribeButton from '../Components/Subscriptions/SubscribeButton.vue';
import RankingTable from '../Components/RankingTable.vue';
import { compact, dayNum, int, isNum, longDate, money, monthLabel, pct, shortDate, weekday } from '../lib/format';
import { BRAND, LAST_YEAR_BAR, MIX, SERIES, THIS_YEAR } from '../lib/palette';
import { useViewMode } from '../lib/view';
import { useI18n } from '../lib/i18n';

const props = defineProps({
    empty: Boolean, filters: Object, currency: String, group: Object, groupLy: Object, hotels: Array, missing: Array,
    hotelCount: Number, trend: Array, monthly: Array, heatmap: Object, month: Array, mtd: Object, mtdLy: Object, attention: Array, ranking: Array,
});

const { t, name, locale } = useI18n();
const page = usePage();
const view = useViewMode();

const scope = computed(() => page.props.scope ?? { current: null, hotels: [] });
const scopeHotel = computed(() => scope.value.hotels.find((h) => h.id === scope.value.current) ?? null);
const groupName = computed(() => name(page.props.auth?.groups?.find((g) => g.id === page.props.auth.currentGroupId)));
const multi = computed(() => !scopeHotel.value && props.hotelCount > 1);

const periods = computed(() => [{ key: 'day', label: t('Day') }, { key: 'mtd', label: t('Month to date') }, { key: 'ytd', label: t('Year to date') }]);
const periodLabel = computed(() => periods.value.find((p) => p.key === props.filters?.period)?.label);
const loading = ref(false);
function apply(changes) {
    router.get('/', { date: props.filters.date, period: props.filters.period, ...changes }, {
        preserveScroll: true, preserveState: true, onStart: () => (loading.value = true), onFinish: () => (loading.value = false),
    });
}

const fmtPct = (v, axis) => (axis ? `${compact(v)}%` : pct(v));
const fmtMoney = (v, axis) => (axis ? compact(v) : money(v));
const fmtMoney0 = (v, axis) => (axis ? compact(v) : money(v, 0));
const metrics = computed(() => ({
    occupancy_pct: { label: t('Occupancy'), format: fmtPct, kind: 'pp' },
    adr: { label: t('ADR'), format: fmtMoney, kind: 'pct' },
    revpar: { label: t('RevPAR'), format: fmtMoney, kind: 'pct' },
    total_revenue: { label: t('Total revenue'), format: fmtMoney0, kind: 'pct' },
}));
const trendMetric = ref('occupancy_pct');
const barMetric = ref('revpar');
const monthMetric = ref('total_revenue');

const spark = (key) => (props.trend ?? []).map((p) => p[key]);
const trendPoints = computed(() => (props.trend ?? []).map((p) => ({ date: p.date, ty: p[trendMetric.value], ly: p[`ly_${trendMetric.value}`] })));

// Last 14 days: occupancy columns with labels, and arrivals / departures.
const last14 = computed(() => (props.trend ?? []).slice(-14));
const dayCats = computed(() => last14.value.map((p) => ({ key: p.date, label: weekday(p.date, locale.value), sub: dayNum(p.date), tooltip: longDate(p.date, locale.value) })));
const occValues = computed(() => Object.fromEntries(last14.value.map((p) => [p.date, { occ: p.occupancy_pct }])));
const moveValues = computed(() => Object.fromEntries(last14.value.map((p) => [p.date, { arr: p.arrival_rooms, dep: p.departure_rooms }])));

// Status of rooms for the selected period (room nights).
const roomStatus = computed(() => {
    const g = props.group ?? {};
    const ooo = Number(g.ooo_rooms ?? 0);
    return [
        { key: 'sold', label: t('Sold'), value: Number(g.paid_occupied ?? 0), color: BRAND.navy },
        { key: 'comp', label: t('Comp & house use'), value: Number(g.comp_rooms ?? 0) + Number(g.house_use_rooms ?? 0), color: BRAND.coral },
        { key: 'vacant', label: t('Vacant'), value: Math.max(0, Number(g.total_rooms ?? 0) - ooo - Number(g.rooms_occupied ?? 0)), color: BRAND.lightGrey },
        { key: 'ooo', label: t('Out of order'), value: ooo, color: BRAND.green },
    ];
});
const mix = computed(() => [
    { key: 'rooms', label: t('Rooms'), value: props.group?.room_revenue, color: MIX.rooms },
    { key: 'fnb', label: t('Food & beverage'), value: props.group?.fnb_revenue, color: MIX.fnb },
    { key: 'other', label: t('Other'), value: props.group?.other_revenue, color: MIX.other },
]);
const share = computed(() => {
    const list = [...(props.hotels ?? [])].sort((a, b) => Number(b.total_revenue ?? 0) - Number(a.total_revenue ?? 0));
    const head = list.slice(0, 4).map((h, i) => ({ key: h.property_id, label: name(h), value: Number(h.total_revenue ?? 0), color: SERIES[i] }));
    const rest = list.slice(4).reduce((s, h) => s + Number(h.total_revenue ?? 0), 0);
    return rest > 0 ? [...head, { key: 'other', label: t('Other hotels'), value: rest, color: SERIES[4] }] : head;
});

// This month vs the same month last year, cumulative room revenue by day.
const monthKey = computed(() => props.filters?.date?.slice(0, 7));
const daysInMonth = computed(() => (monthKey.value ? new Date(Number(monthKey.value.slice(0, 4)), Number(monthKey.value.slice(5, 7)), 0).getDate() : 30));
const cumulative = computed(() => {
    const byDay = Object.fromEntries((props.month ?? []).map((p) => [Number(p.date.slice(8, 10)), p]));
    let ty = 0, ly = 0;
    const last = Math.max(0, ...Object.keys(byDay).map(Number));
    return Array.from({ length: daysInMonth.value }, (_, i) => {
        const d = i + 1, p = byDay[d];
        if (p) { ty += Number(p.room_revenue ?? 0); ly += Number(p.ly_room_revenue ?? 0); }
        return { key: String(d), day: d, ty: d <= last ? ty : null, ly: d <= last ? ly : null };
    });
});
const lyYear = computed(() => (monthKey.value ? `${Number(monthKey.value.slice(0, 4)) - 1}${monthKey.value.slice(4)}` : null));

const months = computed(() => (props.monthly ?? []).map((m) => ({ key: m.month, label: monthLabel(m.month, locale.value), tooltip: monthLabel(m.month, locale.value, true) })));
const monthValues = computed(() => Object.fromEntries((props.monthly ?? []).map((m) => [m.month, { ty: m[monthMetric.value], ly: m[`ly_${monthMetric.value}`] }])));
const yearSeries = computed(() => [{ key: 'ty', label: t('This year'), color: THIS_YEAR }, { key: 'ly', label: t('Last year'), color: LAST_YEAR_BAR }]);
const barRows = computed(() => (props.hotels ?? []).map((h) => ({ id: h.property_id, name: h.name, name_ar: h.name_ar, ty: h[barMetric.value], ly: h.ly?.[barMetric.value] ?? null })));

const sort = ref({ key: 'revpar', dir: -1 });
const sortedHotels = computed(() => [...(props.hotels ?? [])].sort((a, b) => sort.value.dir * (Number(a[sort.value.key] ?? -1e12) - Number(b[sort.value.key] ?? -1e12))));
const sortBy = (key) => (sort.value = { key, dir: sort.value.key === key ? -sort.value.dir : -1 });
</script>

<template>
    <Head :title="t('Dashboard')" />

    <div v-if="empty" class="card mx-auto mt-10 max-w-xl overflow-hidden text-center">
        <div class="brand-pattern-full h-28 opacity-80" />
        <div class="p-10">
            <h1 class="font-display text-2xl font-semibold">{{ t('No reports yet') }}</h1>
            <p class="mt-2 text-ink-2">{{ t('Figures appear here as soon as the first Manager Flash report from one of your hotels is processed.') }}</p>
        </div>
    </div>

    <template v-else>
        <PageHeader :eyebrow="t('Fandaqah / Group overview')" :title="t('Dashboard')">
            <template #subtitle>
                <Icon name="calendar" :size="15" class="text-coral" />{{ t('Business date') }}: <b class="text-navy">{{ longDate(filters.date, locale) }}</b>
            </template>
            <template #actions>
                <input type="date" :value="filters.date" :max="filters.latest" dir="ltr" class="field !w-auto !py-2 text-sm font-semibold"
                       :aria-label="t('Business date')" @change="apply({ date: $event.target.value })" />
                <div class="seg" role="tablist" :aria-label="t('Period')">
                    <button v-for="p in periods" :key="p.key" type="button" role="tab" :aria-selected="filters.period === p.key" @click="apply({ period: p.key })">{{ p.label }}</button>
                </div>
                <ViewToggle />
                <SubscribeButton v-if="page.props.auth?.can?.['view reports']" />
            </template>
        </PageHeader>

        <div class="mt-6 space-y-6 transition-opacity" :class="loading && 'opacity-60'">
            <Hero :eyebrow="scopeHotel ? t('Hotel performance') : t('Group performance')"
                  :title="scopeHotel ? name(scopeHotel) : groupName"
                  :text="scopeHotel ? `${scopeHotel.pms} · ${t(':n rooms', { n: scopeHotel.rooms ?? '–' })}` : t('Every hotel in your group, consolidated, whichever PMS it runs.')">
                <span class="chip bg-white/10 text-white"><Icon name="calendar" :size="14" />{{ periodLabel }} · {{ longDate(filters.date, locale) }}</span>
                <span v-if="group.is_provisional" class="chip bg-coral text-white">{{ t('Includes provisional figures') }}</span>
            </Hero>

            <HealthBoard :items="attention ?? []" :date="filters.date" />

            <!-- KPI cards -->
            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <KpiCard :dot="BRAND.coral" :label="t('Occupancy')" :value="isNum(group.occupancy_pct) ? Number(group.occupancy_pct).toFixed(1) : '–'" unit="%"
                         :current="group.occupancy_pct" :previous="groupLy.occupancy_pct" kind="pp" :note="t(':sold of :total rooms sold', { sold: int(group.paid_occupied), total: int(group.sellable_rooms) })" :spark="spark('occupancy_pct')" />
                <KpiCard :dot="BRAND.navy" :label="t('ADR')" :value="money(group.adr)" :unit="currency"
                         :current="group.adr" :previous="groupLy.adr" :previous-label="money(groupLy.adr)" :spark="spark('adr')" />
                <KpiCard :dot="BRAND.green" :label="t('RevPAR')" :value="money(group.revpar)" :unit="currency"
                         :current="group.revpar" :previous="groupLy.revpar" :previous-label="money(groupLy.revpar)" :spark="spark('revpar')" />
                <KpiCard :dot="BRAND.peach" :label="t('Total revenue')" :value="compact(group.total_revenue)" :unit="currency"
                         :current="group.total_revenue" :previous="groupLy.total_revenue" :previous-label="compact(groupLy.total_revenue)" :spark="spark('total_revenue')" />
                <KpiCard v-if="!scopeHotel" :dot="BRAND.coral" :label="t('Hotels reported')" :value="`${group.hotel_count} / ${hotelCount}`" :highlight="missing.length > 0"
                         :note="missing.length ? `${t('Waiting for')}: ${missing.map((m) => name(m)).join('، ')}` : t('All hotels reported')"
                         :progress="hotelCount ? (group.hotel_count / hotelCount) * 100 : 0" />
                <KpiCard v-else :dot="BRAND.coral" :label="t('Room nights sold')" :value="int(group.paid_occupied)" :highlight="true"
                         :note="t(':sold of :total rooms sold', { sold: int(group.paid_occupied), total: int(group.sellable_rooms) })"
                         :progress="Number(group.occupancy_pct ?? 0)" />
            </section>

            <!-- CHARTS -->
            <template v-if="view === 'charts'">
                <section class="grid gap-6 xl:grid-cols-3">
                    <div class="card rise p-6">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="card-title">{{ t('Status of rooms') }}</h3>
                            <span class="card-sub"><bdi>{{ periodLabel }} · {{ int(group.total_rooms) }} {{ t('room nights') }}</bdi></span>
                        </div>
                        <div class="mt-5"><Donut :segments="roomStatus" :format="int" :center-value="pct(group.occupancy_pct)" :center-label="t('Occupancy')" /></div>
                    </div>
                    <div class="card rise p-6 xl:col-span-2">
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <h3 class="card-title">{{ t(':metric, last 30 days', { metric: metrics[trendMetric].label }) }}</h3>
                            <div class="seg">
                                <button v-for="(m, key) in metrics" :key="key" type="button" :aria-pressed="trendMetric === key" @click="trendMetric = key">{{ m.label }}</button>
                            </div>
                        </div>
                        <AreaTrend :points="trendPoints" :format="metrics[trendMetric].format" :average="true" :height="330" />
                    </div>
                </section>

                <section class="grid gap-6 xl:grid-cols-2">
                    <div class="card rise p-6">
                        <h3 class="card-title mb-4">{{ t('Occupancy, last 14 days') }}</h3>
                        <ColumnChart :categories="dayCats" :series="[{ key: 'occ', label: t('Occupancy'), color: BRAND.navy }]" :values="occValues"
                                     :format="(v, axis) => (axis ? `${Number(v).toFixed(0)}%` : pct(v))" :value-labels="true" :average="true" :show-legend="false" :height="250" />
                    </div>
                    <div class="card rise p-6">
                        <h3 class="card-title mb-4">{{ t('Arrivals and departures, last 14 days') }}</h3>
                        <ColumnChart :categories="dayCats" :series="[{ key: 'arr', label: t('Arrivals'), color: BRAND.coral }, { key: 'dep', label: t('Departures'), color: BRAND.navy }]"
                                     :values="moveValues" :format="(v) => int(v)" :height="250" />
                    </div>
                </section>

                <section class="grid gap-6" :class="multi ? 'xl:grid-cols-3' : 'xl:grid-cols-2'">
                    <div class="card rise flex flex-col p-6">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="card-title">{{ t('Room nights') }}</h3>
                            <span class="card-sub">{{ periodLabel }}</span>
                        </div>
                        <div class="mt-5"><SegmentBar :parts="roomStatus" /></div>
                        <div class="mt-auto pt-6"><Gauge :value="group.occupancy_pct" :previous="groupLy.occupancy_pct" :label="t('Occupancy')" /></div>
                    </div>
                    <div class="card rise p-6">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="card-title">{{ t('Revenue mix') }}</h3>
                            <span class="card-sub">{{ periodLabel }} · {{ currency }}</span>
                        </div>
                        <div class="mt-5"><Donut :segments="mix" :format="(v) => compact(v)" :center-label="t('Total revenue')" /></div>
                    </div>
                    <div v-if="multi" class="card rise p-6">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="card-title">{{ t('Revenue by hotel') }}</h3>
                            <span class="card-sub">{{ periodLabel }} · {{ currency }}</span>
                        </div>
                        <div class="mt-5"><Donut :segments="share" :format="(v) => compact(v)" :center-value="String(group.hotel_count)" :center-label="t('Hotels')" /></div>
                    </div>
                </section>

                <section class="grid gap-6 xl:grid-cols-3">
                    <div class="card rise p-6 xl:col-span-2">
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <h3 class="card-title">{{ t('Room revenue, this month vs last year') }}</h3>
                            <span class="card-sub">{{ t('Cumulative') }} · {{ currency }}</span>
                        </div>
                        <AreaTrend :points="cumulative" :format="fmtMoney0" :x-label="(p) => p.day" :tooltip-label="(p) => `${p.day} ${monthLabel(monthKey, locale)}`"
                                   :label-width="26" :ty-label="monthLabel(monthKey, locale, true)" :ly-label="monthLabel(lyYear, locale, true)" :height="300" />
                    </div>
                    <div class="grid gap-4">
                        <KpiCard :dot="BRAND.navy" :label="t('Room revenue this month')" :value="money(mtd.room_revenue, 0)" :unit="currency"
                                 :current="mtd.room_revenue" :previous="mtdLy.room_revenue" :note="t('vs the same days last year')" />
                        <KpiCard :dot="BRAND.coral" :label="t('ADR (average daily rate)')" :value="money(mtd.adr)" :unit="currency"
                                 :current="mtd.adr" :previous="mtdLy.adr" :note="t('vs the same days last year')" />
                        <KpiCard :dot="BRAND.peach" :label="t('RevPAR (revenue per available room)')" :value="money(mtd.revpar)" :unit="currency"
                                 :current="mtd.revpar" :previous="mtdLy.revpar" :note="t('vs the same days last year')" />
                    </div>
                </section>

                <section class="card rise p-6">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <h3 class="card-title">{{ t(':metric by month', { metric: metrics[monthMetric].label }) }}</h3>
                        <div class="seg">
                            <button v-for="(m, key) in metrics" :key="key" type="button" :aria-pressed="monthMetric === key" @click="monthMetric = key">{{ m.label }}</button>
                        </div>
                    </div>
                    <ColumnChart :categories="months" :series="yearSeries" :values="monthValues" :format="metrics[monthMetric].format" :height="280" />
                    <p class="mt-2 text-xs text-muted">{{ t('Current month to date, compared with the same days last year.') }}</p>
                </section>

                <RankingTable v-if="ranking?.length" :rows="ranking" :date="filters.date" />

                <section v-if="multi" class="grid gap-6 xl:grid-cols-2">
                    <div class="card rise p-6">
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <h3 class="card-title">{{ t(':metric by hotel', { metric: metrics[barMetric].label }) }}</h3>
                            <div class="seg">
                                <button v-for="key in ['revpar', 'occupancy_pct', 'adr', 'total_revenue']" :key="key" type="button" :aria-pressed="barMetric === key" @click="barMetric = key">{{ metrics[key].label }}</button>
                            </div>
                        </div>
                        <HotelBars :rows="barRows" :format="(v) => metrics[barMetric].format(v)" :delta-kind="metrics[barMetric].kind" />
                    </div>
                    <div v-if="heatmap" class="card rise p-6">
                        <h3 class="card-title">{{ t('Occupancy by hotel, last 14 days') }}</h3>
                        <p class="mb-4 card-sub">{{ t('Each square is one hotel on one day. Hover for the figure.') }}</p>
                        <Heatmap :data="heatmap" />
                    </div>
                </section>
            </template>

            <!-- TABLES -->
            <template v-else>
                <RankingTable v-if="ranking?.length" :rows="ranking" :date="filters.date" />
                <section v-if="multi" class="card overflow-hidden">
                    <div class="flex items-center justify-between px-6 pt-6 pb-4">
                        <h3 class="card-title">{{ t('Hotel comparison') }}</h3>
                        <span class="card-sub">{{ periodLabel }} · {{ t('Change vs same period last year') }}</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="tbl min-w-[58rem] tabular">
                            <thead><tr>
                                <th>{{ t('Hotel') }}</th><th>{{ t('PMS') }}</th><th class="!text-end">{{ t('Rooms sold') }}</th>
                                <th v-for="col in ['occupancy_pct', 'adr', 'revpar', 'total_revenue']" :key="col" class="cursor-pointer !text-end select-none hover:text-navy" @click="sortBy(col)">
                                    {{ metrics[col].label }} <span v-if="sort.key === col">{{ sort.dir < 0 ? '↓' : '↑' }}</span>
                                </th>
                            </tr></thead>
                            <tbody>
                                <tr v-for="h in sortedHotels" :key="h.property_id">
                                    <td><Link :href="`/hotels/${h.property_id}?date=${filters.date}`" class="font-bold text-navy hover:text-coral">{{ name(h) }}</Link>
                                        <span v-if="h.is_provisional" class="chip ms-2 bg-coral-tint !px-2 !py-0 text-[10px] text-coral-dark">{{ t('Provisional') }}</span></td>
                                    <td class="text-ink-2">{{ h.pms }}</td>
                                    <td class="text-end"><bdi>{{ int(h.paid_occupied) }} <span class="text-muted">/ {{ int(h.sellable_rooms) }}</span></bdi></td>
                                    <td v-for="col in ['occupancy_pct', 'adr', 'revpar', 'total_revenue']" :key="col" class="text-end">
                                        <div class="font-semibold"><bdi>{{ metrics[col].format(h[col]) }}</bdi></div>
                                        <Delta :current="h[col]" :previous="h.ly?.[col]" :kind="metrics[col].kind" />
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-paper font-bold">
                                <tr>
                                    <td class="px-4 py-3" colspan="2">{{ t('Group total') }}</td>
                                    <td class="px-4 text-end"><bdi>{{ int(group.paid_occupied) }} <span class="font-normal text-muted">/ {{ int(group.sellable_rooms) }}</span></bdi></td>
                                    <td v-for="col in ['occupancy_pct', 'adr', 'revpar', 'total_revenue']" :key="col" class="px-4 py-3 text-end">
                                        <div><bdi>{{ metrics[col].format(group[col]) }}</bdi></div>
                                        <Delta :current="group[col]" :previous="groupLy[col]" :kind="metrics[col].kind" />
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>

                <section class="grid gap-6 xl:grid-cols-2">
                    <div class="card overflow-hidden">
                        <h3 class="card-title px-6 pt-6 pb-4">{{ t('Daily figures, last 30 days') }}</h3>
                        <div class="max-h-[28rem] overflow-auto">
                            <table class="tbl tabular">
                                <thead class="sticky top-0"><tr><th>{{ t('Date') }}</th><th class="!text-end">{{ t('Occupancy') }}</th><th class="!text-end">{{ t('ADR') }}</th><th class="!text-end">{{ t('RevPAR') }}</th><th class="!text-end">{{ t('Arrivals') }}</th><th class="!text-end">{{ t('Total revenue') }}</th></tr></thead>
                                <tbody>
                                    <tr v-for="p in [...trend].reverse()" :key="p.date">
                                        <td>{{ shortDate(p.date, locale) }}</td>
                                        <td class="text-end"><bdi>{{ pct(p.occupancy_pct) }}</bdi> <span class="text-xs text-muted"><bdi>{{ pct(p.ly_occupancy_pct) }}</bdi></span></td>
                                        <td class="text-end"><bdi>{{ money(p.adr) }}</bdi></td>
                                        <td class="text-end"><bdi>{{ money(p.revpar) }}</bdi></td>
                                        <td class="text-end"><bdi>{{ int(p.arrival_rooms) }}</bdi></td>
                                        <td class="text-end font-semibold"><bdi>{{ money(p.total_revenue, 0) }}</bdi></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card overflow-hidden">
                        <h3 class="card-title px-6 pt-6 pb-4">{{ t('Monthly figures') }}</h3>
                        <div class="overflow-x-auto">
                            <table class="tbl tabular">
                                <thead><tr><th>{{ t('Month') }}</th><th class="!text-end">{{ t('Total revenue') }}</th><th class="!text-end">{{ t('Last year') }}</th><th class="!text-end">{{ t('Occupancy') }}</th><th class="!text-end">{{ t('RevPAR') }}</th></tr></thead>
                                <tbody>
                                    <tr v-for="m in [...monthly].reverse()" :key="m.month">
                                        <td class="font-semibold">{{ monthLabel(m.month, locale, true) }}</td>
                                        <td class="text-end"><bdi>{{ money(m.total_revenue, 0) }}</bdi> <Delta :current="m.total_revenue" :previous="m.ly_total_revenue" /></td>
                                        <td class="text-end text-ink-2"><bdi>{{ money(m.ly_total_revenue, 0) }}</bdi></td>
                                        <td class="text-end"><bdi>{{ pct(m.occupancy_pct) }}</bdi></td>
                                        <td class="text-end"><bdi>{{ money(m.revpar) }}</bdi></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </template>
        </div>
    </template>
</template>
