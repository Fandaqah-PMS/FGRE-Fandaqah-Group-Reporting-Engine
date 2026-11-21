<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PageHeader from '../Components/PageHeader.vue';
import ViewToggle from '../Components/ViewToggle.vue';
import KpiCard from '../Components/KpiCard.vue';
import Delta from '../Components/Delta.vue';
import Icon from '../Components/Icon.vue';
import ColumnChart from '../Components/Charts/ColumnChart.vue';
import Donut from '../Components/Charts/Donut.vue';
import HotelBars from '../Components/Charts/HotelBars.vue';
import SegmentBar from '../Components/Charts/SegmentBar.vue';
import Gauge from '../Components/Charts/Gauge.vue';
import { compact, int, isNum, longDate, money, pct } from '../lib/format';
import { BRAND, LAST_YEAR, MIX, THIS_YEAR } from '../lib/palette';
import { useViewMode } from '../lib/view';
import { useI18n } from '../lib/i18n';

const props = defineProps({ empty: Boolean, date: String, latest: String, currency: String, columns: Object, hotels: Array, missing: Array });
const { t, name, locale } = useI18n();
const page = usePage();
const view = useViewMode();

const scope = computed(() => page.props.scope ?? { current: null, hotels: [] });
const scopeHotel = computed(() => scope.value.hotels.find((h) => h.id === scope.value.current) ?? null);
const multi = computed(() => (props.hotels?.length ?? 0) > 1);
const col = (k) => props.columns?.[k] ?? {};
const day = computed(() => col('day'));
const dayLy = computed(() => col('day_ly'));
const provisional = computed(() => Object.values(props.columns ?? {}).some((c) => c?.is_provisional));

const go = (date) => router.get('/manager-flash', { date }, { preserveScroll: true });

// Day / MTD / YTD × this year / last year for one KPI.
const periodCats = computed(() => [{ key: 'day', label: t('Day') }, { key: 'mtd', label: t('MTD') }, { key: 'ytd', label: t('YTD') }]);
const yearSeries = computed(() => [{ key: 'ty', label: t('This year'), color: THIS_YEAR }, { key: 'ly', label: t('Last year'), color: LAST_YEAR }]);
const byPeriod = (metric) => Object.fromEntries(['day', 'mtd', 'ytd'].map((p) => [p, { ty: col(p)[metric], ly: col(`${p}_ly`)[metric] }]));
const kpiCharts = computed(() => [
    { metric: 'occupancy_pct', label: t('Occupancy'), format: (v, axis) => (axis ? `${compact(v)}%` : pct(v)) },
    { metric: 'adr', label: t('ADR'), format: (v, axis) => (axis ? compact(v) : money(v)) },
    { metric: 'revpar', label: t('RevPAR'), format: (v, axis) => (axis ? compact(v) : money(v)) },
]);

const mixPeriod = ref('mtd');
const mix = computed(() => [
    { key: 'rooms', label: t('Rooms'), value: col(mixPeriod.value).room_revenue, color: MIX.rooms },
    { key: 'fnb', label: t('Food & beverage'), value: col(mixPeriod.value).fnb_revenue, color: MIX.fnb },
    { key: 'other', label: t('Other'), value: col(mixPeriod.value).other_revenue, color: MIX.other },
]);

const roomParts = computed(() => {
    const g = day.value;
    const ooo = Number(g.ooo_rooms ?? 0);
    return [
        { key: 'sold', label: t('Sold'), value: g.paid_occupied, color: BRAND.navy },
        { key: 'comp', label: t('Comp & house use'), value: Number(g.comp_rooms ?? 0) + Number(g.house_use_rooms ?? 0), color: BRAND.peach },
        { key: 'vacant', label: t('Vacant'), value: Math.max(0, Number(g.total_rooms ?? 0) - ooo - Number(g.rooms_occupied ?? 0)), color: BRAND.neutral },
        { key: 'ooo', label: t('Out of order'), value: ooo, color: BRAND.muted },
    ];
});

