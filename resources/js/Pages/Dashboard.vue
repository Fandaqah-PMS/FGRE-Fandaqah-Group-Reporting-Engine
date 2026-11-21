<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import StatTile from '../Components/StatTile.vue';
import Delta from '../Components/Delta.vue';
import HotelBars from '../Components/Charts/HotelBars.vue';
import TrendChart from '../Components/Charts/TrendChart.vue';
import RevenueMix from '../Components/Charts/RevenueMix.vue';
import { compact, int, isNum, longDate, money, pct, shortDate } from '../lib/format';
import { useI18n } from '../lib/i18n';

const props = defineProps({
    empty: Boolean,
    filters: Object,
    currency: String,
    group: Object,
    groupLy: Object,
    hotels: Array,
    missing: Array,
    hotelCount: Number,
    trend: Array,
});

const { t, name, locale } = useI18n();
const page = usePage();
const groupName = computed(() => name(page.props.auth?.groups?.find((g) => g.id === page.props.auth.currentGroupId)));

const periods = computed(() => [
    { key: 'day', label: t('Day') },
    { key: 'mtd', label: t('Month to date') },
    { key: 'ytd', label: t('Year to date') },
]);

const loading = ref(false);
function apply(changes) {
    router.get('/', { ...props.filters, latest: undefined, ...changes }, {
        preserveScroll: true, preserveState: true,
        onStart: () => (loading.value = true), onFinish: () => (loading.value = false),
    });
}

// Metric switch shared by the hotel bars and the trend.
const metrics = computed(() => ({
    revpar: { label: t('RevPAR'), format: (v, axis) => (axis ? compact(v) : money(v)), kind: 'pct' },
    occupancy_pct: { label: t('Occupancy'), format: (v, axis) => (axis ? `${compact(v)}%` : pct(v)), kind: 'pp' },
    adr: { label: t('ADR'), format: (v, axis) => (axis ? compact(v) : money(v)), kind: 'pct' },
}));
const barMetric = ref('revpar');
const trendMetric = ref('occupancy_pct');

const barRows = computed(() => (props.hotels ?? []).map((h) => ({
    id: h.property_id, name: h.name, name_ar: h.name_ar,
    ty: h[barMetric.value], ly: h.ly?.[barMetric.value] ?? null,
})));

const mixRows = computed(() => (props.hotels ?? []).map((h) => ({
    id: h.property_id, name: h.name, name_ar: h.name_ar, rooms: h.room_revenue, fnb: h.fnb_revenue, other: h.other_revenue,
})));

const trendPoints = computed(() => (props.trend ?? []).map((p) => ({ date: p.date, ty: p[trendMetric.value], ly: p[`ly_${trendMetric.value}`] })));
const showTrendTable = ref(false);

const sort = ref({ key: 'revpar', dir: -1 });
const sortedHotels = computed(() => [...(props.hotels ?? [])].sort((a, b) => sort.value.dir * (Number(a[sort.value.key] ?? -1e12) - Number(b[sort.value.key] ?? -1e12))));
const sortBy = (key) => (sort.value = { key, dir: sort.value.key === key ? -sort.value.dir : -1 });
const sortMark = (key) => (sort.value.key === key ? (sort.value.dir < 0 ? '↓' : '↑') : '');
</script>

