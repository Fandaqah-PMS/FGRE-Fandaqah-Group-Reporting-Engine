<script setup>
import { computed } from 'vue';
import { smoothPath, runs } from '../../lib/chart';
import { THIS_YEAR, SURFACE } from '../../lib/palette';
import { isNum } from '../../lib/format';

// Small trend line under a KPI: last N days, current day marked.
const props = defineProps({ values: { type: Array, default: () => [] }, height: { type: Number, default: 36 } });
const W = 160;

const geo = computed(() => {
    const nums = props.values.filter(isNum).map(Number);
    if (nums.length < 2) return null;
    const min = Math.min(...nums), max = Math.max(...nums), span = max - min || 1;
    const n = props.values.length;
    const pt = (v, i) => [(i / (n - 1)) * (W - 6) + 3, props.height - 4 - ((Number(v) - min) / span) * (props.height - 10)];
    const segments = runs(props.values).map((r) => r.map((i) => pt(props.values[i], i)));
    const lastIdx = props.values.map(isNum).lastIndexOf(true);
    const main = segments.reduce((a, b) => (b.length > a.length ? b : a), []);
    return {
        lines: segments.map(smoothPath),
        area: main.length > 1 ? `${smoothPath(main)}L${main[main.length - 1][0]},${props.height}L${main[0][0]},${props.height}Z` : '',
        last: pt(props.values[lastIdx], lastIdx),
    };
});
const gid = `spark-${Math.random().toString(36).slice(2, 8)}`;
</script>

<template>
    <svg v-if="geo" :viewBox="`0 0 ${W} ${height}`" preserveAspectRatio="none" class="block h-9 w-full overflow-visible" aria-hidden="true" dir="ltr">
        <defs>
            <linearGradient :id="gid" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" :stop-color="THIS_YEAR" stop-opacity="0.18" />
                <stop offset="1" :stop-color="THIS_YEAR" stop-opacity="0" />
            </linearGradient>
        </defs>
        <path :d="geo.area" :fill="`url(#${gid})`" />
        <path v-for="(d, i) in geo.lines" :key="i" :d="d" fill="none" :stroke="THIS_YEAR" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linecap="round" />
        <circle :cx="geo.last[0]" :cy="geo.last[1]" r="3.5" :fill="THIS_YEAR" :stroke="SURFACE" stroke-width="2" vector-effect="non-scaling-stroke" />
    </svg>
</template>
