<script setup>
import { computed, ref } from 'vue';
import { SERIES } from '../../lib/palette';
import { compact, isNum, money } from '../../lib/format';
import { useI18n } from '../../lib/i18n';

/**
 * Revenue mix per hotel: one 100% bar split into rooms / F&B / other, with a
 * 2px surface gap between segments. Labels only where they fit; every value
 * is in the tooltip and the table below the chart.
 */
const props = defineProps({ rows: { type: Array, required: true } });   // [{ id, name, name_ar, rooms, fnb, other }]

const { t, name } = useI18n();
const hover = ref(null);

const parts = computed(() => [
    { key: 'rooms', label: t('Rooms'), color: SERIES[0] },
    { key: 'fnb', label: t('F&B'), color: SERIES[1] },
    { key: 'other', label: t('Other'), color: SERIES[2] },
]);

const bars = computed(() => props.rows.map((r) => {
    const total = parts.value.reduce((s, p) => s + (isNum(r[p.key]) ? Number(r[p.key]) : 0), 0);
    return {
        ...r,
        total,
        segments: parts.value.map((p) => ({ ...p, value: Number(r[p.key] ?? 0), share: total > 0 ? (Number(r[p.key] ?? 0) / total) * 100 : 0 })),
    };
}).sort((a, b) => b.total - a.total));
</script>

<template>
    <div>
        <div class="mb-4 flex flex-wrap gap-4 text-xs text-secondary" role="list" :aria-label="t('Legend')">
            <span v-for="p in parts" :key="p.key" class="flex items-center gap-1.5" role="listitem">
                <span class="h-2.5 w-3.5 rounded-[2px]" :style="{ background: p.color }" />{{ p.label }}
            </span>
        </div>

        <ul class="space-y-3">
            <li v-for="bar in bars" :key="bar.id" class="grid grid-cols-[minmax(7rem,11rem)_1fr_auto] items-center gap-3">
                <span class="truncate text-sm font-medium text-navy" :title="name(bar)">{{ name(bar) }}</span>
                <div class="relative flex h-5 gap-[2px]">
                    <div v-for="(seg, i) in bar.segments" :key="seg.key" tabindex="0"
                         class="relative h-full outline-none transition-[filter] focus-visible:ring-2 focus-visible:ring-coral/40"
                         :class="[i === 0 && 'rounded-s-[4px]', i === bar.segments.length - 1 && 'rounded-e-[4px]', hover === `${bar.id}-${seg.key}` && 'brightness-110']"
                         :style="{ width: `${seg.share}%`, background: seg.color, display: seg.share > 0 ? 'block' : 'none' }"
                         @pointerenter="hover = `${bar.id}-${seg.key}`" @pointerleave="hover = null"
                         @focus="hover = `${bar.id}-${seg.key}`" @blur="hover = null">
                        <span v-if="seg.key === 'rooms' && seg.share >= 18" class="absolute inset-y-0 start-2 flex items-center text-[11px] font-semibold text-white tabular" dir="ltr">
                            {{ seg.share.toFixed(0) }}%
                        </span>
                        <div v-if="hover === `${bar.id}-${seg.key}`" role="tooltip"
                             class="pointer-events-none absolute start-0 -top-2 z-10 -translate-y-full whitespace-nowrap rounded-xl border border-line bg-white px-3 py-2 text-xs shadow-lg">
                            <div class="font-medium text-secondary">{{ name(bar) }} · {{ seg.label }}</div>
                            <div class="flex items-center gap-2"><span class="h-0.5 w-3" :style="{ background: seg.color }" />
                                <b class="text-navy tabular" dir="ltr">{{ money(seg.value, 0) }}</b>
                                <span class="text-grey tabular" dir="ltr">{{ seg.share.toFixed(1) }}%</span>
                            </div>
                        </div>
                    </div>
                </div>
                <span class="text-xs font-semibold text-navy tabular" dir="ltr">{{ compact(bar.total) }}</span>
            </li>
        </ul>
    </div>
</template>
