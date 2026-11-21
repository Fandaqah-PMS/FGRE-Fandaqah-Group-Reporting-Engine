<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import PageHeader from '../Components/PageHeader.vue';
import KpiCard from '../Components/KpiCard.vue';
import Icon from '../Components/Icon.vue';
import ColumnChart from '../Components/Charts/ColumnChart.vue';
import { compact, isNum, money, monthLabel, pct } from '../lib/format';
import { BRAND } from '../lib/palette';
import { useI18n } from '../lib/i18n';

const props = defineProps({ year: Number, hotels: Array, cells: Array, canManage: Boolean, importErrors: Array });
const { t, name, locale } = useI18n();
const page = usePage();
const form = useForm({ file: null });
const fileInput = ref(null);

const months = computed(() => Array.from({ length: 12 }, (_, i) => `${props.year}-${String(i + 1).padStart(2, '0')}`));
const daysIn = (ym) => new Date(Number(ym.slice(0, 4)), Number(ym.slice(5, 7)), 0).getDate();
const cell = (hotelId, ym) => props.cells.find((c) => c.property_id === hotelId && c.month === ym);
const occ = (h, c) => (c && h.total_rooms ? (c.rooms_sold / (h.total_rooms * daysIn(c.month))) * 100 : null);

const totals = computed(() => {
    let revenue = 0, room = 0, sold = 0, avail = 0;
    for (const c of props.cells) {
        const h = props.hotels.find((x) => x.id === c.property_id);
        revenue += c.total_revenue; room += c.room_revenue; sold += c.rooms_sold;
        avail += (h?.total_rooms ?? 0) * daysIn(c.month);
    }
    return { revenue, occ: avail ? (sold / avail) * 100 : null, adr: sold ? room / sold : null, revpar: avail ? room / avail : null };
});
const chartCats = computed(() => months.value.map((m) => ({ key: m, label: monthLabel(m, locale.value), tooltip: monthLabel(m, locale.value, true) })));
const chartValues = computed(() => Object.fromEntries(months.value.map((m) => [m, {
    rev: props.cells.filter((c) => c.month === m).reduce((s, c) => s + c.total_revenue, 0) || null,
}])));
const hotelYear = (h) => props.cells.filter((c) => c.property_id === h.id).reduce((s, c) => s + c.total_revenue, 0);

const pick = () => fileInput.value?.click();
const upload = (e) => {
    form.file = e.target.files[0];
    if (form.file) form.post('/budgets/import', { forceFormData: true, preserveScroll: true, onFinish: () => (e.target.value = '') });
};
</script>

<template>
    <Head :title="t('Budgets')" />

    <PageHeader :eyebrow="t('Planning / Budgets')" :title="t('Budgets')" :subtitle="t('Monthly targets per hotel. Reports compare actual figures with budget to date.')">
        <template #actions>
            <select :value="year" dir="ltr" class="field !w-auto !py-2 text-sm font-semibold" :aria-label="t('Year')" @change="router.get('/budgets', { year: $event.target.value })">
                <option v-for="y in [year - 1, year, year + 1]" :key="y" :value="y">{{ y }}</option>
            </select>
            <template v-if="canManage">
                <a :href="`/budgets/template?year=${year}`" class="flex items-center gap-2 rounded-xl border border-line bg-white px-3 py-2 text-[13px] font-semibold text-navy hover:border-coral/40">
                    <Icon name="table" :size="15" class="text-coral" />{{ t('Download template') }}
                </a>
                <button type="button" class="btn-coral !py-2 text-[13px]" :disabled="form.processing" @click="pick"><Icon name="inbox" :size="15" />{{ t('Upload budget') }}</button>
                <input ref="fileInput" type="file" accept=".csv,text/csv" class="hidden" @change="upload" />
            </template>
        </template>
    </PageHeader>

    <div v-if="importErrors?.length" class="mt-5 rounded-2xl border border-coral/30 bg-coral-tint px-5 py-4 text-sm text-coral-dark">
        <div class="mb-2 flex items-center gap-2 font-bold"><Icon name="alert" :size="16" />{{ t('Nothing was saved. Please fix these rows and upload again:') }}</div>
        <ul class="list-disc space-y-0.5 ps-5"><li v-for="(e, i) in importErrors.slice(0, 30)" :key="i">{{ e }}</li></ul>
    </div>

    <div v-if="!cells.length" class="card mt-6 p-10 text-center">
        <div class="brand-pattern-full mx-auto mb-5 h-20 w-40 rounded-2xl opacity-70" />
        <h2 class="text-lg font-bold">{{ t('No budget for :year yet', { year }) }}</h2>
        <p class="mt-1 text-sm text-ink-2">{{ t('Download the template, fill in rooms sold and revenue for each hotel and month, then upload it.') }}</p>
    </div>

    <div v-else class="mt-6 space-y-6">
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <KpiCard :dot="BRAND.coral" :label="t('Budget revenue :year', { year })" :value="compact(totals.revenue)" unit="SAR" />
            <KpiCard :dot="BRAND.navy" :label="t('Budget occupancy')" :value="isNum(totals.occ) ? totals.occ.toFixed(1) : '–'" unit="%" />
            <KpiCard :dot="BRAND.green" :label="t('Budget ADR')" :value="money(totals.adr)" unit="SAR" />
            <KpiCard :dot="BRAND.peach" :label="t('Budget RevPAR')" :value="money(totals.revpar)" unit="SAR" />
        </section>

        <section class="card rise p-6">
            <h3 class="card-title mb-4">{{ t('Group budget by month') }}</h3>
            <ColumnChart :categories="chartCats" :series="[{ key: 'rev', label: t('Total revenue'), color: BRAND.navy }]" :values="chartValues"
                         :format="(v, axis) => (axis ? compact(v) : money(v, 0))" :value-labels="true" :show-legend="false" :height="240" />
        </section>

        <section class="card overflow-hidden">
            <div class="px-6 pt-6 pb-4">
                <h3 class="card-title">{{ t('Budget by hotel and month') }}</h3>
                <p class="card-sub">{{ t('Total revenue, with budget occupancy underneath') }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[72rem] text-sm tabular">
                    <thead><tr class="bg-navy text-white">
                        <th class="sticky start-0 bg-navy px-4 py-3 text-start text-xs font-bold">{{ t('Hotel') }}</th>
                        <th v-for="m in months" :key="m" class="px-2 py-3 text-end text-xs font-bold">{{ monthLabel(m, locale) }}</th>
                        <th class="bg-coral px-3 py-3 text-end text-xs font-bold">{{ year }}</th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="h in hotels" :key="h.id" class="border-t border-line">
                            <td class="sticky start-0 bg-white px-4 py-2.5 font-bold text-navy">{{ name(h) }}<div class="text-[11px] font-normal text-muted" dir="ltr">{{ h.code }}</div></td>
                            <td v-for="m in months" :key="m" class="px-2 py-2.5 text-end">
                                <template v-if="cell(h.id, m)">
                                    <bdi class="block font-semibold text-navy">{{ compact(cell(h.id, m).total_revenue) }}</bdi>
                                    <bdi class="block text-[11px] text-muted">{{ pct(occ(h, cell(h.id, m))) }}</bdi>
                                </template>
                                <span v-else class="text-muted">–</span>
                            </td>
                            <td class="bg-coral-tint/50 px-3 text-end font-extrabold text-navy"><bdi>{{ compact(hotelYear(h)) }}</bdi></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
