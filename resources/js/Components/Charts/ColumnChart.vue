<script setup>
import { computed, ref } from 'vue';
import { GRID, AXIS_TEXT } from '../../lib/palette';
import { isNum } from '../../lib/format';
import { niceTicks } from '../../lib/chart';
import { useWidth } from '../../lib/useWidth';
import { useI18n } from '../../lib/i18n';

/**
 * Grouped columns (e.g. months × this year / last year, or hotels × revenue / expenses).
 * categories: [{ key, label }]; series: [{ key, label, color }]; values[catKey][seriesKey].
 * Columns ≤ 24px, 4px rounded tops, 2px gap inside a group. Hover/focus a group for values.
 * keepOrder: categories that are time stay left to right in Arabic too.
 */
const props = defineProps({
    categories: { type: Array, required: true },
    series: { type: Array, required: true },
    values: { type: Object, required: true },
    format: { type: Function, required: true },
    height: { type: Number, default: 260 },
    labelEvery: { type: Number, default: 1 },
    highlight: { type: String, default: null },   // category key drawn at full strength
});

const { t } = useI18n();
const { el, width } = useWidth();
const active = ref(null);

const pad = { top: 16, right: 8, bottom: 34, left: 54 };
const innerW = computed(() => width.value - pad.left - pad.right);
const innerH = computed(() => props.height - pad.top - pad.bottom);
const band = computed(() => innerW.value / Math.max(1, props.categories.length));
const barW = computed(() => Math.max(4, Math.min(24, (band.value * 0.7 - (props.series.length - 1) * 2) / props.series.length)));
const groupW = computed(() => barW.value * props.series.length + (props.series.length - 1) * 2);

const all = computed(() => props.categories.flatMap((c) => props.series.map((s) => props.values[c.key]?.[s.key])).filter(isNum).map(Number));
const ticks = computed(() => niceTicks(Math.max(0, ...all.value)));
const yMax = computed(() => ticks.value[ticks.value.length - 1] || 1);
const y = (v) => pad.top + innerH.value - (Math.max(0, Number(v)) / yMax.value) * innerH.value;
const gx = (ci) => pad.left + ci * band.value + (band.value - groupW.value) / 2;

/** Bar with rounded data end and square base. */
const bar = (x, v) => {
    const top = y(v), base = pad.top + innerH.value, w = barW.value, r = Math.min(4, w / 2, base - top);
    if (base - top <= 0.5) return '';
    return `M${x},${base}V${top + r}Q${x},${top} ${x + r},${top}H${x + w - r}Q${x + w},${top} ${x + w},${top + r}V${base}Z`;
};

const tipLeft = computed(() => (active.value === null ? 0 : Math.min(width.value - 200, Math.max(0, gx(active.value) + groupW.value / 2 - 100))));
</script>

<template>
    <div ref="el" class="relative" dir="ltr">
        <div class="mb-2 flex flex-wrap gap-4 text-xs font-semibold text-ink-2" role="list" :aria-label="t('Legend')">
            <span v-for="s in series" :key="s.key" class="flex items-center gap-1.5" role="listitem"><span class="size-2.5 rounded-[3px]" :style="{ background: s.color }" />{{ s.label }}</span>
        </div>
        <svg :width="width" :height="height" class="block" role="img" :aria-label="series.map((s) => s.label).join(', ')">
            <g v-for="tick in ticks" :key="tick">
                <line :x1="pad.left" :x2="width - pad.right" :y1="y(tick)" :y2="y(tick)" :stroke="GRID" />
                <text :x="pad.left - 10" :y="y(tick)" text-anchor="end" dominant-baseline="middle" font-size="11" :fill="AXIS_TEXT">{{ format(tick, true) }}</text>
            </g>
            <g v-for="(c, ci) in categories" :key="c.key" tabindex="0" class="cursor-default outline-none"
               @pointerenter="active = ci" @pointerleave="active = null" @focus="active = ci" @blur="active = null">
                <rect :x="pad.left + ci * band" :y="pad.top" :width="band" :height="innerH" :fill="active === ci ? '#F8F7F4' : 'transparent'" rx="8" />
                <path v-for="(s, si) in series" :key="s.key" :d="bar(gx(ci) + si * (barW + 2), values[c.key]?.[s.key])" :fill="s.color" />
                <text v-if="ci % labelEvery === 0 || ci === categories.length - 1" :x="gx(ci) + groupW / 2" :y="height - 10" text-anchor="middle" font-size="11"
                      :fill="AXIS_TEXT" :font-weight="highlight === c.key ? 700 : 400">{{ c.label }}</text>
            </g>
        </svg>
        <div v-if="active !== null" role="tooltip" class="pointer-events-none absolute top-6 z-10 w-[200px] rounded-xl border border-line bg-white px-3 py-2.5 text-xs shadow-lg" :style="{ left: `${tipLeft}px` }">
            <div class="mb-1 font-semibold text-ink-2">{{ categories[active].tooltip ?? categories[active].label }}</div>
            <div v-for="s in series" :key="s.key" class="flex items-center gap-2">
                <span class="h-[3px] w-3 rounded" :style="{ background: s.color }" />
                <b class="text-navy tabular">{{ format(values[categories[active].key]?.[s.key]) }}</b><span class="truncate text-muted">{{ s.label }}</span>
            </div>
        </div>
    </div>
</template>
