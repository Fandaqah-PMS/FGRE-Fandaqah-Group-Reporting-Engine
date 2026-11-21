<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import StatTile from '../Components/StatTile.vue';
import TrendChart from '../Components/Charts/TrendChart.vue';
import StatusBadge from '../Components/StatusBadge.vue';
import { compact, int, isNum, longDate, money, pct } from '../lib/format';
import { useI18n } from '../lib/i18n';

const props = defineProps({ hotel: Object, date: String, columns: Object, trend: Array, documents: Array });
const { t, name, locale } = useI18n();

const day = computed(() => props.columns?.day ?? {});
const dayLy = computed(() => props.columns?.day_ly ?? {});

const metrics = computed(() => ({
    occupancy_pct: { label: t('Occupancy'), format: (v, axis) => (axis ? `${compact(v)}%` : pct(v)) },
    adr: { label: t('ADR'), format: (v, axis) => (axis ? compact(v) : money(v)) },
    revpar: { label: t('RevPAR'), format: (v, axis) => (axis ? compact(v) : money(v)) },
}));
const trendMetric = ref('occupancy_pct');
const points = computed(() => (props.trend ?? []).map((p) => ({ date: p.date, ty: p[trendMetric.value], ly: p[`ly_${trendMetric.value}`] })));

const rows = computed(() => [
    ['total_rooms', t('Rooms in hotel'), int],
    ['ooo_rooms', t('Out of order'), int],
    ['rooms_occupied', t('Rooms occupied'), int],
    ['comp_rooms', t('Complimentary'), int],
    ['house_use_rooms', t('House use'), int],
    ['occupancy_pct', t('Occupancy'), pct],
    ['adr', t('ADR'), (v) => money(v)],
    ['revpar', t('RevPAR'), (v) => money(v)],
    ['room_revenue', t('Room revenue'), (v) => money(v, 0)],
    ['fnb_revenue', t('F&B revenue'), (v) => money(v, 0)],
    ['other_revenue', t('Other revenue'), (v) => money(v, 0)],
    ['total_revenue', t('Total revenue'), (v) => money(v, 0)],
    ['persons_in_house', t('Guests in house'), int],
    ['arrival_rooms', t('Arrivals'), int],
    ['departure_rooms', t('Departures'), int],
    ['no_show_rooms', t('No-shows'), int],
]);
const cols = computed(() => [
    ['day', t('Day')], ['mtd', t('MTD')], ['ytd', t('YTD')],
    ['day_ly', t('Day LY')], ['mtd_ly', t('MTD LY')], ['ytd_ly', t('YTD LY')],
]);
</script>

<template>
    <Head :title="name(hotel)" />

    <Link href="/" class="text-sm font-medium text-secondary hover:text-navy">← {{ t('Group dashboard') }}</Link>

    <div class="mt-3 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl font-semibold">{{ name(hotel) }}</h1>
            <p class="mt-1 text-sm text-secondary">{{ hotel.pms }} · {{ t(':n rooms', { n: hotel.total_rooms ?? '–' }) }} · {{ longDate(date, locale) }}
                <span v-if="day.is_provisional" class="ms-1 rounded-full bg-coral-tint px-2 py-0.5 text-xs font-medium text-coral-text">{{ t('Provisional') }}</span>
            </p>
        </div>
        <input type="date" :value="date" dir="ltr" class="rounded-xl border border-line bg-white px-3 py-2 text-sm outline-none focus:border-coral focus:ring-2 focus:ring-coral/25"
               :aria-label="t('Business date')" @change="router.get(`/hotels/${hotel.id}`, { date: $event.target.value }, { preserveScroll: true })" />
    </div>

    <div v-if="!columns || !columns.day" class="card mt-6 p-8 text-center text-secondary">{{ t('No Manager Flash for this date.') }}</div>

    <template v-else>
        <section class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <StatTile :label="t('Occupancy')" :value="isNum(day.occupancy_pct) ? Number(day.occupancy_pct).toFixed(1) : '–'" unit="%"
                      :current="day.occupancy_pct" :previous="dayLy.occupancy_pct" kind="pp" :previous-label="pct(dayLy.occupancy_pct)" />
            <StatTile :label="t('ADR')" :value="money(day.adr)" :unit="hotel.currency" :current="day.adr" :previous="dayLy.adr" :previous-label="money(dayLy.adr)" />
            <StatTile :label="t('RevPAR')" :value="money(day.revpar)" :unit="hotel.currency" :current="day.revpar" :previous="dayLy.revpar" :previous-label="money(dayLy.revpar)" />
            <StatTile :label="t('Total revenue')" :value="compact(day.total_revenue)" :unit="hotel.currency" :current="day.total_revenue" :previous="dayLy.total_revenue" :previous-label="compact(dayLy.total_revenue)" />
        </section>

        <section class="card mt-4 p-5">
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-display text-lg font-semibold">{{ t(':metric, last 60 days', { metric: metrics[trendMetric].label }) }}</h2>
                <div class="flex rounded-lg bg-off-white p-0.5 text-xs">
                    <button v-for="(m, key) in metrics" :key="key" type="button" class="rounded-md px-2.5 py-1 font-medium"
                            :class="trendMetric === key ? 'bg-white text-navy shadow-sm' : 'text-secondary'" @click="trendMetric = key">{{ m.label }}</button>
                </div>
            </div>
            <TrendChart :points="points" :format="metrics[trendMetric].format" />
        </section>

        <section class="card mt-4 overflow-hidden">
            <h2 class="px-5 pt-5 pb-3 font-display text-lg font-semibold">{{ t('Manager Flash') }}</h2>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[48rem] text-sm tabular">
                    <thead class="border-y border-line bg-off-white/60 text-xs text-secondary">
                        <tr>
                            <th class="px-5 py-2.5 text-start font-medium" />
                            <th v-for="[key, label] in cols" :key="key" class="px-3 text-end font-medium" :class="key === 'day_ly' && 'border-s border-line'">{{ label }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="[key, label, fmt] in rows" :key="key" class="border-b border-line last:border-0"
                            :class="['occupancy_pct', 'adr', 'revpar', 'total_revenue'].includes(key) && 'font-semibold'">
                            <td class="px-5 py-2">{{ label }}</td>
                            <td v-for="[col] in cols" :key="col" class="px-3 text-end"
                                :class="[col.endsWith('_ly') && 'text-secondary', col === 'day_ly' && 'border-s border-line']"><bdi>{{ fmt(columns[col]?.[key]) }}</bdi></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="px-5 py-3 text-xs text-grey">{{ t('Occupancy, ADR and RevPAR exclude complimentary and house-use rooms and out-of-order inventory, the same way for every PMS.') }}</p>
        </section>
    </template>

    <section v-if="documents.length" class="card mt-4 p-5">
        <h2 class="mb-3 font-display text-lg font-semibold">{{ t('Latest reports received') }}</h2>
        <ul class="divide-y divide-line text-sm">
            <li v-for="d in documents" :key="d.id" class="flex flex-wrap items-center justify-between gap-2 py-2">
                <span>{{ t(d.type) }} · <span dir="ltr" class="tabular">{{ d.date ?? '–' }}</span></span>
                <StatusBadge :status="d.status" />
            </li>
        </ul>
    </section>
</template>
