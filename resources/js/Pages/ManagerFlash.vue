<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PageHeader from '../Components/PageHeader.vue';
import SubscribeButton from '../Components/Subscriptions/SubscribeButton.vue';
import ViewToggle from '../Components/ViewToggle.vue';
import KpiCard from '../Components/KpiCard.vue';
import ReportToolbar from '../Components/Report/ReportToolbar.vue';
import CompareMatrix from '../Components/Report/CompareMatrix.vue';
import HotelFocus from '../Components/Report/HotelFocus.vue';
import GroupCharts from '../Components/Report/GroupCharts.vue';
import { managerFlashSections } from '../lib/reports';
import { downloadCsv, printPage } from '../lib/export';
import Delta from '../Components/Delta.vue';
import Icon from '../Components/Icon.vue';
import ColumnChart from '../Components/Charts/ColumnChart.vue';
import Donut from '../Components/Charts/Donut.vue';
import HotelBars from '../Components/Charts/HotelBars.vue';
import SegmentBar from '../Components/Charts/SegmentBar.vue';
import Gauge from '../Components/Charts/Gauge.vue';
import { compact, int, isNum, longDate, money, pct } from '../lib/format';
import { BRAND, LAST_YEAR_BAR, MIX, THIS_YEAR } from '../lib/palette';
import { useViewMode } from '../lib/view';
import { useI18n } from '../lib/i18n';

const props = defineProps({ empty: Boolean, date: String, latest: String, currency: String, columns: Object, hotels: Array, missing: Array, regions: Array, budget: Object, hotelCount: Number, selection: Array });
const { t, name, locale } = useI18n();
const page = usePage();
const view = useViewMode();

// ---- Views: total (group as one) | compare (side by side) | focus (hotel vs group) ----
const query = computed(() => Object.fromEntries(new URLSearchParams(page.url.split('?')[1] ?? '')));
const mode = computed(() => (['total', 'compare', 'focus'].includes(query.value.view) && (props.hotels?.length ?? 0) > 1 ? query.value.view : 'total'));
const basis = computed(() => (query.value.basis === 'budget' ? 'budget' : 'ly'));
const basisLabel = computed(() => (basis.value === 'budget' ? t('Budget') : t('Last year')));
const cmpPeriod = computed(() => (['day', 'mtd', 'ytd'].includes(query.value.period) ? query.value.period : 'day'));
const focusId = computed(() => Number(query.value.focus) || props.hotels?.[0]?.id);
function navigate(changes) {
    const params = { ...query.value, date: props.date, ...changes };
    Object.keys(params).forEach((k) => (params[k] === null || params[k] === undefined || params[k] === '') && delete params[k]);
    router.get('/manager-flash', params, { preserveScroll: true, preserveState: true });
}
const sections = computed(() => managerFlashSections(t));
const toolbarHotels = computed(() => (page.props.scope?.hotels ?? []).map((h) => ({ ...h, region: (props.hotels ?? []).find((x) => x.id === h.id)?.region ?? null })));

const hotelById = (id) => (props.hotels ?? []).find((h) => h.id === id);
const regionByKey = (k) => (props.regions ?? []).find((r) => r.key === k);
const periodData = (col, p) => (col.kind === 'group' ? props.columns?.[p] : col.kind === 'region' ? regionByKey(col.id)?.periods?.[p] : hotelById(col.id)?.periods?.[p]) ?? {};
const budgetData = (col, p) => (col.kind === 'group' ? props.budget?.group?.[p] : col.kind === 'region' ? props.budget?.regions?.[col.id]?.[p] : props.budget?.hotels?.[col.id]?.[p]) ?? {};
const baseData = (col, p) => (basis.value === 'budget' ? budgetData(col, p) : periodData(col, `${p}_ly`));
const cmpValue = (r, col) => periodData(col, cmpPeriod.value)[r.key] ?? null;
const cmpBase = (r, col) => baseData(col, cmpPeriod.value)[r.key] ?? null;

const compareColumns = computed(() => [
    ...(props.hotels ?? []).map((h) => ({ id: h.id, kind: 'hotel', label: name(h), sub: h.pms, href: `/hotels/${h.id}?date=${props.date}` })),
    ...(props.regions ?? []).filter((r) => r.hotelIds.length > 1).map((r) => ({ id: r.key, kind: 'region', label: t(r.key), sub: t(':n hotels', { n: r.hotelIds.length }) })),
    { id: 'group', kind: 'group', label: t('Group'), sub: t(':n hotels', { n: props.columns?.[cmpPeriod.value]?.hotel_count ?? 0 }) },
]);
const cmpPeriodLabel = computed(() => ({ day: t('Day'), mtd: t('Month to date'), ytd: t('Year to date') })[cmpPeriod.value]);

