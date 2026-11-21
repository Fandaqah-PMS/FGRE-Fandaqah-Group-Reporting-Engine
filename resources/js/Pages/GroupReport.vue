<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PageHeader from '../Components/PageHeader.vue';
import ViewToggle from '../Components/ViewToggle.vue';
import Icon from '../Components/Icon.vue';
import ColumnChart from '../Components/Charts/ColumnChart.vue';
import Donut from '../Components/Charts/Donut.vue';
import Gauge from '../Components/Charts/Gauge.vue';
import HotelBars from '../Components/Charts/HotelBars.vue';
import { compact, int, isNum, longDate, money, pct } from '../lib/format';
import { BRAND, SERIES } from '../lib/palette';
import { useViewMode } from '../lib/view';
import { useI18n } from '../lib/i18n';

const props = defineProps({ empty: Boolean, date: String, latest: String, currency: String, columns: Object, hotels: Array, missing: Array, hotelCount: Number });
const { t, name, locale } = useI18n();
const page = usePage();
const view = useViewMode();

const scope = computed(() => page.props.scope ?? { current: null, hotels: [] });
const single = computed(() => scope.value.current !== null);
const period = ref('day');
const periods = computed(() => [{ key: 'day', label: t('Day') }, { key: 'mtd', label: t('Month to date') }, { key: 'ytd', label: t('Year to date') }]);
const periodLabel = computed(() => periods.value.find((p) => p.key === period.value)?.label);
const go = (date) => router.get('/group-report', { date }, { preserveScroll: true });

const ty = (h) => h.periods?.[period.value] ?? {};
const ly = (h) => h.periods?.[`${period.value}_ly`] ?? {};
const groupTy = computed(() => props.columns?.[period.value] ?? {});
const groupLy = computed(() => props.columns?.[`${period.value}_ly`] ?? {});

const money0 = (v) => money(v, 0);
const shareOf = (v, total) => (isNum(v) && Number(total) > 0 ? (Number(v) / Number(total)) * 100 : null);

/**
 * Sections and columns. code = short heading (full name in the tooltip and the column key);
 * better = which direction is good ('high' | 'low' | null), used for the best-in-group marker
 * and the colour of the change arrow.
 */
const sections = computed(() => [
    { key: 'rooms', title: t('Rooms'), cols: [
        { code: 'TRH', key: 'total_rooms', label: t('Rooms in hotel'), f: int },
        { code: 'OOO', key: 'ooo_rooms', label: t('Out of order'), f: int, better: 'low' },
        { code: 'OOS', key: 'oos_rooms', label: t('Out of service'), f: int, better: 'low' },
        { code: 'SR', key: 'sellable_rooms', label: t('Sellable rooms (rooms in hotel − out of order)'), f: int },
        { code: 'RO', key: 'rooms_occupied', label: t('Rooms occupied'), f: int, better: 'high' },
        { code: 'COMP', key: 'comp_rooms', label: t('Complimentary'), f: int },
        { code: 'HU', key: 'house_use_rooms', label: t('House use'), f: int },
        { code: 'DU', key: 'day_use_rooms', label: t('Day use'), f: int },
        { code: 'PRS', key: 'paid_occupied', label: t('Paid rooms sold (occupied − comp − house use)'), f: int, better: 'high' },
    ] },
    { key: 'kpi', title: t('Occupancy and rates'), cols: [
        { code: 'OCC%', key: 'occupancy_pct', label: t('Occupancy (paid rooms sold ÷ sellable rooms)'), f: pct, better: 'high', pp: true },
        { code: 'ADR', key: 'adr', label: t('Average daily rate (room revenue ÷ paid rooms sold)'), f: money, better: 'high' },
        { code: 'RevPAR', key: 'revpar', label: t('Revenue per available room (room revenue ÷ sellable rooms)'), f: money, better: 'high' },
        { code: 'TRevPAR', key: 'trevpar', label: t('Total revenue per available room'), f: money, better: 'high' },
        { code: 'RPI', key: 'rpi', label: t('RevPAR index: hotel RevPAR ÷ group RevPAR × 100'), f: (v) => (isNum(v) ? Number(v).toFixed(0) : '–'), better: 'high', computed: 'rpi' },
    ] },
    { key: 'revenue', title: t('Revenue'), note: props.currency, cols: [
        { code: 'RRV', key: 'room_revenue', label: t('Room revenue'), f: money0, better: 'high' },
        { code: 'FBR', key: 'fnb_revenue', label: t('Food and beverage revenue'), f: money0, better: 'high' },
        { code: 'ORV', key: 'other_revenue', label: t('Other revenue'), f: money0, better: 'high' },
        { code: 'TRV', key: 'total_revenue', label: t('Total revenue'), f: money0, better: 'high' },
        { code: 'SHR%', key: 'share', label: t('Share of group total revenue'), f: pct, computed: 'share', pp: true },
    ] },
    { key: 'guests', title: t('Guests and activity'), cols: [
        { code: 'IH', key: 'persons_in_house', label: t('Guests in house'), f: int, better: 'high' },
        { code: 'ARR', key: 'arrival_rooms', label: t('Arrival rooms'), f: int },
        { code: 'DEP', key: 'departure_rooms', label: t('Departure rooms'), f: int },
        { code: 'NS', key: 'no_show_rooms', label: t('No-show rooms'), f: int, better: 'low' },
        { code: 'CXL', key: 'cancellations', label: t('Cancellations'), f: int, better: 'low' },
    ] },
]);

