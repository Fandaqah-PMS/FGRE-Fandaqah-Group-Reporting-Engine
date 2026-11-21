<script setup>
import { computed, ref } from 'vue';
import Legend from './Legend.vue';
import AveragePill from './AveragePill.vue';
import { THIS_YEAR, LAST_YEAR, AVERAGE, GRID, AXIS_TEXT, SURFACE } from '../../lib/palette';
import { isNum, shortDate } from '../../lib/format';
import { niceTicks, runs, smoothPath } from '../../lib/chart';
import { useWidth } from '../../lib/useWidth';
import { useI18n } from '../../lib/i18n';

/**
 * Line + area chart in the PMS dashboard style: this period as a navy line over a soft
 * gradient, comparison as a grey dashed line, optional coral dashed average with a pill.
 * points: [{ key, ty, ly }] (key = date by default). Missing values leave gaps, so a
 * month-to-date line simply stops at today. Time runs left to right in Arabic too.
 */
const props = defineProps({
    points: { type: Array, required: true },
    format: { type: Function, required: true },
    height: { type: Number, default: 280 },
    xLabel: { type: Function, default: null },
    tooltipLabel: { type: Function, default: null },
    tyLabel: { type: String, default: null },
    lyLabel: { type: String, default: null },
    average: { type: Boolean, default: false },
    showLy: { type: Boolean, default: true },
    labelWidth: { type: Number, default: null },
});

const { t, locale } = useI18n();
const { el, width } = useWidth();
const active = ref(null);
const gid = `area-${Math.random().toString(36).slice(2, 8)}`;

const keyOf = (p) => p.key ?? p.date;
const xText = (p) => (props.xLabel ? props.xLabel(p) : shortDate(keyOf(p), locale.value));
const tipText = (p) => (props.tooltipLabel ? props.tooltipLabel(p) : xText(p));

const pad = { top: 14, right: 16, bottom: 30, left: 56 };
const innerW = computed(() => width.value - pad.left - pad.right);
const innerH = computed(() => props.height - pad.top - pad.bottom);
const n = computed(() => props.points.length);

const tyValues = computed(() => props.points.map((p) => p.ty).filter(isNum).map(Number));
const avg = computed(() => (props.average && tyValues.value.length ? tyValues.value.reduce((a, b) => a + b, 0) / tyValues.value.length : null));
const ticks = computed(() => niceTicks(Math.max(0, ...props.points.flatMap((p) => [p.ty, props.showLy ? p.ly : null]).filter(isNum).map(Number))));
const yMax = computed(() => ticks.value[ticks.value.length - 1] || 1);
const x = (i) => pad.left + (n.value <= 1 ? innerW.value / 2 : (i / (n.value - 1)) * innerW.value);
const y = (v) => pad.top + innerH.value - (Number(v) / yMax.value) * innerH.value;
const base = computed(() => pad.top + innerH.value);

const lines = (key) => runs(props.points.map((p) => p[key])).map((r) => smoothPath(r.map((i) => [x(i), y(props.points[i][key])])));
const tyLines = computed(() => lines('ty'));
const lyLines = computed(() => (props.showLy ? lines('ly') : []));
const tyArea = computed(() => runs(props.points.map((p) => p.ty)).filter((r) => r.length > 1).map((r) => {
    const pts = r.map((i) => [x(i), y(props.points[i].ty)]);
    return `${smoothPath(pts)}L${pts[pts.length - 1][0]},${base.value}L${pts[0][0]},${base.value}Z`;
}));
const lastTy = computed(() => props.points.map((p) => isNum(p.ty)).lastIndexOf(true));

const xLabels = computed(() => {
    if (!n.value) return [];
    const lw = props.labelWidth ?? (locale.value === 'ar' ? 90 : 62);
    const step = n.value <= 1 ? Infinity : innerW.value / (n.value - 1);
    const every = Math.max(1, Math.ceil(lw / step));
    return props.points.map((p, i) => ({ i, p })).filter(({ i }) => i === n.value - 1 || (i % every === 0 && n.value - 1 - i >= every));
});

function onMove(event) {
    if (!n.value) return;
    const px = event.clientX - event.currentTarget.getBoundingClientRect().left;
    active.value = Math.min(n.value - 1, Math.max(0, Math.round(((px - pad.left) / innerW.value) * (n.value - 1))));
}
function onKey(event) {
    if (!n.value) return;
    if (event.key === 'ArrowRight') active.value = Math.min(n.value - 1, (active.value ?? n.value - 1) + 1);
    if (event.key === 'ArrowLeft') active.value = Math.max(0, (active.value ?? n.value - 1) - 1);
}
const tipLeft = computed(() => (active.value === null ? 0 : Math.min(width.value - 200, Math.max(0, x(active.value) - 100))));
const legend = computed(() => [
    { label: props.tyLabel ?? t('This year'), color: THIS_YEAR, kind: 'line' },
    ...(props.showLy ? [{ label: props.lyLabel ?? t('Last year'), color: LAST_YEAR, kind: 'dashed' }] : []),
    ...(avg.value !== null ? [{ label: t('Average'), color: AVERAGE, kind: 'dashed' }] : []),
]);
</script>