function focusGet(r) {
    const hotel = { id: focusId.value, kind: 'hotel' };
    const group = props.columns?.[cmpPeriod.value] ?? {};
    const groupVal = r.agg === 'sum' && isNum(group[r.key]) && group.hotel_count ? Number(group[r.key]) / group.hotel_count : group[r.key] ?? null;
    let best = null;
    if (r.better) {
        for (const h of props.hotels ?? []) {
            const v = h.periods?.[cmpPeriod.value]?.[r.key];
            if (isNum(v) && (best === null || (r.better === 'high' ? Number(v) > best.value : Number(v) < best.value))) best = { value: Number(v), name: name(h) };
        }
    }
    return { value: cmpValue(r, hotel), group: groupVal, best, base: cmpBase(r, hotel) };
}

const chartHotels = computed(() => (props.hotels ?? []).map((h) => ({ id: h.id, name: h.name, name_ar: h.name_ar, ty: h.periods?.[cmpPeriod.value], base: baseData({ id: h.id, kind: 'hotel' }, cmpPeriod.value) })));

function exportCsv() {
    const rowsOf = () => sections.value.flatMap((s) => s.rows);
    if (mode.value === 'compare') {
        const cols = compareColumns.value;
        downloadCsv(`manager-flash-compare-${cmpPeriod.value}-${props.date}.csv`, [
            [t('Code'), t('Measure'), ...cols.flatMap((c) => [c.label, `${c.label} (${basisLabel.value})`])],
            ...rowsOf().map((r) => [r.code, r.label, ...cols.flatMap((c) => [cmpValue(r, c), cmpBase(r, c)])]),
        ]);
    } else if (mode.value === 'focus') {
        const h = hotelById(focusId.value);
        downloadCsv(`manager-flash-${h?.name ?? 'hotel'}-${props.date}.csv`, [
            [t('Code'), t('Measure'), name(h), t('Group'), t('Best hotel'), basisLabel.value],
            ...rowsOf().map((r) => { const d = focusGet(r); return [r.code, r.label, d.value, d.group, d.best?.value ?? '', d.base]; }),
        ]);
    } else {
        const keys = ['day', 'mtd', 'ytd', 'day_ly', 'mtd_ly', 'ytd_ly'];
        downloadCsv(`manager-flash-${props.date}.csv`, [
            [t('Code'), t('Measure'), t('Day'), t('MTD'), t('YTD'), t('Day LY'), t('MTD LY'), t('YTD LY')],
            ...rowsOf().map((r) => [r.code, r.label, ...keys.map((k) => props.columns?.[k]?.[r.key] ?? '')]),
        ]);
    }
}

const scope = computed(() => page.props.scope ?? { current: null, hotels: [] });
const scopeHotel = computed(() => scope.value.hotels.find((h) => h.id === scope.value.current) ?? null);
const multi = computed(() => (props.hotels?.length ?? 0) > 1);
const col = (k) => props.columns?.[k] ?? {};
const day = computed(() => col('day'));
const dayLy = computed(() => col('day_ly'));
const baseDay = computed(() => (basis.value === 'budget' ? props.budget?.group?.day ?? {} : dayLy.value));
const provisional = computed(() => Object.values(props.columns ?? {}).some((c) => c?.is_provisional));

const go = (date) => router.get('/manager-flash', { date }, { preserveScroll: true });