/** Value of a column for a hotel (or the group) in this period / last year. */
function cell(col, row, which) {
    const own = which === 'ty' ? (row === 'group' ? groupTy.value : ty(row)) : (row === 'group' ? groupLy.value : ly(row));
    const grp = which === 'ty' ? groupTy.value : groupLy.value;
    if (col.computed === 'share') return row === 'group' ? (isNum(grp.total_revenue) ? 100 : null) : shareOf(own.total_revenue, grp.total_revenue);
    if (col.computed === 'rpi') return row === 'group' ? (isNum(grp.revpar) ? 100 : null) : shareOf(own.revpar, grp.revpar);
    return own[col.key] ?? null;
}

const rows = computed(() => props.hotels ?? []);
const stats = computed(() => {
    const out = {};
    for (const s of sections.value) for (const c of s.cols) {
        const vals = rows.value.map((h) => cell(c, h, 'ty')).filter(isNum).map(Number);
        const tied = vals.length < 2 || Math.max(...vals) === Math.min(...vals);
        out[c.code] = { max: Math.max(0, ...vals), best: !c.better || tied ? null : c.better === 'high' ? Math.max(...vals) : Math.min(...vals) };
    }
    return out;
});
const barWidth = (col, v) => (isNum(v) && stats.value[col.code].max > 0 ? `${Math.max(2, (Number(v) / stats.value[col.code].max) * 100)}%` : '0');
const isBest = (col, v) => isNum(v) && stats.value[col.code].best !== null && Number(v) === stats.value[col.code].best;
function trend(col, a, b) {
    if (!isNum(a) || !isNum(b) || Number(a) === Number(b)) return null;
    const up = Number(a) > Number(b);
    const good = col.better ? (up === (col.better === 'high')) : null;
    return { up, cls: good === null ? 'text-muted' : good ? 'text-success' : 'text-coral-dark' };
}

