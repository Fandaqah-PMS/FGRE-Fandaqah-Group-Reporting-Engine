<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { THIS_YEAR, LAST_YEAR, GRID, AXIS_TEXT, SURFACE } from '../../lib/palette';
import { isNum, shortDate } from '../../lib/format';
import { useI18n } from '../../lib/i18n';

/**
 * Daily trend, this year vs the same dates last year, on one axis.
 * points: [{ date, ty, ly }]. Crosshair snaps to the nearest day; the tooltip
 * lists both series. The time axis always runs left to right.
 */
const props = defineProps({
    points: { type: Array, required: true },
    format: { type: Function, required: true },
    height: { type: Number, default: 240 },
});

const { t, locale } = useI18n();
const box = ref(null);
const width = ref(640);
const active = ref(null);
let observer;

onMounted(() => {
    observer = new ResizeObserver(([entry]) => (width.value = Math.max(280, entry.contentRect.width)));
    observer.observe(box.value);
});
onBeforeUnmount(() => observer?.disconnect());

const pad = { top: 12, right: 16, bottom: 28, left: 52 };
const innerW = computed(() => width.value - pad.left - pad.right);
const innerH = computed(() => props.height - pad.top - pad.bottom);

const values = computed(() => props.points.flatMap((p) => [p.ty, p.ly]).filter(isNum).map(Number));

/** Clean ticks: 0 … nice max, 4 steps. */
const ticks = computed(() => {
    const raw = Math.max(...values.value, 0) || 1;
    const step0 = raw / 4;
    const mag = 10 ** Math.floor(Math.log10(step0));
    const step = [1, 2, 2.5, 5, 10].map((m) => m * mag).find((s) => s >= step0);
    return Array.from({ length: 5 }, (_, i) => i * step);
});
const yMax = computed(() => ticks.value[ticks.value.length - 1]);

const x = (i) => pad.left + (props.points.length <= 1 ? 0 : (i / (props.points.length - 1)) * innerW.value);
const y = (v) => pad.top + innerH.value - (Number(v) / yMax.value) * innerH.value;

/** Path with gaps where a day is missing. */
const path = (key) => {
    let d = '';
    let pen = false;
    props.points.forEach((p, i) => {
        if (!isNum(p[key])) { pen = false; return; }
        d += `${pen ? 'L' : 'M'}${x(i).toFixed(1)},${y(p[key]).toFixed(1)}`;
        pen = true;
    });
    return d;
};

/** Date labels spaced by their rendered width (Arabic month names are longer); never crowd the last one. */
const xLabels = computed(() => {
    const n = props.points.length;
    if (!n) return [];
    const labelWidth = locale.value === 'ar' ? 92 : 64;
    const step = n <= 1 ? Infinity : innerW.value / (n - 1);
    const every = Math.max(1, Math.ceil(labelWidth / step));
    return props.points
        .map((p, i) => ({ i, date: p.date }))
        .filter(({ i }) => i === n - 1 || (i % every === 0 && n - 1 - i >= every));
});

const last = computed(() => {
    const i = props.points.length - 1;
    return i >= 0 ? { i, p: props.points[i] } : null;
});

function onMove(event) {
    const rect = event.currentTarget.getBoundingClientRect();
    const px = ((event.clientX - rect.left) / rect.width) * width.value;
    const n = props.points.length;
    if (!n) return;
    const i = Math.round(((px - pad.left) / innerW.value) * (n - 1));
    active.value = Math.min(n - 1, Math.max(0, i));
}

function onKey(event) {
    const n = props.points.length;
    if (!n) return;
    if (event.key === 'ArrowRight') active.value = Math.min(n - 1, (active.value ?? n - 1) + 1);
    if (event.key === 'ArrowLeft') active.value = Math.max(0, (active.value ?? n - 1) - 1);
}

const tooltipLeft = computed(() => {
    if (active.value === null) return 0;
    const px = (x(active.value) / width.value) * 100;
    return Math.min(78, Math.max(2, px - 11));
});
</script>