<template>
    <div ref="el" class="relative" dir="ltr">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <Legend :items="legend" />
            <AveragePill v-if="avg !== null" :value="format(avg)" />
        </div>

        <svg :width="width" :height="height" class="block touch-none outline-none" role="img" tabindex="0" :aria-label="legend.map((l) => l.label).join(', ')"
             @pointermove="onMove" @pointerleave="active = null" @focus="active = lastTy" @blur="active = null" @keydown="onKey">
            <defs>
                <linearGradient :id="gid" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" :stop-color="THIS_YEAR" stop-opacity="0.16" />
                    <stop offset="1" :stop-color="THIS_YEAR" stop-opacity="0" />
                </linearGradient>
            </defs>
            <g v-for="tick in ticks" :key="tick">
                <line :x1="pad.left" :x2="width - pad.right" :y1="y(tick)" :y2="y(tick)" :stroke="GRID" stroke-dasharray="3 5" />
                <text :x="pad.left - 10" :y="y(tick)" text-anchor="end" dominant-baseline="middle" font-size="11" :fill="AXIS_TEXT">{{ format(tick, true) }}</text>
            </g>
            <line :x1="pad.left" :x2="width - pad.right" :y1="base" :y2="base" :stroke="GRID" />
            <text v-for="l in xLabels" :key="l.i" :x="x(l.i)" :y="height - 8" font-size="11" :fill="AXIS_TEXT"
                  :text-anchor="l.i === 0 ? 'start' : l.i === n - 1 ? 'end' : 'middle'">{{ xText(l.p) }}</text>

            <path v-for="(d, i) in tyArea" :key="`a${i}`" :d="d" :fill="`url(#${gid})`" />
            <path v-for="(d, i) in lyLines" :key="`l${i}`" :d="d" fill="none" :stroke="LAST_YEAR" stroke-width="2" stroke-dasharray="5 5" stroke-linecap="round" />
            <path v-for="(d, i) in tyLines" :key="`t${i}`" :d="d" fill="none" :stroke="THIS_YEAR" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
            <line v-if="avg !== null" :x1="pad.left" :x2="width - pad.right" :y1="y(avg)" :y2="y(avg)" :stroke="AVERAGE" stroke-width="1.5" stroke-dasharray="6 5" />

            <circle v-if="active === null && lastTy >= 0" :cx="x(lastTy)" :cy="y(points[lastTy].ty)" r="4.5" :fill="THIS_YEAR" :stroke="SURFACE" stroke-width="2" />
            <template v-if="active !== null">
                <line :x1="x(active)" :x2="x(active)" :y1="pad.top" :y2="base" :stroke="AXIS_TEXT" opacity="0.3" />
                <circle v-if="showLy && isNum(points[active].ly)" :cx="x(active)" :cy="y(points[active].ly)" r="4.5" :fill="LAST_YEAR" :stroke="SURFACE" stroke-width="2" />
                <circle v-if="isNum(points[active].ty)" :cx="x(active)" :cy="y(points[active].ty)" r="4.5" :fill="THIS_YEAR" :stroke="SURFACE" stroke-width="2" />
            </template>
        </svg>

        <div v-if="active !== null" role="tooltip" class="pointer-events-none absolute top-12 z-10 w-[200px] rounded-xl border border-line bg-white px-3 py-2.5 text-xs shadow-lg" :style="{ left: `${tipLeft}px` }">
            <div class="mb-1 font-semibold text-ink-2">{{ tipText(points[active]) }}</div>
            <div class="flex items-center gap-2"><span class="h-[3px] w-3 rounded" :style="{ background: THIS_YEAR }" /><b class="text-navy tabular">{{ format(points[active].ty) }}</b><span class="truncate text-muted">{{ legend[0].label }}</span></div>
            <div v-if="showLy" class="flex items-center gap-2"><span class="h-[3px] w-3 rounded" :style="{ background: LAST_YEAR }" /><b class="text-navy tabular">{{ format(points[active].ly) }}</b><span class="truncate text-muted">{{ legend[1].label }}</span></div>
        </div>
    </div>
</template>
