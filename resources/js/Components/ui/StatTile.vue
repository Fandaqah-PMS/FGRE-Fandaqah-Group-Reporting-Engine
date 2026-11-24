<script setup>
import Delta from '../Delta.vue';
import IconTile from './IconTile.vue';

// A small figure inside a card (e.g. arrivals, departures): inset tile, icon, value, label, change.
defineProps({
    label: { type: String, required: true },
    value: { type: String, required: true },
    icon: { type: String, default: null },
    tone: { type: String, default: 'coral' },
    current: { default: null },
    previous: { default: null },
    kind: { type: String, default: 'pct' },
});
</script>

<template>
    <div class="rounded-2xl bg-paper p-4">
        <IconTile v-if="icon" :icon="icon" :tone="tone" size="sm" />
        <div class="mt-3 text-xl leading-none font-semibold text-ink tabular" dir="ltr">{{ value }}</div>
        <div class="mt-1 text-xs font-semibold text-ink-2">{{ label }}</div>
        <div v-if="previous !== null" class="mt-1.5"><Delta :current="current" :previous="previous" :kind="kind" /></div>
    </div>
</template>
