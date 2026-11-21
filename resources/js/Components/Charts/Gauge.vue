<script setup>
import { computed } from 'vue';
import { BRAND } from '../../lib/palette';
import { isNum } from '../../lib/format';
import { useI18n } from '../../lib/i18n';

/** Half dial (PMS style): navy → green gradient arc on a warm track, value in the middle, last year as a coral tick. */
const props = defineProps({
    value: { default: null },
    previous: { default: null },
    label: { type: String, default: '' },
});

const { t } = useI18n();
const uid = Math.random().toString(36).slice(2, 8);
const R = 84, CX = 110, CY = 104;
const clamp = (v) => Math.max(0, Math.min(100, Number(v)));
const point = (pct, r = R) => {
    const a = Math.PI * (1 - pct / 100);
    return [CX + r * Math.cos(a), CY - r * Math.sin(a)];
};
const arc = (pct) => {
    const [x, y] = point(pct);
    return `M${CX - R},${CY} A${R},${R} 0 0 1 ${x.toFixed(2)},${y.toFixed(2)}`;
};
const fill = computed(() => (isNum(props.value) && clamp(props.value) > 0 ? arc(clamp(props.value)) : ''));
const tick = computed(() => {
    if (!isNum(props.previous)) return null;
    const [x1, y1] = point(clamp(props.previous), R - 17);
    const [x2, y2] = point(clamp(props.previous), R + 17);
    return { x1, y1, x2, y2 };
});
</script>

<template>
    <div>
        <div class="relative mx-auto w-full max-w-[250px]">
            <svg viewBox="0 0 220 116" class="w-full overflow-visible" role="img" :aria-label="`${label} ${value ?? ''}%`">
                <defs>
                    <linearGradient :id="`gg-${uid}`" x1="0" y1="0" x2="1" y2="0">
                        <stop offset="0" :stop-color="BRAND.navy" />
                        <stop offset="1" :stop-color="BRAND.green" />
                    </linearGradient>
                </defs>
                <path :d="arc(100)" fill="none" :stroke="BRAND.neutral" stroke-width="20" stroke-linecap="round" />
                <path v-if="fill" :d="fill" fill="none" :stroke="`url(#gg-${uid})`" stroke-width="20" stroke-linecap="round" />
                <line v-if="tick" :x1="tick.x1" :y1="tick.y1" :x2="tick.x2" :y2="tick.y2" :stroke="BRAND.coral" stroke-width="3" stroke-linecap="round" />
            </svg>
            <div class="absolute inset-x-0 bottom-0 text-center">
                <div class="text-[2.1rem] leading-none font-extrabold text-navy tabular" dir="ltr">{{ isNum(value) ? Number(value).toFixed(1) : '–' }}<span class="text-lg">%</span></div>
                <div class="mt-1 text-xs font-semibold text-muted">{{ label }}</div>
            </div>
        </div>
        <div v-if="isNum(previous)" class="mt-3 flex items-center justify-center gap-1.5 text-xs text-ink-2">
            <span class="h-3 w-[3px] rounded bg-coral" />{{ t('Last year') }} <bdi class="font-bold tabular">{{ Number(previous).toFixed(1) }}%</bdi>
        </div>
    </div>
</template>