const activity = computed(() => [
    { icon: 'login', label: t('Arrivals'), value: day.value.arrival_rooms, ly: dayLy.value.arrival_rooms },
    { icon: 'logout', label: t('Departures'), value: day.value.departure_rooms, ly: dayLy.value.departure_rooms },
    { icon: 'users', label: t('Guests in house'), value: day.value.persons_in_house, ly: dayLy.value.persons_in_house },
    { icon: 'alert', label: t('No-shows'), value: day.value.no_show_rooms, ly: dayLy.value.no_show_rooms },
]);

const hotelMetric = ref('total_revenue');
const hotelPeriod = ref('mtd');
const hotelRows = computed(() => (props.hotels ?? []).map((h) => ({
    id: h.id, name: h.name, name_ar: h.name_ar,
    ty: h.periods[hotelPeriod.value]?.[hotelMetric.value] ?? null,
    ly: h.periods[`${hotelPeriod.value}_ly`]?.[hotelMetric.value] ?? null,
})));
const hotelFormat = (v) => (hotelMetric.value === 'occupancy_pct' ? pct(v) : hotelMetric.value === 'total_revenue' ? money(v, 0) : money(v));

// The full Manager Flash (table view).
const flashRows = computed(() => [
    { section: t('Rooms') },
    { key: 'total_rooms', label: t('Rooms in hotel'), f: int },
    { key: 'ooo_rooms', label: t('Out of order'), f: int },
    { key: 'oos_rooms', label: t('Out of service'), f: int },
    { key: 'rooms_occupied', label: t('Rooms occupied'), f: int },
    { key: 'comp_rooms', label: t('Complimentary'), f: int },
    { key: 'house_use_rooms', label: t('House use'), f: int },
    { key: 'day_use_rooms', label: t('Day use'), f: int },
    { section: t('Key figures') },
    { key: 'occupancy_pct', label: t('Occupancy'), f: pct, strong: true },
    { key: 'adr', label: t('ADR'), f: (v) => money(v), strong: true },
    { key: 'revpar', label: t('RevPAR'), f: (v) => money(v), strong: true },
    { key: 'trevpar', label: t('TRevPAR'), f: (v) => money(v) },
    { section: t('Revenue') },
    { key: 'room_revenue', label: t('Room revenue'), f: (v) => money(v, 0) },
    { key: 'fnb_revenue', label: t('Food & beverage'), f: (v) => money(v, 0) },
    { key: 'other_revenue', label: t('Other revenue'), f: (v) => money(v, 0) },
    { key: 'total_revenue', label: t('Total revenue'), f: (v) => money(v, 0), strong: true },
    { section: t('Guests and activity') },
    { key: 'persons_in_house', label: t('Guests in house'), f: int },
    { key: 'arrival_rooms', label: t('Arrivals'), f: int },
    { key: 'departure_rooms', label: t('Departures'), f: int },
    { key: 'no_show_rooms', label: t('No-shows'), f: int },
    { key: 'cancellations', label: t('Cancellations'), f: int },
]);
const flashCols = computed(() => [['day', t('Day')], ['mtd', t('MTD')], ['ytd', t('YTD')], ['day_ly', t('Day LY')], ['mtd_ly', t('MTD LY')], ['ytd_ly', t('YTD LY')]]);
</script>

