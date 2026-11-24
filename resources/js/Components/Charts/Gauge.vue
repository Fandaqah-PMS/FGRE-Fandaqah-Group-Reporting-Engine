<script setup>
import { computed } from 'vue';
import { BRAND } from '../../lib/palette';
import { isNum } from '../../lib/format';
import { useI18n } from '../../lib/i18n';

/**
 * Half donut (PMS "guest rating" style): thick rounded arc, navy → coral gradient across
 * the filled part, warm track, value centred inside the arc with a caption under it.
 * value is on a 0–max scale (default 0–100 %).
 */
const props = defineProps({
    value: { default: null },
    previous: { default: null },
    label: { type: String, default: '' },
    max: { type: Number, default: 100 },
    suffix: { type: String, default: '%' },
    digits: { type: Number, default: 1 },
    compact: { type: Boolean, default: false },
});

const { t } = useI18n();
const uid = Math.random().toString(36).slice(2, 8);
const R = 80, CX = 110, CY = 102, W = 22;
const share = (v) => Math.max(0, Math.min(1, Number(v) / props.max));
const point = (s) => {
    const a = Math.PI * (1 - s);
    return [CX + R * Math.cos(a), CY - R * Math.sin(a)];
};
const arc = (s) => {
    const [x, y] = point(s);
    return `M${CX - R},${CY} A${R},${R} 0 0 1 ${x.toFixed(2)},${y.toFixed(2)}`;
};
const fill = computed(() => (isNum(props.value) && share(props.value) > 0.004 ? arc(share(props.value)) : ''));
</script>

<template>
    <div>
        <div class="relative mx-auto w-full" :class="compact ? 'max-w-[190px]' : 'max-w-[250px]'">
            <svg viewBox="0 0 220 116" class="w-full overflow-visible" role="img" :aria-label="`${label} ${value ?? ''}${suffix}`">
                <defs>
                    <linearGradient :id="`gg-${uid}`" x1="0" y1="0" x2="1" y2="0">
                        <stop offset="0" :stop-color="BRAND.navy" />
                        <stop offset="1" :stop-color="BRAND.coral" />
                    </linearGradient>
                </defs>
                <path :d="arc(1)" fill="none" :stroke="BRAND.neutral" :stroke-width="W" stroke-linecap="round" />
                <path v-if="fill" :d="fill" fill="none" :stroke="`url(#gg-${uid})`" :stroke-width="W" stroke-linecap="round" />
            </svg>
            <div class="absolute inset-x-0 bottom-[6%] text-center">
                <div class="leading-none font-semibold text-ink tabular" :class="compact ? 'text-[1.35rem]' : 'text-[1.9rem]'" dir="ltr">
                    {{ isNum(value) ? Number(value).toFixed(digits) : '–' }}<span v-if="suffix" class="font-medium text-ink-2" :class="compact ? 'text-xs' : 'text-base'">{{ suffix }}</span>
                </div>
                <div v-if="label" class="mt-1.5 text-xs font-medium text-ink-2">{{ label }}</div>
            </div>
        </div>
        <div v-if="isNum(previous)" class="mt-3 text-center text-xs text-muted">
            {{ t('Last year') }} <bdi class="font-semibold text-ink-2 tabular">{{ Number(previous).toFixed(digits) }}{{ suffix }}</bdi>
        </div>
    </div>
</template>
