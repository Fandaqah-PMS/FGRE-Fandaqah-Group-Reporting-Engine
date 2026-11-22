<script setup>
import { computed } from 'vue';
import { int } from '../../lib/format';
import { useI18n } from '../../lib/i18n';

/**
 * One whole split into parts (PMS "room service condition" style): a rounded segmented
 * bar, then a tile per part with its value and share. parts: [{ key, label, value, color }]
 */
const props = defineProps({ parts: { type: Array, required: true }, format: { type: Function, default: int } });
const { t } = useI18n();
const total = computed(() => props.parts.reduce((s, p) => s + Math.max(0, Number(p.value) || 0), 0));
const share = (v) => (total.value > 0 ? (Math.max(0, Number(v) || 0) / total.value) * 100 : 0);
const shown = computed(() => props.parts.filter((p) => Number(p.value) > 0));
</script>

<template>
    <div>
        <div class="flex h-4 gap-1" role="img" :aria-label="parts.map((p) => `${p.label} ${p.value}`).join(', ')">
            <div v-for="p in shown" :key="p.key" class="h-full rounded-full transition-all" :style="{ width: `${share(p.value)}%`, background: p.color }" :title="`${p.label}: ${format(p.value)}`" />
        </div>
        <ul class="mt-4 grid grid-cols-[repeat(auto-fill,minmax(8.5rem,1fr))] gap-2.5" :aria-label="t('Legend')">
            <li v-for="p in parts" :key="p.key" class="rounded-xl bg-paper px-3.5 py-3">
                <div class="flex items-center gap-1.5 text-xs font-semibold text-ink-2">
                    <span class="size-2 shrink-0 rounded-full" :style="{ background: p.color }" />{{ p.label }}
                </div>
                <div class="mt-1 flex items-baseline gap-1.5">
                    <bdi class="text-lg font-semibold text-ink tabular">{{ format(p.value) }}</bdi>
                    <bdi class="text-xs text-muted tabular">{{ share(p.value).toFixed(0) }}%</bdi>
                </div>
            </li>
        </ul>
    </div>
</template>
