<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import Sparkline from './Charts/Sparkline.vue';
import { compact, isNum, money, pct } from '../lib/format';
import { useI18n } from '../lib/i18n';

/**
 * League table of hotels: ranked by the measure the owner picks, month or year to date,
 * with change vs last year, revenue vs budget, and a 30-day RevPAR trend line.
 */
const props = defineProps({ rows: { type: Array, required: true }, date: String });
const { t, name } = useI18n();

const period = ref('mtd');
const by = ref('revpar');
const growth = (r, key) => (isNum(r[period.value]?.[key]) && Number(r[`${period.value}_ly`]?.[key]) > 0 ? (Number(r[period.value][key]) / Number(r[`${period.value}_ly`][key]) - 1) * 100 : null);
const budgetPct = (r) => (Number(r.budget_mtd?.total_revenue) > 0 && isNum(r.mtd?.total_revenue) ? (Number(r.mtd.total_revenue) / Number(r.budget_mtd.total_revenue)) * 100 : null);
const measures = computed(() => ({
    revpar: { label: t('RevPAR'), get: (r) => r[period.value]?.revpar },
    occupancy_pct: { label: t('Occupancy'), get: (r) => r[period.value]?.occupancy_pct },
    adr: { label: t('ADR'), get: (r) => r[period.value]?.adr },
    growth: { label: t('Revenue growth'), get: (r) => growth(r, 'total_revenue') },
    budget: { label: t('Budget achieved'), get: (r) => budgetPct(r) },
}));
const ranked = computed(() => [...props.rows].sort((a, b) => Number(measures.value[by.value].get(b) ?? -1e12) - Number(measures.value[by.value].get(a) ?? -1e12)));
const medal = (i) => ['bg-coral text-white', 'bg-navy text-white', 'bg-peach text-navy'][i] ?? 'bg-neutral text-ink-2';
const sign = (v, unit = '%') => (isNum(v) ? `${v >= 0 ? '+' : '−'}${Math.abs(v).toFixed(1)}${unit}` : '–');
</script>

<template>
    <section class="card rise overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 px-6 pt-6 pb-4">
            <div>
                <h3 class="card-title">{{ t('Hotel ranking') }}</h3>
                <p class="card-sub">{{ t('Ranked by :m', { m: measures[by].label }) }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <div class="seg">
                    <button v-for="(m, k) in measures" :key="k" type="button" :aria-pressed="by === k" @click="by = k">{{ m.label }}</button>
                </div>
                <div class="seg">
                    <button type="button" :aria-pressed="period === 'mtd'" @click="period = 'mtd'">{{ t('MTD') }}</button>
                    <button type="button" :aria-pressed="period === 'ytd'" @click="period = 'ytd'">{{ t('YTD') }}</button>
                </div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="tbl min-w-[60rem] tabular">
                <thead><tr>
                    <th class="w-14">#</th><th>{{ t('Hotel') }}</th>
                    <th class="!text-end">{{ t('Occupancy') }}</th><th class="!text-end">{{ t('ADR') }}</th><th class="!text-end">{{ t('RevPAR') }}</th>
                    <th class="!text-end">{{ t('Total revenue') }}</th><th class="!text-end">{{ t('vs last year') }}</th>
                    <th class="!text-end">{{ t('Budget achieved') }} <span class="normal-case">(MTD)</span></th><th class="w-40">{{ t('RevPAR, 30 days') }}</th>
                </tr></thead>
                <tbody>
                    <tr v-for="(r, i) in ranked" :key="r.id">
                        <td><span class="grid size-8 place-items-center rounded-lg text-sm font-bold" :class="medal(i)">{{ i + 1 }}</span></td>
                        <td><Link :href="`/hotels/${r.id}?date=${date}`" class="font-bold text-navy hover:text-coral">{{ name(r) }}</Link><div class="text-xs text-muted">{{ r.pms }}</div></td>
                        <td class="text-end"><bdi :class="by === 'occupancy_pct' && 'font-semibold text-navy'">{{ pct(r[period]?.occupancy_pct) }}</bdi></td>
                        <td class="text-end"><bdi :class="by === 'adr' && 'font-semibold text-navy'">{{ money(r[period]?.adr) }}</bdi></td>
                        <td class="text-end"><bdi :class="by === 'revpar' && 'font-semibold text-navy'">{{ money(r[period]?.revpar) }}</bdi></td>
                        <td class="text-end"><bdi>{{ compact(r[period]?.total_revenue) }}</bdi></td>
                        <td class="text-end">
                            <bdi class="chip !px-2 !py-0.5" :class="growth(r, 'total_revenue') >= 0 ? 'bg-success-tint text-success' : 'bg-coral-tint text-coral-dark'">{{ sign(growth(r, 'total_revenue')) }}</bdi>
                        </td>
                        <td class="text-end">
                            <div v-if="budgetPct(r) !== null" class="ms-auto w-28">
                                <bdi class="text-xs font-bold" :class="budgetPct(r) >= 100 ? 'text-success' : 'text-coral-dark'">{{ budgetPct(r).toFixed(0) }}%</bdi>
                                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-neutral"><div class="h-full rounded-full" :class="budgetPct(r) >= 100 ? 'bg-success' : 'bg-coral'" :style="{ width: `${Math.min(100, budgetPct(r))}%` }" /></div>
                            </div>
                            <span v-else class="text-xs text-muted">–</span>
                        </td>
                        <td><Sparkline :values="r.spark" /></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
