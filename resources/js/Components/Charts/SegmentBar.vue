<script setup>
import { computed } from 'vue';
import { int } from '../../lib/format';
import { useI18n } from '../../lib/i18n';

/**
 * One whole split into parts (e.g. rooms: sold / comp & house / vacant / out of order).
 * Tall segmented bar with 2px gaps; the legend carries every value, so labels never crowd.
 * parts: [{ key, label, value, color }]
 */
const props = defineProps({ parts: { type: Array, required: true }, format: { type: Function, default: int } });
const { t } = useI18n();
const total = computed(() => props.parts.reduce((s, p) => s + Math.max(0, Number(p.value) || 0), 0));
const shown = computed(() => props.parts.filter((p) => Number(p.value) > 0));
</script>

<template>
    <div>
        <div class="flex h-9 gap-[2px] overflow-hidden rounded-[10px]" role="img" :aria-label="parts.map((p) => `${p.label} ${p.value}`).join(', ')">
            <div v-for="p in shown" :key="p.key" class="h-full transition-all" :style="{ width: `${(Number(p.value) / total) * 100}%`, background: p.color }" :title="`${p.label}: ${format(p.value)}`" />
        </div>
        <ul class="mt-4 grid grid-cols-[repeat(auto-fill,minmax(10.5rem,1fr))] gap-x-5 gap-y-2.5" :aria-label="t('Legend')">
            <li v-for="p in parts" :key="p.key" class="flex items-center gap-2 text-[13px]">
                <span class="size-3 shrink-0 rounded-[4px]" :style="{ background: p.color }" />
                <span class="flex-1 font-medium text-ink-2">{{ p.label }}</span>
                <bdi class="font-bold text-navy tabular">{{ format(p.value) }}</bdi>
            </li>
        </ul>
    </div>
</template>
