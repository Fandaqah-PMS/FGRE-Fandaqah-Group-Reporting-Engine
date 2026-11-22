<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { change, tone } from '../../lib/reports';
import { isNum } from '../../lib/format';
import { useI18n } from '../../lib/i18n';

/**
 * Side by side: the report's own rows, one column per hotel, then hotel-set subtotals and
 * the group. Each cell: the figure, and its change against the comparison (last year or
 * budget). Among hotels, the best figure of a row is marked and shaded green, the weakest
 * shaded coral, so differences stand out without reading every number.
 * columns: [{ id, label, sub, kind: 'hotel' | 'region' | 'group', href }]
 * value(row, col) / base(row, col): numbers or null.
 */
const props = defineProps({
    sections: { type: Array, required: true },
    columns: { type: Array, required: true },
    value: { type: Function, required: true },
    base: { type: Function, required: true },
    basisLabel: { type: String, required: true },
});
const { t } = useI18n();

const hotelCols = computed(() => props.columns.filter((c) => c.kind === 'hotel'));
const extremes = computed(() => {
    const out = {};
    for (const s of props.sections) for (const r of s.rows) {
        const vals = hotelCols.value.map((c) => props.value(r, c)).filter(isNum).map(Number);
        if (!r.better || vals.length < 2 || Math.max(...vals) === Math.min(...vals)) continue;
        const hi = Math.max(...vals), lo = Math.min(...vals);
        out[r.key] = r.better === 'high' ? { best: hi, worst: lo } : { best: lo, worst: hi };
    }
    return out;
});
const mark = (r, c) => {
    if (c.kind !== 'hotel') return null;
    const v = props.value(r, c), e = extremes.value[r.key];
    if (!e || !isNum(v)) return null;
    return Number(v) === e.best ? 'best' : Number(v) === e.worst ? 'worst' : null;
};
const colBg = (c) => (c.kind === 'group' ? 'bg-coral-tint/50' : c.kind === 'region' ? 'bg-paper' : '');
</script>

<template>
    <div>
        <div class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-ink-2">
            <span class="flex items-center gap-1.5"><b class="text-ink">123</b>{{ t('Figure for the period') }}</span>
            <span v-if="basisLabel" class="flex items-center gap-1.5"><span class="text-success">▲</span><span class="text-coral-dark">▼</span>{{ t('Change vs :basis', { basis: basisLabel }) }}</span>
            <span class="flex items-center gap-1.5"><span class="inline-block size-3 rounded bg-success-tint ring-1 ring-success/30" />{{ t('Best hotel') }}</span>
            <span class="flex items-center gap-1.5"><span class="inline-block size-3 rounded bg-coral-tint ring-1 ring-coral/30" />{{ t('Weakest hotel') }}</span>
        </div>
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm tabular" :style="{ minWidth: `${18 + columns.length * 8.5}rem` }">
                    <thead>
                        <tr class="bg-navy text-white">
                            <th class="sticky start-0 z-10 min-w-[14rem] bg-navy px-4 py-3 text-start text-xs font-bold">{{ t('Measure') }}</th>
                            <th v-for="c in columns" :key="c.id" class="px-3 py-3 text-end align-bottom text-xs font-bold"
                                :class="c.kind === 'group' ? 'bg-coral' : c.kind === 'region' ? 'bg-navy-light' : ''">
                                <Link v-if="c.href" :href="c.href" class="block leading-tight hover:underline">{{ c.label }}</Link>
                                <span v-else class="block leading-tight">{{ c.label }}</span>
                                <span v-if="c.sub" class="mt-0.5 block text-[10px] font-medium opacity-70">{{ c.sub }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody v-for="s in sections" :key="s.key">
                        <tr><td :colspan="columns.length + 1" class="bg-paper px-4 py-2 text-[11px] font-bold tracking-wider text-coral uppercase">{{ s.title }}</td></tr>
                        <tr v-for="r in s.rows" :key="r.key" class="border-t border-line">
                            <td class="sticky start-0 z-[1] bg-surface px-4 py-2.5">
                                <div class="flex items-baseline gap-2">
                                    <abbr :title="r.label" class="w-14 shrink-0 cursor-help text-[11px] font-bold text-muted no-underline" dir="ltr">{{ r.code }}</abbr>
                                    <span class="font-semibold text-ink" :title="r.label">{{ r.name ?? r.label }}</span>
                                </div>
                            </td>
                            <td v-for="c in columns" :key="c.id" class="px-3 py-2 text-end" :class="[colBg(c), mark(r, c) === 'best' && '!bg-success-tint', mark(r, c) === 'worst' && '!bg-coral-tint/70']">
                                <bdi class="block font-medium text-ink tabular" :class="c.kind === 'group' && '!font-semibold'">{{ r.f(value(r, c)) }}</bdi>
                                <template v-for="ch in [change(r, value(r, c), base(r, c))]" :key="'c'">
                                    <bdi v-if="ch" class="block text-[11px] font-semibold"
                                         :class="tone(r, ch.d) === 'good' ? 'text-success' : tone(r, ch.d) === 'bad' ? 'text-coral-dark' : 'text-muted'">
                                        {{ ch.d >= 0 ? '▲' : '▼' }} {{ ch.label }}
                                    </bdi>
                                    <span v-else class="block text-[11px] text-muted">&nbsp;</span>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