// Charts
const bars = (fn) => rows.value.map((h) => ({ id: h.id, name: h.name, name_ar: h.name_ar, ...fn(h) }));
const kpiCards = computed(() => [
    { title: t('Occupancy by hotel'), key: 'occupancy_pct', f: pct, kind: 'pp' },
    { title: t('ADR by hotel'), key: 'adr', f: (v) => money(v), kind: 'pct' },
    { title: t('RevPAR by hotel'), key: 'revpar', f: (v) => money(v), kind: 'pct' },
    { title: t('Total revenue by hotel'), key: 'total_revenue', f: money0, kind: 'pct', perHotel: true },
]);
const rpiRows = computed(() => bars((h) => ({ ty: shareOf(ty(h).revpar, groupTy.value.revpar), ly: shareOf(ly(h).revpar, groupLy.value.revpar) })));
const shareSegments = computed(() => {
    const list = [...rows.value].sort((a, b) => Number(ty(b).total_revenue ?? 0) - Number(ty(a).total_revenue ?? 0));
    const head = list.slice(0, 4).map((h, i) => ({ key: h.id, label: name(h), value: Number(ty(h).total_revenue ?? 0), color: SERIES[i] }));
    const rest = list.slice(4).reduce((s, h) => s + Number(ty(h).total_revenue ?? 0), 0);
    return rest > 0 ? [...head, { key: 'other', label: t('Other hotels'), value: rest, color: SERIES[4] }] : head;
});
const hotelCats = computed(() => rows.value.map((h) => ({ key: String(h.id), label: name(h).split(' ').slice(0, 2).join(' '), tooltip: name(h) })));
const moveValues = computed(() => Object.fromEntries(rows.value.map((h) => [String(h.id), { arr: ty(h).arrival_rooms, dep: ty(h).departure_rooms, ih: ty(h).persons_in_house }])));
const roomValues = computed(() => Object.fromEntries(rows.value.map((h) => [String(h.id), { sold: ty(h).paid_occupied, sell: ty(h).sellable_rooms }])));
</script>

