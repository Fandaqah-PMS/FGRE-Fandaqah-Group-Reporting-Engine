<script setup>
import { computed, ref } from 'vue';
import Legend from './Legend.vue';
import AveragePill from './AveragePill.vue';
import { AVERAGE, GRID, AXIS_TEXT } from '../../lib/palette';
import { isNum } from '../../lib/format';
import { niceTicks } from '../../lib/chart';
import { useWidth } from '../../lib/useWidth';
import { useI18n } from '../../lib/i18n';

/**
 * Grouped columns in the PMS dashboard style. categories: [{ key, label, sub?, tooltip? }]
 * (sub = second label line, e.g. weekday over day number); series: [{ key, label, color }];
 * values[catKey][seriesKey]. Options: value labels over the first series, coral dashed
 * average of the first series with a pill. Rounded tops, square base, ≤ 28px wide.
 */
const props = defineProps({
    categories: { type: Array, required: true },
    series: { type: Array, required: true },
    values: { type: Object, required: true },
    format: { type: Function, required: true },
    height: { type: Number, default: 270 },
    valueLabels: { type: Boolean, default: false },
    average: { type: Boolean, default: false },
    labelEvery: { type: Number, default: 1 },
    showLegend: { type: Boolean, default: true },
});

const { t } = useI18n();
const { el, width } = useWidth();
const active = ref(null);

const hasSub = computed(() => props.categories.some((c) => c.sub));
const pad = computed(() => ({ top: props.valueLabels ? 26 : 14, right: 8, bottom: hasSub.value ? 44 : 30, left: props.valueLabels ? 12 : 56 }));
const innerW = computed(() => width.value - pad.value.left - pad.value.right);
const innerH = computed(() => props.height - pad.value.top - pad.value.bottom);
const band = computed(() => innerW.value / Math.max(1, props.categories.length));
const barW = computed(() => Math.max(4, Math.min(28, (band.value * 0.62 - (props.series.length - 1) * 3) / props.series.length)));
const groupW = computed(() => barW.value * props.series.length + (props.series.length - 1) * 3);

const first = computed(() => props.series[0]?.key);
const all = computed(() => props.categories.flatMap((c) => props.series.map((s) => props.values[c.key]?.[s.key])).filter(isNum).map(Number));
const firstValues = computed(() => props.categories.map((c) => props.values[c.key]?.[first.value]).filter(isNum).map(Number));
const avg = computed(() => (props.average && firstValues.value.length ? firstValues.value.reduce((a, b) => a + b, 0) / firstValues.value.length : null));
const ticks = computed(() => niceTicks(Math.max(0, ...all.value)));
const yMax = computed(() => ticks.value[ticks.value.length - 1] || 1);
const y = (v) => pad.value.top + innerH.value - (Math.max(0, Number(v)) / yMax.value) * innerH.value;
const base = computed(() => pad.value.top + innerH.value);
const gx = (ci) => pad.value.left + ci * band.value + (band.value - groupW.value) / 2;

const bar = (x, v) => {
    if (!isNum(v)) return '';
    const top = y(v), w = barW.value, r = Math.min(6, w / 2, base.value - top);
    if (base.value - top <= 0.5) return '';
    return `M${x},${base.value}V${top + r}Q${x},${top} ${x + r},${top}H${x + w - r}Q${x + w},${top} ${x + w},${top + r}V${base.value}Z`;
};
const tipLeft = computed(() => (active.value === null ? 0 : Math.min(width.value - 200, Math.max(0, gx(active.value) + groupW.value / 2 - 100))));
const legend = computed(() => [
    ...props.series.map((s) => ({ label: s.label, color: s.color, kind: 'dot' })),
    ...(avg.value !== null ? [{ label: t('Average'), color: AVERAGE, kind: 'dashed' }] : []),
]);
</script>

<template>
    <div ref="el" class="relative" dir="ltr">
        <div v-if="showLegend || avg !== null" class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <Legend v-if="showLegend" :items="legend" /><span v-else />
            <AveragePill v-if="avg !== null" :value="format(avg)" />
        </div>
        <svg :width="width" :height="height" class="block" role="img" :aria-label="series.map((s) => s.label).join(', ')">
            <template v-if="!valueLabels">
                <g v-for="tick in ticks" :key="tick">
                    <line :x1="pad.left" :x2="width - pad.right" :y1="y(tick)" :y2="y(tick)" :stroke="GRID" stroke-dasharray="3 5" />
                    <text :x="pad.left - 10" :y="y(tick)" text-anchor="end" dominant-baseline="middle" font-size="11" :fill="AXIS_TEXT">{{ format(tick, true) }}</text>
                </g>
            </template>
            <line :x1="pad.left" :x2="width - pad.right" :y1="base" :y2="base" :stroke="GRID" />

            <g v-for="(c, ci) in categories" :key="c.key" tabindex="0" class="outline-none"
               @pointerenter="active = ci" @pointerleave="active = null" @focus="active = ci" @blur="active = null">
                <rect :x="pad.left + ci * band" :y="0" :width="band" :height="height - 4" :fill="active === ci ? '#F8F7F4' : 'transparent'" rx="10" />
                <path v-for="(s, si) in series" :key="s.key" :d="bar(gx(ci) + si * (barW + 3), values[c.key]?.[s.key])" :fill="s.color" />
                <text v-if="valueLabels && isNum(values[c.key]?.[first])" :x="gx(ci) + barW / 2" :y="y(values[c.key][first]) - 7" text-anchor="middle"
                      font-size="10.5" font-weight="600" :fill="AXIS_TEXT">{{ format(values[c.key][first], true) }}</text>
                <template v-if="ci % labelEvery === 0 || ci === categories.length - 1">
                    <text :x="gx(ci) + groupW / 2" :y="base + 17" text-anchor="middle" font-size="11" :fill="AXIS_TEXT">{{ c.label }}</text>
                    <text v-if="c.sub" :x="gx(ci) + groupW / 2" :y="base + 32" text-anchor="middle" font-size="11" font-weight="600" fill="#262E43">{{ c.sub }}</text>
                </template>
            </g>
            <line v-if="avg !== null" :x1="pad.left" :x2="width - pad.right" :y1="y(avg)" :y2="y(avg)" :stroke="AVERAGE" stroke-width="1.5" stroke-dasharray="6 5" pointer-events="none" />
        </svg>
        <div v-if="active !== null" role="tooltip" class="pointer-events-none absolute top-10 z-10 w-[200px] rounded-xl border border-line bg-white px-3 py-2.5 text-xs shadow-lg" :style="{ left: `${tipLeft}px` }">
            <div class="mb-1 font-semibold text-ink-2">{{ categories[active].tooltip ?? `${categories[active].label} ${categories[active].sub ?? ''}` }}</div>
            <div v-for="s in series" :key="s.key" class="flex items-center gap-2">
                <span class="size-2 rounded-full" :style="{ background: s.color }" />
                <b class="text-navy tabular">{{ format(values[categories[active].key]?.[s.key]) }}</b><span class="truncate text-muted">{{ s.label }}</span>
            </div>
        </div>
    </div>
</template>