<template>
    <div ref="box" class="relative" dir="ltr">
        <div class="mb-3 flex flex-wrap gap-4 text-xs text-secondary" role="list" :aria-label="t('Legend')">
            <span class="flex items-center gap-1.5" role="listitem"><span class="h-0.5 w-4 rounded" :style="{ background: THIS_YEAR }" />{{ t('This year') }}</span>
            <span class="flex items-center gap-1.5" role="listitem"><span class="h-0.5 w-4 rounded" :style="{ background: LAST_YEAR }" />{{ t('Last year') }}</span>
        </div>

        <svg :width="width" :height="height" class="block touch-none outline-none" role="img" tabindex="0"
             :aria-label="t('Daily trend, this year and last year')"
             @pointermove="onMove" @pointerleave="active = null" @focus="active = points.length - 1" @blur="active = null" @keydown="onKey">
            <!-- grid + y ticks -->
            <g v-for="tick in ticks" :key="tick">
                <line :x1="pad.left" :x2="width - pad.right" :y1="y(tick)" :y2="y(tick)" :stroke="GRID" stroke-width="1" />
                <text :x="pad.left - 8" :y="y(tick)" text-anchor="end" dominant-baseline="middle" font-size="11" :fill="AXIS_TEXT">{{ format(tick, true) }}</text>
            </g>
            <!-- x labels -->
            <text v-for="l in xLabels" :key="l.date" :x="x(l.i)" :y="height - 8" font-size="11" :fill="AXIS_TEXT"
                  :text-anchor="l.i === 0 ? 'start' : l.i === points.length - 1 ? 'end' : 'middle'">{{ shortDate(l.date, locale) }}</text>

            <!-- lines: last year first, this year on top -->
            <path :d="path('ly')" fill="none" :stroke="LAST_YEAR" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
            <path :d="path('ty')" fill="none" :stroke="THIS_YEAR" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />

            <!-- end markers with surface ring -->
            <template v-if="last">
                <circle v-if="isNum(last.p.ly)" :cx="x(last.i)" :cy="y(last.p.ly)" r="4" :fill="LAST_YEAR" :stroke="SURFACE" stroke-width="2" />
                <circle v-if="isNum(last.p.ty)" :cx="x(last.i)" :cy="y(last.p.ty)" r="4" :fill="THIS_YEAR" :stroke="SURFACE" stroke-width="2" />
            </template>

            <!-- crosshair -->
            <template v-if="active !== null">
                <line :x1="x(active)" :x2="x(active)" :y1="pad.top" :y2="pad.top + innerH" :stroke="AXIS_TEXT" stroke-width="1" opacity="0.5" />
                <circle v-if="isNum(points[active].ly)" :cx="x(active)" :cy="y(points[active].ly)" r="4" :fill="LAST_YEAR" :stroke="SURFACE" stroke-width="2" />
                <circle v-if="isNum(points[active].ty)" :cx="x(active)" :cy="y(points[active].ty)" r="4" :fill="THIS_YEAR" :stroke="SURFACE" stroke-width="2" />
            </template>
        </svg>

        <div v-if="active !== null" role="tooltip"
             class="pointer-events-none absolute top-8 z-10 min-w-40 rounded-xl border border-line bg-white px-3 py-2 text-xs shadow-lg"
             :style="{ left: `${tooltipLeft}%` }">
            <div class="mb-1 font-medium text-secondary">{{ shortDate(points[active].date, locale) }}</div>
            <div class="flex items-center gap-2"><span class="h-0.5 w-3" :style="{ background: THIS_YEAR }" /><b class="text-navy tabular">{{ format(points[active].ty) }}</b><span class="text-grey">{{ t('This year') }}</span></div>
            <div class="flex items-center gap-2"><span class="h-0.5 w-3" :style="{ background: LAST_YEAR }" /><b class="text-navy tabular">{{ format(points[active].ly) }}</b><span class="text-grey">{{ t('Last year') }}</span></div>
        </div>
    </div>
</template>
