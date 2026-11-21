<script setup>
import Icon from './Icon.vue';
import Delta from './Delta.vue';
import Sparkline from './Charts/Sparkline.vue';
import { useI18n } from '../lib/i18n';

// KPI card from the web identity: label + coral-tint icon tile, big value, change vs last year, sparkline.
defineProps({
    label: String,
    icon: String,
    value: String,
    unit: { type: String, default: '' },
    current: { default: null },
    previous: { default: null },
    previousLabel: { type: String, default: '' },
    kind: { type: String, default: 'pct' },
    spark: { type: Array, default: null },
    note: { type: String, default: '' },
});

const { t } = useI18n();
</script>

<template>
    <div class="card rise flex flex-col p-5">
        <div class="flex items-start justify-between gap-3">
            <span class="text-[13px] font-semibold text-ink-2">{{ label }}</span>
            <span class="grid size-10 place-items-center rounded-xl bg-coral-tint text-coral"><Icon :name="icon" :size="19" /></span>
        </div>
        <div class="mt-2 flex">
            <span dir="ltr" class="flex items-baseline gap-1.5">
                <span class="font-sans text-[1.9rem] leading-none font-extrabold tracking-tight text-navy tabular">{{ value }}</span>
                <span v-if="unit" class="text-xs font-bold text-navy/70">{{ unit }}</span>
            </span>
        </div>
        <div v-if="previous !== null || previousLabel || note" class="mt-2 flex flex-wrap items-center gap-x-2 text-xs text-muted">
            <Delta v-if="previous !== null" :current="current" :previous="previous" :kind="kind" />
            <span v-if="previousLabel">{{ t('LY') }} <bdi class="tabular">{{ previousLabel }}</bdi></span>
            <span v-if="note">{{ note }}</span>
        </div>
        <div v-if="spark" class="mt-auto pt-3"><Sparkline :values="spark" /></div>
    </div>
</template>