<template>
    <Head :title="t('Manager Flash')" />

    <PageHeader :eyebrow="t('Reports / Manager Flash')" :title="t('Manager Flash')">
        <template #subtitle>
            <Icon :name="scopeHotel ? 'building' : 'layers'" :size="15" class="text-coral" />
            <b class="text-navy">{{ scopeHotel ? name(scopeHotel) : t('All hotels, consolidated') }}</b>
            <template v-if="!empty"><span class="text-muted">·</span>{{ longDate(date, locale) }}</template>
            <span v-if="provisional" class="chip bg-coral-tint text-coral-dark">{{ t('Provisional') }}</span>
        </template>
        <template #actions>
            <input v-if="!empty" type="date" :value="date" :max="latest" dir="ltr" class="field !w-auto !py-2 text-sm font-semibold" :aria-label="t('Business date')" @change="go($event.target.value)" />
            <ViewToggle />
        </template>
    </PageHeader>

    <div v-if="empty || !columns?.day" class="card mt-6 p-10 text-center text-ink-2">{{ t('No Manager Flash for this date.') }}</div>

    <div v-else class="mt-6 space-y-6">
        <div v-if="multi && missing.length" class="flex items-center gap-3 rounded-2xl border border-coral/25 bg-coral-tint px-4 py-3 text-sm text-coral-dark">
            <Icon name="alert" :size="18" />{{ t('No report received yet for this date from:') }} <b>{{ missing.map((m) => name(m)).join('، ') }}</b>
        </div>

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <KpiCard icon="bed" :label="t('Occupancy · day')" :value="isNum(day.occupancy_pct) ? Number(day.occupancy_pct).toFixed(1) : '–'" unit="%"
                     :current="day.occupancy_pct" :previous="dayLy.occupancy_pct" kind="pp" :previous-label="pct(dayLy.occupancy_pct)" />
            <KpiCard icon="tag" :label="t('ADR · day')" :value="money(day.adr)" :unit="currency" :current="day.adr" :previous="dayLy.adr" :previous-label="money(dayLy.adr)" />
            <KpiCard icon="trend" :label="t('RevPAR · day')" :value="money(day.revpar)" :unit="currency" :current="day.revpar" :previous="dayLy.revpar" :previous-label="money(dayLy.revpar)" />
            <KpiCard icon="wallet" :label="t('Total revenue · day')" :value="compact(day.total_revenue)" :unit="currency" :current="day.total_revenue" :previous="dayLy.total_revenue" :previous-label="compact(dayLy.total_revenue)" />
        </section>

        <template v-if="view === 'charts'">
            <section class="grid gap-6 lg:grid-cols-3">
                <div v-for="k in kpiCharts" :key="k.metric" class="card rise p-6">
                    <h3 class="card-title">{{ k.label }}</h3>
                    <p class="mb-3 text-xs text-muted">{{ t('Day, month and year to date vs last year') }}</p>
                    <ColumnChart :categories="periodCats" :series="yearSeries" :values="byPeriod(k.metric)" :format="k.format" :height="220" />
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-3">
                <div class="card rise p-6">
                    <div class="mb-5 flex items-center justify-between gap-3">
                        <h3 class="card-title">{{ t('Revenue mix') }}</h3>
                        <div class="seg">
                            <button v-for="p in periodCats" :key="p.key" type="button" :aria-pressed="mixPeriod === p.key" @click="mixPeriod = p.key">{{ p.label }}</button>
                        </div>
                    </div>
                    <Donut :segments="mix" :format="(v) => compact(v)" :center-label="t('Total revenue')" :size="170" />
                </div>
                <div class="card rise p-6">
                    <h3 class="card-title">{{ t('Room nights today') }}</h3>
                    <p class="text-xs text-muted">{{ t('Day') }}</p>
                    <div class="my-4"><Gauge :value="day.occupancy_pct" :previous="dayLy.occupancy_pct" :label="t(':sold of :total rooms sold', { sold: int(day.paid_occupied), total: int(day.sellable_rooms) })" /></div>
                    <SegmentBar :parts="roomParts" />
                </div>
                <div class="card rise p-6">
                    <h3 class="card-title">{{ t('Guests and activity') }}</h3>
                    <p class="mb-4 text-xs text-muted">{{ t('Day') }} · {{ t('vs last year') }}</p>
                    <ul class="grid grid-cols-2 gap-3">
                        <li v-for="a in activity" :key="a.label" class="rounded-2xl bg-paper p-4">
                            <span class="grid size-8 place-items-center rounded-lg bg-white text-coral shadow-sm"><Icon :name="a.icon" :size="16" /></span>
                            <div class="mt-3 text-2xl font-extrabold text-navy tabular" dir="ltr">{{ int(a.value) }}</div>
                            <div class="mt-0.5 text-xs font-semibold text-ink-2">{{ a.label }}</div>
                            <div class="mt-1.5"><Delta :current="a.value" :previous="a.ly" /></div>
                        </li>
                    </ul>
                </div>
            </section>

            <section v-if="multi" class="card rise p-6">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <h3 class="card-title">{{ t('Hotels side by side') }}</h3>
                    <div class="flex flex-wrap gap-2">
                        <div class="seg">
                            <button v-for="m in [['total_revenue', t('Total revenue')], ['revpar', t('RevPAR')], ['occupancy_pct', t('Occupancy')], ['adr', t('ADR')]]" :key="m[0]" type="button"
                                    :aria-pressed="hotelMetric === m[0]" @click="hotelMetric = m[0]">{{ m[1] }}</button>
                        </div>
                        <div class="seg">
                            <button v-for="p in periodCats" :key="p.key" type="button" :aria-pressed="hotelPeriod === p.key" @click="hotelPeriod = p.key">{{ p.label }}</button>
                        </div>
                    </div>
                </div>
                <HotelBars :rows="hotelRows" :format="hotelFormat" :delta-kind="hotelMetric === 'occupancy_pct' ? 'pp' : 'pct'" />
            </section>
        </template>

        <template v-else>
            <section class="card overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-2 px-6 pt-6 pb-4">
                    <h3 class="card-title">{{ t('Manager Flash') }} · {{ scopeHotel ? name(scopeHotel) : t('All hotels, consolidated') }}</h3>
                    <span class="text-xs text-muted">{{ currency }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="tbl min-w-[52rem] tabular">
                        <thead><tr>
                            <th />
                            <th v-for="[k, label] in flashCols" :key="k" class="!text-end" :class="k === 'day_ly' && 'border-s border-line'">{{ label }}</th>
                        </tr></thead>
                        <tbody>
                            <template v-for="(r, i) in flashRows" :key="i">
                                <tr v-if="r.section"><td :colspan="7" class="!bg-paper !py-2 text-[11px] font-bold tracking-wider text-coral uppercase">{{ r.section }}</td></tr>
                                <tr v-else :class="r.strong && 'font-bold'">
                                    <td>{{ r.label }}</td>
                                    <td v-for="[k] in flashCols" :key="k" class="text-end" :class="[k.endsWith('_ly') && 'text-ink-2', k === 'day_ly' && 'border-s border-line']">
                                        <bdi>{{ r.f(columns[k]?.[r.key]) }}</bdi>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <p class="px-6 py-4 text-xs text-muted">{{ t('Occupancy, ADR and RevPAR exclude complimentary and house-use rooms and out-of-order inventory, the same way for every PMS.') }}</p>
            </section>

            <section v-if="multi" class="card overflow-hidden">
                <h3 class="card-title px-6 pt-6 pb-4">{{ t('By hotel') }}</h3>
                <div class="overflow-x-auto">
                    <table class="tbl min-w-[60rem] tabular">
                        <thead><tr>
                            <th>{{ t('Hotel') }}</th>
                            <th v-for="p in periodCats" :key="p.key" class="!text-end" colspan="1">{{ t('Occupancy') }} · {{ p.label }}</th>
                            <th v-for="p in periodCats" :key="`r${p.key}`" class="!text-end">{{ t('Revenue') }} · {{ p.label }}</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="h in hotels" :key="h.id">
                                <td><Link :href="`/hotels/${h.id}?date=${date}`" class="font-bold text-navy hover:text-coral">{{ name(h) }}</Link><div class="text-xs text-muted">{{ h.pms }}</div></td>
                                <td v-for="p in periodCats" :key="p.key" class="text-end"><bdi>{{ pct(h.periods[p.key]?.occupancy_pct) }}</bdi>
                                    <div><Delta :current="h.periods[p.key]?.occupancy_pct" :previous="h.periods[`${p.key}_ly`]?.occupancy_pct" kind="pp" /></div></td>
                                <td v-for="p in periodCats" :key="`r${p.key}`" class="text-end"><bdi>{{ money(h.periods[p.key]?.total_revenue, 0) }}</bdi>
                                    <div><Delta :current="h.periods[p.key]?.total_revenue" :previous="h.periods[`${p.key}_ly`]?.total_revenue" /></div></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </template>
    </div>
</template>
