<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import ColumnChart from '../Charts/ColumnChart.vue';
import Donut from '../Charts/Donut.vue';
import Gauge from '../Charts/Gauge.vue';
import HotelBars from '../Charts/HotelBars.vue';
import { compact, int, isNum, money, pct } from '../../lib/format';
import { BRAND, SERIES } from '../../lib/palette';
import { useI18n } from '../../lib/i18n';

/** Hotels compared in charts for one period: occupancy gauges, ranked KPIs vs the group, RevPAR index, revenue share. */
const props = defineProps({
    hotels: { type: Array, required: true },   // [{ id, name, name_ar, ty: {...}, base: {...} }]
    group: { type: Object, required: true },
    groupBase: { type: Object, default: () => ({}) },
    periodLabel: { type: String, required: true },
    basisLabel: { type: String, required: true },
    currency: { type: String, default: 'SAR' },
    date: { type: String, default: '' },
});
const { t, name } = useI18n();
const money0 = (v) => money(v, 0);
const shareOf = (v, total) => (isNum(v) && Number(total) > 0 ? (Number(v) / Number(total)) * 100 : null);
const bars = (key) => props.hotels.map((h) => ({ id: h.id, name: h.name, name_ar: h.name_ar, ty: h.ty?.[key] ?? null, ly: h.base?.[key] ?? null }));
const kpis = computed(() => [
    { title: t('Occupancy'), key: 'occupancy_pct', f: pct, kind: 'pp' },
    { title: t('ADR'), key: 'adr', f: (v) => money(v), kind: 'pct' },
    { title: t('RevPAR'), key: 'revpar', f: (v) => money(v), kind: 'pct' },
    { title: t('Total revenue'), key: 'total_revenue', f: money0, kind: 'pct', perHotel: true },
]);
const rpi = computed(() => props.hotels.map((h) => ({ id: h.id, name: h.name, name_ar: h.name_ar, ty: shareOf(h.ty?.revpar, props.group.revpar), ly: shareOf(h.base?.revpar, props.groupBase?.revpar) })));
const share = computed(() => {
    const list = [...props.hotels].sort((a, b) => Number(b.ty?.total_revenue ?? 0) - Number(a.ty?.total_revenue ?? 0));
    const head = list.slice(0, 4).map((h, i) => ({ key: h.id, label: name(h), value: Number(h.ty?.total_revenue ?? 0), color: SERIES[i] }));
    const rest = list.slice(4).reduce((s, h) => s + Number(h.ty?.total_revenue ?? 0), 0);
    return rest > 0 ? [...head, { key: 'o', label: t('Other hotels'), value: rest, color: SERIES[4] }] : head;
});
const cats = computed(() => props.hotels.map((h) => ({ key: String(h.id), label: name(h).split(' ').slice(0, 2).join(' '), tooltip: name(h) })));
const roomValues = computed(() => Object.fromEntries(props.hotels.map((h) => [String(h.id), { sold: h.ty?.paid_occupied, avail: h.ty?.total_rooms }])));
</script>

<template>
    <div class="space-y-6">
        <section class="card rise p-6">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <h3 class="card-title">{{ t('Occupancy by hotel') }}</h3>
                <span class="card-sub">{{ periodLabel }} · {{ t('vs :basis', { basis: basisLabel }) }}</span>
            </div>
            <div class="mt-5 grid grid-cols-[repeat(auto-fill,minmax(12.5rem,1fr))] gap-4">
                <div class="rounded-2xl bg-navy p-4 text-white">
                    <div class="mb-3 truncate text-sm font-bold">{{ t('Group') }}</div>
                    <div class="rounded-xl bg-white px-2 pt-3 pb-2"><Gauge compact :value="group.occupancy_pct" :previous="groupBase?.occupancy_pct" /></div>
                </div>
                <div v-for="h in hotels" :key="h.id" class="rounded-2xl border border-line bg-white p-4">
                    <Link :href="`/hotels/${h.id}?date=${date}`" class="mb-3 block truncate text-sm font-bold text-navy hover:text-coral" :title="name(h)">{{ name(h) }}</Link>
                    <div class="px-2 pt-3 pb-2"><Gauge compact :value="h.ty?.occupancy_pct" :previous="h.base?.occupancy_pct" /></div>
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <div v-for="k in kpis" :key="k.key" class="card rise p-6">
                <div class="mb-4 flex items-start justify-between gap-2">
                    <h3 class="card-title">{{ t(':metric by hotel', { metric: k.title }) }}</h3>
                    <span class="card-sub">{{ periodLabel }}</span>
                </div>
                <HotelBars :rows="bars(k.key)" :format="k.f" :delta-kind="k.kind"
                           :reference="k.perHotel ? (group.hotel_count ? Number(group[k.key]) / group.hotel_count : null) : group[k.key]"
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
                <HotelBars :rows="rpi" :format="(v) => (isNum(v) ? Number(v).toFixed(0) : '–')" :reference="100" :reference-label="t('Group')" />
            </div>
            <div class="card rise p-6">
                <div class="flex items-start justify-between gap-2">
                    <h3 class="card-title">{{ t('Share of group revenue') }}</h3>
                    <span class="card-sub">{{ periodLabel }} · {{ currency }}</span>
                </div>
                <div class="mt-5"><Donut :segments="share" :format="(v) => compact(v)" :center-label="t('Total revenue')" /></div>
            </div>
        </section>

        <section class="card rise p-6">
            <div class="mb-4 flex items-start justify-between gap-2">
                <h3 class="card-title">{{ t('Rooms sold vs rooms available') }}</h3>
                <span class="card-sub">{{ periodLabel }}</span>
            </div>
            <ColumnChart :categories="cats" :series="[{ key: 'sold', label: t('Rooms sold'), color: BRAND.navy }, { key: 'avail', label: t('Rooms available'), color: BRAND.lightGrey }]"
                         :values="roomValues" :format="(v, axis) => (axis ? compact(v) : int(v))" />
        </section>
    </div>
</template>
