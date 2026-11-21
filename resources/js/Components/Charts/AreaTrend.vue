<script setup>
import { computed, ref } from 'vue';
import { THIS_YEAR, LAST_YEAR, GRID, AXIS_TEXT, SURFACE } from '../../lib/palette';
import { isNum, shortDate } from '../../lib/format';
import { niceTicks, runs, smoothPath } from '../../lib/chart';
import { useWidth } from '../../lib/useWidth';
import { useI18n } from '../../lib/i18n';

/**
 * Daily trend: this year as a coral area, last year as a grey line, one axis.
 * points: [{ date, ty, ly }]. Crosshair snaps to the nearest day (pointer or ←/→ keys).
 * Time always runs left to right, in Arabic too.
 */
const props = defineProps({
    points: { type: Array, required: true },
    format: { type: Function, required: true },
    height: { type: Number, default: 260 },
});

const { t, locale } = useI18n();
const { el, width } = useWidth();
const active = ref(null);
const gid = `area-${Math.random().toString(36).slice(2, 8)}`;

const pad = { top: 14, right: 14, bottom: 30, left: 54 };
const innerW = computed(() => width.value - pad.left - pad.right);
const innerH = computed(() => props.height - pad.top - pad.bottom);
const n = computed(() => props.points.length);

const ticks = computed(() => niceTicks(Math.max(0, ...props.points.flatMap((p) => [p.ty, p.ly]).filter(isNum).map(Number))));
const yMax = computed(() => ticks.value[ticks.value.length - 1] || 1);
const x = (i) => pad.left + (n.value <= 1 ? innerW.value / 2 : (i / (n.value - 1)) * innerW.value);
const y = (v) => pad.top + innerH.value - (Number(v) / yMax.value) * innerH.value;

const lines = (key) => runs(props.points.map((p) => p[key])).map((r) => smoothPath(r.map((i) => [x(i), y(props.points[i][key])])));
const tyLines = computed(() => lines('ty'));
const lyLines = computed(() => lines('ly'));
const tyArea = computed(() => runs(props.points.map((p) => p.ty)).map((r) => {
    const pts = r.map((i) => [x(i), y(props.points[i].ty)]);
    const base = pad.top + innerH.value;
    return `${smoothPath(pts)}L${pts[pts.length - 1][0]},${base}L${pts[0][0]},${base}Z`;
}));

const xLabels = computed(() => {
    if (!n.value) return [];
    const labelWidth = locale.value === 'ar' ? 92 : 64;
    const step = n.value <= 1 ? Infinity : innerW.value / (n.value - 1);
    const every = Math.max(1, Math.ceil(labelWidth / step));
    return props.points.map((p, i) => ({ i, date: p.date })).filter(({ i }) => i === n.value - 1 || (i % every === 0 && n.value - 1 - i >= every));
});

function onMove(event) {
    if (!n.value) return;
    const rect = event.currentTarget.getBoundingClientRect();
    const px = event.clientX - rect.left;
    active.value = Math.min(n.value - 1, Math.max(0, Math.round(((px - pad.left) / innerW.value) * (n.value - 1))));
}
function onKey(event) {
    if (!n.value) return;
    if (event.key === 'ArrowRight') active.value = Math.min(n.value - 1, (active.value ?? n.value - 1) + 1);
    if (event.key === 'ArrowLeft') active.value = Math.max(0, (active.value ?? n.value - 1) - 1);
}
const tipLeft = computed(() => (active.value === null ? 0 : Math.min(width.value - 190, Math.max(0, x(active.value) - 95))));
const last = computed(() => n.value - 1);
</script>

