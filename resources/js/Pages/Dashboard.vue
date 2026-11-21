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
import { compact, int, isNum, longDate, money, monthLabel, pct, shortDate } from '../lib/format';
import { BRAND, LAST_YEAR, MIX, THIS_YEAR } from '../lib/palette';
import { useViewMode } from '../lib/view';
import { useI18n } from '../lib/i18n';

const props = defineProps({
    empty: Boolean, filters: Object, currency: String, group: Object, groupLy: Object,
    hotels: Array, missing: Array, hotelCount: Number, trend: Array, monthly: Array, heatmap: Object,
});

const { t, name, locale } = useI18n();
const page = usePage();
const view = useViewMode();

const scope = computed(() => page.props.scope ?? { current: null, hotels: [] });
const scopeHotel = computed(() => scope.value.hotels.find((h) => h.id === scope.value.current) ?? null);
const groupName = computed(() => name(page.props.auth?.groups?.find((g) => g.id === page.props.auth.currentGroupId)));
const multi = computed(() => (props.hotels?.length ?? 0) > 1 || (!scopeHotel.value && props.hotelCount > 1));

const periods = computed(() => [{ key: 'day', label: t('Day') }, { key: 'mtd', label: t('Month to date') }, { key: 'ytd', label: t('Year to date') }]);
const periodLabel = computed(() => periods.value.find((p) => p.key === props.filters?.period)?.label);
const loading = ref(false);
function apply(changes) {
    router.get('/', { date: props.filters.date, period: props.filters.period, ...changes }, {
        preserveScroll: true, preserveState: true, onStart: () => (loading.value = true), onFinish: () => (loading.value = false),
    });
}

const metrics = computed(() => ({
    total_revenue: { label: t('Total revenue'), format: (v, axis) => (axis ? compact(v) : money(v, 0)), kind: 'pct' },
    occupancy_pct: { label: t('Occupancy'), format: (v, axis) => (axis ? `${compact(v)}%` : pct(v)), kind: 'pp' },
    adr: { label: t('ADR'), format: (v, axis) => (axis ? compact(v) : money(v)), kind: 'pct' },
    revpar: { label: t('RevPAR'), format: (v, axis) => (axis ? compact(v) : money(v)), kind: 'pct' },
}));
const trendMetric = ref('total_revenue');
const barMetric = ref('revpar');
const monthMetric = ref('total_revenue');

const spark = (key) => (props.trend ?? []).map((p) => p[key]);
const trendPoints = computed(() => (props.trend ?? []).map((p) => ({ date: p.date, ty: p[trendMetric.value], ly: p[`ly_${trendMetric.value}`] })));

const months = computed(() => (props.monthly ?? []).map((m) => ({ key: m.month, label: monthLabel(m.month, locale.value), tooltip: monthLabel(m.month, locale.value, true) })));
const monthValues = computed(() => Object.fromEntries((props.monthly ?? []).map((m) => [m.month, { ty: m[monthMetric.value], ly: m[`ly_${monthMetric.value}`] }])));
const yearSeries = computed(() => [{ key: 'ty', label: t('This year'), color: THIS_YEAR }, { key: 'ly', label: t('Last year'), color: LAST_YEAR }]);