// Day / MTD / YTD × this year / last year for one KPI.
const periodCats = computed(() => [{ key: 'day', label: t('Day') }, { key: 'mtd', label: t('MTD') }, { key: 'ytd', label: t('YTD') }]);
const yearSeries = computed(() => [{ key: 'ty', label: t('This year'), color: THIS_YEAR }, { key: 'ly', label: t('Last year'), color: LAST_YEAR_BAR }]);
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
        { key: 'comp', label: t('Comp & house use'), value: Number(g.comp_rooms ?? 0) + Number(g.house_use_rooms ?? 0), color: BRAND.coral },
        { key: 'vacant', label: t('Vacant'), value: Math.max(0, Number(g.total_rooms ?? 0) - ooo - Number(g.rooms_occupied ?? 0)), color: BRAND.lightGrey },
        { key: 'ooo', label: t('Out of order'), value: ooo, color: BRAND.green },
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
            <div v-if="mode !== 'total'" class="seg" role="tablist" :aria-label="t('Period')">
                <button v-for="p in [['day', t('Day')], ['mtd', t('Month to date')], ['ytd', t('Year to date')]]" :key="p[0]" type="button" role="tab"
                        :aria-selected="cmpPeriod === p[0]" @click="navigate({ period: p[0] })">{{ p[1] }}</button>
            </div>
            <ViewToggle v-if="mode !== 'focus'" />
            <SubscribeButton :hotels="mode === 'compare' && selection?.length ? selection : null" />
        </template>
    </PageHeader>

    <ReportToolbar v-if="!empty && columns?.day" class="mt-5" :view="mode" :basis="basis" :hotels="toolbarHotels" :selection="selection" :focus="focusId"
                   :views="(hotels?.length ?? 0) > 1 ? ['total', 'compare', 'focus'] : ['total']" :can-export="!!page.props.auth?.can?.['export reports']"
                   @change="navigate" @export="exportCsv" @print="printPage" />

    <div v-if="!empty && columns?.day && mode === 'compare'" class="mt-6">
        <CompareMatrix v-if="view === 'tables'" :sections="sections" :columns="compareColumns" :value="cmpValue" :base="cmpBase" :basis-label="basisLabel" />
        <GroupCharts v-else :hotels="chartHotels" :group="columns[cmpPeriod]" :group-base="baseData({ kind: 'group' }, cmpPeriod)" :period-label="cmpPeriodLabel"
                     :basis-label="basisLabel" :currency="currency" :date="date" />
    </div>

    <div v-if="!empty && columns?.day && mode === 'focus'" class="mt-6">
        <HotelFocus :sections="sections" :hotel-name="name(hotelById(focusId))" :get="focusGet" :basis-label="basisLabel" />
    </div>

    <div v-if="empty || !columns?.day" class="card mt-6 p-10 text-center text-ink-2">{{ t('No Manager Flash for this date.') }}</div>

    <div v-else-if="mode === 'total'" class="mt-6 space-y-6">
        <div v-if="multi && missing.length" class="flex items-center gap-3 rounded-2xl border border-coral/25 bg-coral-tint px-4 py-3 text-sm text-coral-dark">
            <Icon name="alert" :size="18" />{{ t('No report received yet for this date from:') }} <b>{{ missing.map((m) => name(m)).join('، ') }}</b>
        </div>

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <KpiCard :dot="BRAND.coral" :label="t('Occupancy · day')" :value="isNum(day.occupancy_pct) ? Number(day.occupancy_pct).toFixed(1) : '–'" unit="%"
                     :current="day.occupancy_pct" :previous="baseDay.occupancy_pct ?? null" kind="pp" :note="`${basisLabel} ${pct(baseDay.occupancy_pct)}`" />
            <KpiCard :dot="BRAND.navy" :label="t('ADR · day')" :value="money(day.adr)" :unit="currency" :current="day.adr" :previous="baseDay.adr ?? null" :note="`${basisLabel} ${money(baseDay.adr)}`" />
            <KpiCard :dot="BRAND.green" :label="t('RevPAR · day')" :value="money(day.revpar)" :unit="currency" :current="day.revpar" :previous="baseDay.revpar ?? null" :note="`${basisLabel} ${money(baseDay.revpar)}`" />
            <KpiCard :dot="BRAND.peach" :label="t('Total revenue · day')" :value="compact(day.total_revenue)" :unit="currency" :current="day.total_revenue" :previous="baseDay.total_revenue ?? null" :note="`${basisLabel} ${compact(baseDay.total_revenue)}`" />
        </section>

        <template v-if="view === 'charts'">
            <section class="grid gap-6 lg:grid-cols-3">
                <div v-for="k in kpiCharts" :key="k.metric" class="card rise p-6">
                    <h3 class="card-title">{{ k.label }}</h3>
                    <p class="mb-3 text-xs text-muted">{{ t('Day, month and year to date vs last year') }}</p>
                    <ColumnChart :categories="periodCats" :series="yearSeries" :values="byPeriod(k.metric)" :format="k.format" :height="230" />
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
                    <Donut :segments="mix" :format="(v) => compact(v)" :center-label="t('Total revenue')" />
                </div>
                <div class="card rise p-6">
                    <h3 class="card-title">{{ t('Room nights today') }}</h3>
                    <p class="text-xs text-muted">{{ t('Day') }}</p>
                    <div class="mt-4"><SegmentBar :parts="roomParts" /></div>
                    <div class="mt-6"><Gauge :value="day.occupancy_pct" :previous="dayLy.occupancy_pct" :label="t(':sold of :total rooms sold', { sold: int(day.paid_occupied), total: int(day.sellable_rooms) })" /></div>
                </div>
                <div class="card rise p-6">
                    <h3 class="card-title">{{ t('Guests and activity') }}</h3>
                    <p class="mb-4 text-xs text-muted">{{ t('Day') }} · {{ t('vs last year') }}</p>
                    <ul class="grid grid-cols-2 gap-3">
                        <li v-for="a in activity" :key="a.label" class="rounded-2xl bg-paper p-4">
                            <span class="grid size-8 place-items-center rounded-lg bg-white text-coral shadow-sm"><Icon :name="a.icon" :size="16" /></span>
                            <div class="mt-3 text-xl font-semibold text-navy tabular" dir="ltr">{{ int(a.value) }}</div>
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