<template>
    <div ref="el" class="relative" dir="ltr">
        <div class="mb-2 flex flex-wrap gap-4 text-xs font-semibold text-ink-2" role="list" :aria-label="t('Legend')">
            <span class="flex items-center gap-1.5" role="listitem"><span class="h-[3px] w-4 rounded-full" :style="{ background: THIS_YEAR }" />{{ t('This year') }}</span>
            <span class="flex items-center gap-1.5" role="listitem"><span class="h-[3px] w-4 rounded-full" :style="{ background: LAST_YEAR }" />{{ t('Last year') }}</span>
        </div>

        <svg :width="width" :height="height" class="block touch-none outline-none" role="img" tabindex="0" :aria-label="t('Daily trend, this year and last year')"
             @pointermove="onMove" @pointerleave="active = null" @focus="active = last" @blur="active = null" @keydown="onKey">
            <defs>
                <linearGradient :id="gid" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" :stop-color="THIS_YEAR" stop-opacity="0.22" />
                    <stop offset="1" :stop-color="THIS_YEAR" stop-opacity="0.01" />
                </linearGradient>
            </defs>
            <g v-for="tick in ticks" :key="tick">
                <line :x1="pad.left" :x2="width - pad.right" :y1="y(tick)" :y2="y(tick)" :stroke="GRID" />
                <text :x="pad.left - 10" :y="y(tick)" text-anchor="end" dominant-baseline="middle" font-size="11" :fill="AXIS_TEXT">{{ format(tick, true) }}</text>
            </g>
            <text v-for="l in xLabels" :key="l.date" :x="x(l.i)" :y="height - 8" font-size="11" :fill="AXIS_TEXT"
                  :text-anchor="l.i === 0 ? 'start' : l.i === last ? 'end' : 'middle'">{{ shortDate(l.date, locale) }}</text>

            <path v-for="(d, i) in tyArea" :key="`a${i}`" :d="d" :fill="`url(#${gid})`" />
            <path v-for="(d, i) in lyLines" :key="`l${i}`" :d="d" fill="none" :stroke="LAST_YEAR" stroke-width="2" stroke-linecap="round" />
            <path v-for="(d, i) in tyLines" :key="`t${i}`" :d="d" fill="none" :stroke="THIS_YEAR" stroke-width="2.5" stroke-linecap="round" />

            <template v-if="n && active === null">
                <circle v-if="isNum(points[last].ty)" :cx="x(last)" :cy="y(points[last].ty)" r="4.5" :fill="THIS_YEAR" :stroke="SURFACE" stroke-width="2" />
            </template>
            <template v-if="active !== null">
                <line :x1="x(active)" :x2="x(active)" :y1="pad.top" :y2="pad.top + innerH" :stroke="AXIS_TEXT" opacity="0.35" />
                <circle v-if="isNum(points[active].ly)" :cx="x(active)" :cy="y(points[active].ly)" r="4.5" :fill="LAST_YEAR" :stroke="SURFACE" stroke-width="2" />
                <circle v-if="isNum(points[active].ty)" :cx="x(active)" :cy="y(points[active].ty)" r="4.5" :fill="THIS_YEAR" :stroke="SURFACE" stroke-width="2" />
            </template>
        </svg>

        <div v-if="active !== null" role="tooltip" class="pointer-events-none absolute top-6 z-10 w-[190px] rounded-xl border border-line bg-white px-3 py-2.5 text-xs shadow-lg" :style="{ left: `${tipLeft}px` }">
            <div class="mb-1 font-semibold text-ink-2">{{ shortDate(points[active].date, locale) }}</div>
            <div class="flex items-center gap-2"><span class="h-[3px] w-3 rounded" :style="{ background: THIS_YEAR }" /><b class="text-navy tabular">{{ format(points[active].ty) }}</b><span class="text-muted">{{ t('This year') }}</span></div>
            <div class="flex items-center gap-2"><span class="h-[3px] w-3 rounded" :style="{ background: LAST_YEAR }" /><b class="text-navy tabular">{{ format(points[active].ly) }}</b><span class="text-muted">{{ t('Last year') }}</span></div>
        </div>
    </div>
</template>
