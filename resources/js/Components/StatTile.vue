<script setup>
import Delta from './Delta.vue';
import { useI18n } from '../lib/i18n';

// Stat tile: label · value · change vs last year (+ last year's value).
defineProps({
    label: String,
    value: String,
    unit: { type: String, default: '' },
    current: { default: null },
    previous: { default: null },
    previousLabel: { type: String, default: '' },
    kind: { type: String, default: 'pct' },
});

const { t } = useI18n();
</script>

<template>
    <div class="card flex flex-col gap-2 p-5">
        <div class="text-sm font-medium text-secondary">{{ label }}</div>
        <div class="flex">
            <span dir="ltr" class="flex items-baseline gap-1.5">
                <span class="text-[1.75rem] leading-none font-semibold tracking-tight text-navy tabular">{{ value }}</span>
                <span v-if="unit" class="text-sm font-medium text-grey">{{ unit }}</span>
            </span>
        </div>
        <div class="flex flex-wrap items-center gap-x-2 text-xs text-grey">
            <Delta :current="current" :previous="previous" :kind="kind" />
            <span v-if="previousLabel">{{ t('LY') }} <span class="tabular" dir="ltr">{{ previousLabel }}</span></span>
        </div>
    </div>
</template>