<template>
    <Head :title="t('Group dashboard')" />

    <div v-if="empty" class="card mx-auto mt-10 max-w-xl p-10 text-center">
        <h1 class="font-display text-2xl font-semibold">{{ t('No reports yet') }}</h1>
        <p class="mt-2 text-secondary">{{ t('Figures appear here as soon as the first Manager Flash report from one of your hotels is processed.') }}</p>
    </div>

    <template v-else>
        <!-- Title + filters (one row above everything they scope) -->
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl font-semibold text-navy">{{ groupName }}</h1>
                <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-secondary">
                    <span>{{ t('Business date') }}: <b class="font-semibold text-navy">{{ longDate(filters.date, locale) }}</b></span>
                    <span class="text-grey">·</span>
                    <span>{{ t(':n of :total hotels reported', { n: group.hotel_count, total: hotelCount }) }}</span>
                    <span v-if="group.is_provisional" class="rounded-full bg-coral-tint px-2 py-0.5 text-xs font-medium text-coral-text">{{ t('Includes provisional figures') }}</span>
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input type="date" :value="filters.date" :max="filters.latest" dir="ltr"
                       class="rounded-xl border border-line bg-white px-3 py-2 text-sm text-navy outline-none focus:border-coral focus:ring-2 focus:ring-coral/25"
                       :aria-label="t('Business date')" @change="apply({ date: $event.target.value })" />
                <button v-if="filters.date !== filters.latest" type="button" class="rounded-xl border border-line bg-white px-3 py-2 text-sm font-medium text-secondary hover:text-navy" @click="apply({ date: filters.latest })">
                    {{ t('Latest') }}
                </button>
                <div class="flex rounded-xl border border-line bg-white p-1" role="tablist" :aria-label="t('Period')">
                    <button v-for="p in periods" :key="p.key" type="button" role="tab" :aria-selected="filters.period === p.key"
                            class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                            :class="filters.period === p.key ? 'bg-navy text-white' : 'text-secondary hover:text-navy'"
                            @click="apply({ period: p.key })">{{ p.label }}</button>
                </div>
            </div>
        </div>

        <div v-if="missing.length" class="mt-4 rounded-xl border border-coral/25 bg-coral-tint px-4 py-3 text-sm text-coral-text">
            {{ t('No report received yet for this date from:') }}
            <b>{{ missing.map((m) => name(m)).join('، ') }}</b>
        </div>

        <div class="transition-opacity" :class="loading && 'opacity-60'">
            <!-- Headline tiles -->
            <section class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
                <StatTile :label="t('Occupancy')" :value="isNum(group.occupancy_pct) ? Number(group.occupancy_pct).toFixed(1) : '–'" unit="%"
                          :current="group.occupancy_pct" :previous="groupLy.occupancy_pct" kind="pp" :previous-label="pct(groupLy.occupancy_pct)" />
                <StatTile :label="t('ADR')" :value="money(group.adr)" :unit="currency"
                          :current="group.adr" :previous="groupLy.adr" :previous-label="money(groupLy.adr)" />
                <StatTile :label="t('RevPAR')" :value="money(group.revpar)" :unit="currency"
                          :current="group.revpar" :previous="groupLy.revpar" :previous-label="money(groupLy.revpar)" />
                <StatTile :label="t('Room revenue')" :value="compact(group.room_revenue)" :unit="currency"
                          :current="group.room_revenue" :previous="groupLy.room_revenue" :previous-label="compact(groupLy.room_revenue)" />
                <StatTile :label="t('Total revenue')" :value="compact(group.total_revenue)" :unit="currency" class="col-span-2 lg:col-span-1"
                          :current="group.total_revenue" :previous="groupLy.total_revenue" :previous-label="compact(groupLy.total_revenue)" />
            </section>
            <p class="mt-2 text-xs text-grey">{{ t('Occupancy, ADR and RevPAR exclude complimentary and house-use rooms and out-of-order inventory, the same way for every PMS.') }}</p>

            <!-- Charts -->
            <section class="mt-6 grid gap-4 lg:grid-cols-2">
                <div class="card p-5">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <h2 class="font-display text-lg font-semibold">{{ t(':metric by hotel', { metric: metrics[barMetric].label }) }}</h2>
                        <div class="flex rounded-lg bg-off-white p-0.5 text-xs">
                            <button v-for="(m, key) in metrics" :key="key" type="button" class="rounded-md px-2.5 py-1 font-medium"
                                    :class="barMetric === key ? 'bg-white text-navy shadow-sm' : 'text-secondary'" @click="barMetric = key">{{ m.label }}</button>
                        </div>
                    </div>
                    <HotelBars :rows="barRows" :format="(v) => metrics[barMetric].format(v)" :delta-kind="metrics[barMetric].kind" />
                </div>

                <div class="card p-5">
                    <h2 class="mb-4 font-display text-lg font-semibold">{{ t('Revenue mix') }}</h2>
                    <RevenueMix :rows="mixRows" />
                </div>
            </section>

            <section class="card mt-4 p-5">
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-display text-lg font-semibold">{{ t(':metric, last 30 days', { metric: metrics[trendMetric].label }) }}</h2>
                    <div class="flex items-center gap-2">
                        <div class="flex rounded-lg bg-off-white p-0.5 text-xs">
                            <button v-for="(m, key) in metrics" :key="key" type="button" class="rounded-md px-2.5 py-1 font-medium"
                                    :class="trendMetric === key ? 'bg-white text-navy shadow-sm' : 'text-secondary'" @click="trendMetric = key">{{ m.label }}</button>
                        </div>
                        <button type="button" class="text-xs font-medium text-secondary underline-offset-2 hover:underline" @click="showTrendTable = !showTrendTable">
                            {{ showTrendTable ? t('Show chart') : t('Show table') }}
                        </button>
                    </div>
                </div>
                <TrendChart v-if="!showTrendTable" :points="trendPoints" :format="metrics[trendMetric].format" />
                <div v-else class="max-h-72 overflow-auto">
                    <table class="w-full text-sm tabular">
                        <thead class="sticky top-0 bg-white text-xs text-secondary">
                            <tr><th class="py-2 text-start font-medium">{{ t('Date') }}</th><th class="text-end font-medium">{{ t('This year') }}</th><th class="text-end font-medium">{{ t('Last year') }}</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in [...trendPoints].reverse()" :key="p.date" class="border-t border-line">
                                <td class="py-1.5">{{ shortDate(p.date, locale) }}</td>
                                <td class="text-end"><bdi>{{ metrics[trendMetric].format(p.ty) }}</bdi></td>
                                <td class="text-end text-secondary"><bdi>{{ metrics[trendMetric].format(p.ly) }}</bdi></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Hotel comparison table -->
            <section class="card mt-4 overflow-hidden">
                <div class="flex items-center justify-between px-5 pt-5 pb-3">
                    <h2 class="font-display text-lg font-semibold">{{ t('Hotel comparison') }}</h2>
                    <span class="text-xs text-grey">{{ t('Change vs same period last year') }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[56rem] text-sm tabular">
                        <thead class="border-y border-line bg-off-white/60 text-xs text-secondary">
                            <tr>
                                <th class="px-5 py-2.5 text-start font-medium">{{ t('Hotel') }}</th>
                                <th class="px-3 text-start font-medium">{{ t('PMS') }}</th>
                                <th class="px-3 text-end font-medium">{{ t('Rooms sold') }}</th>
                                <th v-for="col in [['occupancy_pct', t('Occupancy')], ['adr', t('ADR')], ['revpar', t('RevPAR')], ['total_revenue', t('Total revenue')]]" :key="col[0]"
                                    class="cursor-pointer px-3 text-end font-medium select-none hover:text-navy" @click="sortBy(col[0])">
                                    {{ col[1] }} {{ sortMark(col[0]) }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="h in sortedHotels" :key="h.property_id" class="border-b border-line last:border-0 hover:bg-off-white/50">
                                <td class="px-5 py-3">
                                    <Link :href="`/hotels/${h.property_id}?date=${filters.date}`" class="font-medium text-navy hover:text-coral-text">{{ name(h) }}</Link>
                                    <span v-if="h.is_provisional" class="ms-2 rounded-full bg-coral-tint px-1.5 py-0.5 text-[10px] font-medium text-coral-text">{{ t('Provisional') }}</span>
                                </td>
                                <td class="px-3 text-secondary">{{ h.pms }}</td>
                                <td class="px-3 text-end"><bdi>{{ int(h.paid_occupied) }} <span class="text-grey">/ {{ int(h.sellable_rooms) }}</span></bdi></td>
                                <td class="px-3 text-end"><div><bdi>{{ pct(h.occupancy_pct) }}</bdi></div><Delta :current="h.occupancy_pct" :previous="h.ly?.occupancy_pct" kind="pp" /></td>
                                <td class="px-3 text-end"><div><bdi>{{ money(h.adr) }}</bdi></div><Delta :current="h.adr" :previous="h.ly?.adr" /></td>
                                <td class="px-3 text-end"><div><bdi>{{ money(h.revpar) }}</bdi></div><Delta :current="h.revpar" :previous="h.ly?.revpar" /></td>
                                <td class="px-3 text-end"><div><bdi>{{ money(h.total_revenue, 0) }}</bdi></div><Delta :current="h.total_revenue" :previous="h.ly?.total_revenue" /></td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t-2 border-navy/15 bg-off-white/60 font-semibold">
                            <tr>
                                <td class="px-5 py-3" colspan="2">{{ t('Group total') }}</td>
                                <td class="px-3 text-end"><bdi>{{ int(group.paid_occupied) }} <span class="font-normal text-grey">/ {{ int(group.sellable_rooms) }}</span></bdi></td>
                                <td class="px-3 text-end"><div><bdi>{{ pct(group.occupancy_pct) }}</bdi></div><Delta :current="group.occupancy_pct" :previous="groupLy.occupancy_pct" kind="pp" /></td>
                                <td class="px-3 text-end"><div><bdi>{{ money(group.adr) }}</bdi></div><Delta :current="group.adr" :previous="groupLy.adr" /></td>
                                <td class="px-3 text-end"><div><bdi>{{ money(group.revpar) }}</bdi></div><Delta :current="group.revpar" :previous="groupLy.revpar" /></td>
                                <td class="px-3 text-end"><div><bdi>{{ money(group.total_revenue, 0) }}</bdi></div><Delta :current="group.total_revenue" :previous="groupLy.total_revenue" /></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
        </div>
    </template>
</template>