const mix = computed(() => [
    { key: 'rooms', label: t('Rooms'), value: props.group?.room_revenue, color: MIX.rooms },
    { key: 'fnb', label: t('Food & beverage'), value: props.group?.fnb_revenue, color: MIX.fnb },
    { key: 'other', label: t('Other'), value: props.group?.other_revenue, color: MIX.other },
]);
const roomParts = computed(() => {
    const g = props.group ?? {};
    const sold = Number(g.paid_occupied ?? 0);
    const comp = Number(g.comp_rooms ?? 0) + Number(g.house_use_rooms ?? 0);
    const ooo = Number(g.ooo_rooms ?? 0);
    const vacant = Math.max(0, Number(g.total_rooms ?? 0) - ooo - Number(g.rooms_occupied ?? 0));
    return [
        { key: 'sold', label: t('Sold'), value: sold, color: BRAND.navy },
        { key: 'comp', label: t('Comp & house use'), value: comp, color: BRAND.peach },
        { key: 'vacant', label: t('Vacant'), value: vacant, color: BRAND.neutral },
        { key: 'ooo', label: t('Out of order'), value: ooo, color: BRAND.muted },
    ];
});
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
            </template>
        </PageHeader>

        <div class="mt-6 space-y-6 transition-opacity" :class="loading && 'opacity-60'">
            <Hero :eyebrow="scopeHotel ? t('Hotel performance') : t('Group performance')"
                  :title="scopeHotel ? name(scopeHotel) : groupName"
                  :text="scopeHotel ? `${scopeHotel.pms} · ${t(':n rooms', { n: scopeHotel.rooms ?? '–' })}` : t('Every hotel in your group, consolidated, whichever PMS it runs.')">
                <span class="chip bg-white/10 text-white"><Icon name="calendar" :size="14" />{{ periodLabel }} · {{ longDate(filters.date, locale) }}</span>
                <span v-if="!scopeHotel" class="chip bg-white/10 text-white"><Icon name="building" :size="14" />{{ t(':n of :total hotels reported', { n: group.hotel_count, total: hotelCount }) }}</span>
                <span v-if="group.is_provisional" class="chip bg-coral text-white">{{ t('Includes provisional figures') }}</span>
            </Hero>

            <div v-if="missing.length" class="flex items-center gap-3 rounded-2xl border border-coral/25 bg-coral-tint px-4 py-3 text-sm text-coral-dark">
                <Icon name="alert" :size="18" />
                <span>{{ t('No report received yet for this date from:') }} <b>{{ missing.map((m) => name(m)).join('، ') }}</b></span>
            </div>

            <!-- KPI cards -->
            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard icon="bed" :label="t('Occupancy')" :value="isNum(group.occupancy_pct) ? Number(group.occupancy_pct).toFixed(1) : '–'" unit="%"
                         :current="group.occupancy_pct" :previous="groupLy.occupancy_pct" kind="pp" :previous-label="pct(groupLy.occupancy_pct)" :spark="spark('occupancy_pct')" />
                <KpiCard icon="tag" :label="t('ADR')" :value="money(group.adr)" :unit="currency"
                         :current="group.adr" :previous="groupLy.adr" :previous-label="money(groupLy.adr)" :spark="spark('adr')" />
                <KpiCard icon="trend" :label="t('RevPAR')" :value="money(group.revpar)" :unit="currency"
                         :current="group.revpar" :previous="groupLy.revpar" :previous-label="money(groupLy.revpar)" :spark="spark('revpar')" />
                <KpiCard icon="wallet" :label="t('Total revenue')" :value="compact(group.total_revenue)" :unit="currency"
                         :current="group.total_revenue" :previous="groupLy.total_revenue" :previous-label="compact(groupLy.total_revenue)" :spark="spark('total_revenue')" />
            </section>
            <p class="-mt-2 text-xs text-muted">{{ t('Occupancy, ADR and RevPAR exclude complimentary and house-use rooms and out-of-order inventory, the same way for every PMS.') }}</p>

            <!-- CHARTS -->
            <template v-if="view === 'charts'">
                <section class="grid gap-6 xl:grid-cols-3">
                    <div class="card rise p-6 xl:col-span-2">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                            <h3 class="card-title">{{ t(':metric, last 30 days', { metric: metrics[trendMetric].label }) }}</h3>
                            <div class="seg">
                                <button v-for="(m, key) in metrics" :key="key" type="button" :aria-pressed="trendMetric === key" @click="trendMetric = key">{{ m.label }}</button>
                            </div>
                        </div>
                        <AreaTrend :points="trendPoints" :format="metrics[trendMetric].format" />
                    </div>
                    <div class="card rise flex flex-col p-6">
                        <h3 class="card-title">{{ t('Occupancy') }}</h3>
                        <p class="text-xs text-muted">{{ periodLabel }}</p>
                        <div class="mt-4"><Gauge :value="group.occupancy_pct" :previous="groupLy.occupancy_pct" :label="t(':sold of :total rooms sold', { sold: int(group.paid_occupied), total: int(group.sellable_rooms) })" /></div>
                        <div class="mt-auto border-t border-line pt-5">
                            <div class="mb-3 text-xs font-bold tracking-wider text-ink-2 uppercase">{{ t('Room nights') }}</div>
                            <SegmentBar :parts="roomParts" />
                        </div>
                    </div>
                </section>

                <section class="grid gap-6 xl:grid-cols-3">
                    <div class="card rise p-6 xl:col-span-2">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                            <h3 class="card-title">{{ t(':metric by month', { metric: metrics[monthMetric].label }) }}</h3>
                            <div class="seg">
                                <button v-for="(m, key) in metrics" :key="key" type="button" :aria-pressed="monthMetric === key" @click="monthMetric = key">{{ m.label }}</button>
                            </div>
                        </div>
                        <ColumnChart :categories="months" :series="yearSeries" :values="monthValues" :format="metrics[monthMetric].format" :highlight="months.at(-1)?.key" />
                        <p class="mt-1 text-xs text-muted">{{ t('Current month to date, compared with the same days last year.') }}</p>
                    </div>
                    <div class="card rise p-6">
                        <h3 class="card-title">{{ t('Revenue mix') }}</h3>
                        <p class="mb-5 text-xs text-muted">{{ periodLabel }} · {{ currency }}</p>
                        <Donut :segments="mix" :format="(v) => compact(v)" :center-label="t('Total revenue')" />
                    </div>
                </section>

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
                        <h3 class="card-title">{{ t('Occupancy, last 14 days') }}</h3>
                        <p class="mb-4 text-xs text-muted">{{ t('Each square is one hotel on one day. Hover for the figure.') }}</p>
                        <Heatmap :data="heatmap" />
                    </div>
                </section>
            </template>

            <!-- TABLES -->
            <template v-else>
                <section v-if="multi" class="card overflow-hidden">
                    <div class="flex items-center justify-between px-6 pt-6 pb-4">
                        <h3 class="card-title">{{ t('Hotel comparison') }}</h3>
                        <span class="text-xs text-muted">{{ periodLabel }} · {{ t('Change vs same period last year') }}</span>
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
                                <thead class="sticky top-0"><tr><th>{{ t('Date') }}</th><th class="!text-end">{{ t('Occupancy') }}</th><th class="!text-end">{{ t('ADR') }}</th><th class="!text-end">{{ t('RevPAR') }}</th><th class="!text-end">{{ t('Total revenue') }}</th></tr></thead>
                                <tbody>
                                    <tr v-for="p in [...trend].reverse()" :key="p.date">
                                        <td>{{ shortDate(p.date, locale) }}</td>
                                        <td class="text-end"><bdi>{{ pct(p.occupancy_pct) }}</bdi> <span class="text-xs text-muted"><bdi>{{ pct(p.ly_occupancy_pct) }}</bdi></span></td>
                                        <td class="text-end"><bdi>{{ money(p.adr) }}</bdi></td>
                                        <td class="text-end"><bdi>{{ money(p.revpar) }}</bdi></td>
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
