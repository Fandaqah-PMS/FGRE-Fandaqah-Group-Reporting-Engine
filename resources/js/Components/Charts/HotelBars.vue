<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { THIS_YEAR, LAST_YEAR } from '../../lib/palette';
import { delta, isNum } from '../../lib/format';
import { useI18n } from '../../lib/i18n';

/**
 * This year vs last year, one row per hotel, sorted by this year's value.
 * rows: [{ id, name, name_ar, ty, ly }]. Bars grow from the inline-start edge,
 * so the chart mirrors naturally in Arabic.
 */
const props = defineProps({
    rows: { type: Array, required: true },
    format: { type: Function, required: true },
    deltaKind: { type: String, default: 'pct' },
});

const { t, name } = useI18n();
const hover = ref(null);

const sorted = computed(() => [...props.rows].sort((a, b) => Number(b.ty ?? -1) - Number(a.ty ?? -1)));
const max = computed(() => Math.max(1e-9, ...props.rows.flatMap((r) => [Number(r.ty ?? 0), Number(r.ly ?? 0)])));
const width = (v) => (isNum(v) ? `${Math.max(0, (Number(v) / max.value) * 100)}%` : '0%');
</script>

<template>
    <div>
        <div class="mb-4 flex flex-wrap gap-4 text-xs text-secondary" role="list" :aria-label="t('Legend')">
            <span class="flex items-center gap-1.5" role="listitem"><span class="h-2.5 w-3.5 rounded-[2px]" :style="{ background: THIS_YEAR }" />{{ t('This year') }}</span>
            <span class="flex items-center gap-1.5" role="listitem"><span class="h-2.5 w-3.5 rounded-[2px]" :style="{ background: LAST_YEAR }" />{{ t('Last year') }}</span>
        </div>

        <ul class="space-y-3.5">
            <li v-for="row in sorted" :key="row.id"
                class="relative grid grid-cols-[minmax(7rem,11rem)_1fr] items-center gap-3 rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-coral/40"
                tabindex="0"
                @pointerenter="hover = row.id" @pointerleave="hover = null" @focus="hover = row.id" @blur="hover = null">
                <Link :href="`/hotels/${row.id}`" class="truncate text-sm font-medium text-navy hover:text-coral-text" :title="name(row)">{{ name(row) }}</Link>

                <div class="flex flex-col gap-[2px]">
                    <div class="flex items-center gap-2">
                        <div class="h-3.5 rounded-e-[4px] transition-[filter]" :class="hover === row.id && 'brightness-110'"
                             :style="{ width: width(row.ty), background: THIS_YEAR }" />
                        <span class="text-xs font-semibold text-navy tabular" dir="ltr">{{ format(row.ty) }}</span>
                    </div>
                    <div class="h-2 rounded-e-[4px]" :style="{ width: width(row.ly), background: LAST_YEAR }" />
                </div>

                <!-- Tooltip: values lead, labels follow; line keys -->
                <div v-if="hover === row.id" role="tooltip"
                     class="pointer-events-none absolute end-0 top-full z-10 mt-1 rounded-xl border border-line bg-white px-3 py-2 text-xs shadow-lg">
                    <div class="mb-1 font-medium text-secondary">{{ name(row) }}</div>
                    <div class="flex items-center gap-2"><span class="h-0.5 w-3" :style="{ background: THIS_YEAR }" /><b class="text-navy tabular" dir="ltr">{{ format(row.ty) }}</b><span class="text-grey">{{ t('This year') }}</span></div>
                    <div class="flex items-center gap-2"><span class="h-0.5 w-3" :style="{ background: LAST_YEAR }" /><b class="text-navy tabular" dir="ltr">{{ format(row.ly) }}</b><span class="text-grey">{{ t('Last year') }}</span></div>
                    <div v-if="delta(row.ty, row.ly, deltaKind)" class="mt-1 text-grey" dir="ltr">{{ delta(row.ty, row.ly, deltaKind).label }}</div>
                </div>
            </li>
        </ul>
    </div>
</template>