<template>
    <Head :title="t('Group report')" />

    <PageHeader :eyebrow="t('Reports / Group report')" :title="t('Group report')">
        <template #subtitle>
            <Icon name="layers" :size="15" class="text-coral" />
            <b class="text-navy">{{ t('Hotels side by side') }}</b>
            <template v-if="!empty"><span class="text-muted">·</span>{{ longDate(date, locale) }}</template>
        </template>
        <template #actions>
            <input v-if="!empty" type="date" :value="date" :max="latest" dir="ltr" class="field !w-auto !py-2 text-sm font-semibold" :aria-label="t('Business date')" @change="go($event.target.value)" />
            <div class="seg" role="tablist" :aria-label="t('Period')">
                <button v-for="p in periods" :key="p.key" type="button" role="tab" :aria-selected="period === p.key" @click="period = p.key">{{ p.label }}</button>
            </div>
            <ViewToggle />
        </template>
    </PageHeader>

    <div v-if="single" class="mt-5 flex items-center gap-3 rounded-2xl border border-line bg-white px-4 py-3 text-sm text-ink-2">
        <Icon name="building" :size="18" class="text-coral" />
        {{ t('One hotel is selected. Choose “All hotels” in the hotel switch to compare every hotel of the group.') }}
    </div>

    <div v-if="empty || !columns?.day" class="card mt-6 p-10 text-center text-ink-2">{{ t('No Manager Flash for this date.') }}</div>

    <div v-else class="mt-6 space-y-8">
        <div v-if="missing.length" class="flex items-center gap-3 rounded-2xl border border-coral/25 bg-coral-tint px-4 py-3 text-sm text-coral-dark">
            <Icon name="alert" :size="18" />{{ t('No report received yet for this date from:') }} <b>{{ missing.map((m) => name(m)).join('، ') }}</b>
        </div>

        <!-- TABLES: one table per section, hotels as rows -->
        <template v-if="view === 'tables'">
            <section v-for="s in sections" :key="s.key">
                <div class="mb-2 flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <h2 class="text-lg font-bold text-navy">{{ s.title }}</h2>
                        <p class="mt-0.5 flex flex-wrap items-center gap-x-3 text-xs text-ink-2">
                            <span class="flex items-center gap-1.5"><span class="size-2 rounded-sm bg-navy" />{{ t('First figure: :period', { period: periodLabel }) }}</span>
                            <span class="flex items-center gap-1.5"><span class="size-2 rounded-sm bg-muted" />{{ t('Second figure: same period last year') }}</span>
                            <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-coral" />{{ t('Best in the group') }}</span>
                        </p>
                    </div>
                    <span v-if="s.note" class="card-sub">{{ s.note }}</span>
                </div>
                <div class="card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm tabular" :style="{ minWidth: `${13 + s.cols.length * 6.2}rem` }">
                            <thead>
                                <tr class="bg-navy text-white">
                                    <th class="sticky start-0 z-10 bg-navy px-4 py-3 text-start text-xs font-bold">{{ t('Hotel') }}</th>
                                    <th v-for="c in s.cols" :key="c.code" class="px-3 py-3 text-end text-xs font-bold whitespace-nowrap">
                                        <abbr :title="c.label" class="cursor-help no-underline decoration-white/50 decoration-dotted underline-offset-4 hover:underline" dir="ltr">{{ c.code }}</abbr>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(h, i) in rows" :key="h.id" class="border-t border-line" :class="i % 2 ? 'bg-paper/50' : 'bg-white'">
                                    <td class="sticky start-0 z-[1] px-4 py-2.5 align-top" :class="i % 2 ? 'bg-[#FBFAF8]' : 'bg-white'">
                                        <Link :href="`/hotels/${h.id}?date=${date}`" class="font-bold text-navy hover:text-coral">{{ name(h) }}</Link>
                                        <span v-if="ty(h).is_provisional" class="ms-1 text-coral" :title="t('Provisional')">*</span>
                                        <div class="text-[11px] text-muted">{{ h.pms }}</div>
                                    </td>
                                    <td v-for="c in s.cols" :key="c.code" class="px-3 py-2.5 text-end align-top">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <span v-if="isBest(c, cell(c, h, 'ty'))" class="size-1.5 rounded-full bg-coral" :title="t('Best in the group')" />
                                            <bdi class="font-bold text-navy">{{ c.f(cell(c, h, 'ty')) }}</bdi>
                                        </div>
                                        <div class="flex items-center justify-end gap-1 text-xs text-muted">
                                            <span v-if="trend(c, cell(c, h, 'ty'), cell(c, h, 'ly'))" :class="trend(c, cell(c, h, 'ty'), cell(c, h, 'ly')).cls" class="text-[9px]">
                                                {{ trend(c, cell(c, h, 'ty'), cell(c, h, 'ly')).up ? '▲' : '▼' }}
                                            </span>
                                            <bdi>{{ c.f(cell(c, h, 'ly')) }}</bdi>
                                        </div>
                                        <div class="ms-auto mt-1.5 h-1 w-full max-w-24 overflow-hidden rounded-full bg-neutral" aria-hidden="true">
                                            <div class="ms-auto h-full rounded-full bg-navy/70" :style="{ width: barWidth(c, cell(c, h, 'ty')) }" />
                                        </div>
                                    </td>
                                </tr>
                                <tr v-for="m in missing" :key="`m${m.id}`" class="border-t border-line bg-white">
                                    <td class="sticky start-0 bg-white px-4 py-2.5 font-bold text-navy">{{ name(m) }}</td>
                                    <td :colspan="s.cols.length" class="px-3 py-2.5 text-center text-xs text-coral-dark">{{ t('No report for this date') }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="border-t-2 border-navy/20 bg-coral-tint/40">
                                    <td class="sticky start-0 bg-[#FEF5F4] px-4 py-3 font-extrabold text-navy">{{ t('Group total') }}</td>
                                    <td v-for="c in s.cols" :key="c.code" class="px-3 py-3 text-end">
                                        <bdi class="block font-extrabold text-navy">{{ c.f(cell(c, 'group', 'ty')) }}</bdi>
                                        <bdi class="block text-xs text-muted">{{ c.f(cell(c, 'group', 'ly')) }}</bdi>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <details class="mt-2 text-xs text-ink-2">
                    <summary class="cursor-pointer font-semibold text-coral select-none">{{ t('Column key') }}</summary>
                    <dl class="mt-2 grid gap-x-6 gap-y-1 rounded-xl bg-white p-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div v-for="c in s.cols" :key="c.code" class="flex gap-2"><dt class="w-16 shrink-0 font-bold text-navy" dir="ltr">{{ c.code }}</dt><dd>{{ c.label }}</dd></div>
                    </dl>
                </details>
            </section>
        </template>

        <!-- CHARTS -->
        <template v-else>
            <section class="card rise p-6">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <h3 class="card-title">{{ t('Occupancy by hotel') }}</h3>
                    <span class="card-sub">{{ periodLabel }}</span>
                </div>
                <div class="mt-5 grid grid-cols-[repeat(auto-fill,minmax(12.5rem,1fr))] gap-4">
                    <div class="rounded-2xl bg-navy px-4 pt-4 pb-4 text-white">
                        <div class="mb-3 truncate text-sm font-bold">{{ t('Group') }}</div>
                        <div class="rounded-xl bg-white px-2 pt-3 pb-2"><Gauge compact :value="groupTy.occupancy_pct" :previous="groupLy.occupancy_pct" /></div>
                    </div>
                    <div v-for="h in rows" :key="h.id" class="rounded-2xl border border-line bg-white px-4 pt-4 pb-4">
                        <Link :href="`/hotels/${h.id}?date=${date}`" class="mb-3 block truncate text-sm font-bold text-navy hover:text-coral" :title="name(h)">{{ name(h) }}</Link>
                        <div class="px-2 pt-3 pb-2"><Gauge compact :value="ty(h).occupancy_pct" :previous="ly(h).occupancy_pct" /></div>
                    </div>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-2">
                <div v-for="k in kpiCards" :key="k.key" class="card rise p-6">
                    <div class="mb-4 flex items-start justify-between gap-2">
                        <h3 class="card-title">{{ k.title }}</h3>
                        <span class="card-sub">{{ periodLabel }}</span>
                    </div>
                    <HotelBars :rows="bars((h) => ({ ty: ty(h)[k.key], ly: ly(h)[k.key] }))" :format="k.f" :delta-kind="k.kind" :reference="k.perHotel ? (groupTy.hotel_count ? Number(groupTy[k.key]) / groupTy.hotel_count : null) : groupTy[k.key]"
                               :reference-label="k.perHotel ? t('Hotel average') : t('Group')" />
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-3">
                <div class="card rise p-6 xl:col-span-2">
                    <div class="mb-1 flex items-start justify-between gap-2">
                        <h3 class="card-title">{{ t('RevPAR index') }}</h3>
                        <span class="card-sub">{{ t('Group = 100') }}</span>
                    </div>
                    <p class="mb-4 text-xs text-ink-2">{{ t('Above 100: the hotel earns more per available room than the group as a whole.') }}</p>
                    <HotelBars :rows="rpiRows" :format="(v) => (isNum(v) ? Number(v).toFixed(0) : '–')" :reference="100" :reference-label="t('Group')" />
                </div>
                <div class="card rise p-6">
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="card-title">{{ t('Share of group revenue') }}</h3>
                        <span class="card-sub">{{ periodLabel }} · {{ currency }}</span>
                    </div>
                    <div class="mt-5"><Donut :segments="shareSegments" :format="(v) => compact(v)" :center-label="t('Total revenue')" /></div>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-2">
                <div class="card rise p-6">
                    <div class="mb-4 flex items-start justify-between gap-2">
                        <h3 class="card-title">{{ t('Paid rooms sold vs sellable rooms') }}</h3>
                        <span class="card-sub">{{ periodLabel }}</span>
                    </div>
                    <ColumnChart :categories="hotelCats" :series="[{ key: 'sold', label: t('Paid rooms sold'), color: BRAND.navy }, { key: 'sell', label: t('Sellable rooms'), color: BRAND.lightGrey }]"
                                 :values="roomValues" :format="(v, axis) => (axis ? compact(v) : int(v))" />
                </div>
                <div class="card rise p-6">
                    <div class="mb-4 flex items-start justify-between gap-2">
                        <h3 class="card-title">{{ t('Guests and activity') }}</h3>
                        <span class="card-sub">{{ periodLabel }}</span>
                    </div>
                    <ColumnChart :categories="hotelCats" :series="[{ key: 'arr', label: t('Arrivals'), color: BRAND.coral }, { key: 'dep', label: t('Departures'), color: BRAND.navy }, { key: 'ih', label: t('Guests in house'), color: BRAND.muted }]"
                                 :values="moveValues" :format="(v, axis) => (axis ? compact(v) : int(v))" />
                </div>
            </section>
        </template>
    </div>
</template>
