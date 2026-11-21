<script setup>
import Delta from './Delta.vue';
import Sparkline from './Charts/Sparkline.vue';
import { BRAND } from '../lib/palette';
import { useI18n } from '../lib/i18n';

/**
 * KPI card (PMS dashboard style): coloured dot + label, big value with a change pill,
 * a sub-line, and a sparkline. `highlight` = soft coral card with a progress bar.
 */
defineProps({
    label: String,
    dot: { type: String, default: BRAND.coral },
    icon: String,   // kept for callers; the PMS style uses the dot
    value: String,
    unit: { type: String, default: '' },
    current: { default: null },
    previous: { default: null },
    previousLabel: { type: String, default: '' },
    kind: { type: String, default: 'pct' },
    spark: { type: Array, default: null },
    note: { type: String, default: '' },
    highlight: { type: Boolean, default: false },
    progress: { type: Number, default: null },
});

const { t } = useI18n();
</script>

<template>
    <div class="card rise flex flex-col p-5"
         :class="highlight && '!border-coral/30 bg-[linear-gradient(160deg,#FDEBE9_0%,#FFFFFF_65%)]'">
        <div class="flex items-center gap-2 text-[13px] font-semibold text-ink-2">
            <span class="size-2 shrink-0 rounded-full" :style="{ background: dot }" />{{ label }}
        </div>
        <div class="mt-2.5 flex flex-wrap items-center gap-x-2 gap-y-1">
            <span dir="ltr" class="flex items-baseline gap-1">
                <span class="text-[1.6rem] leading-none font-semibold tracking-tight text-navy tabular">{{ value }}</span>
                <span v-if="unit" class="text-[11px] font-medium text-muted">{{ unit }}</span>
            </span>
            <Delta v-if="previous !== null" :current="current" :previous="previous" :kind="kind" />
        </div>
        <div v-if="previousLabel || note" class="mt-1.5 text-xs text-muted">
            <template v-if="previousLabel">{{ t('Last year') }} <bdi class="tabular">{{ previousLabel }}</bdi></template>
            <template v-else>{{ note }}</template>
        </div>
        <div v-if="spark" class="mt-auto pt-3"><Sparkline :values="spark" /></div>
        <div v-else-if="progress !== null" class="mt-auto pt-4">
            <div class="h-2 overflow-hidden rounded-full bg-coral-tint">
                <div class="h-full rounded-full bg-coral transition-all" :style="{ width: `${Math.max(0, Math.min(100, progress))}%` }" />
            </div>
        </div>
    </div>
</template>
