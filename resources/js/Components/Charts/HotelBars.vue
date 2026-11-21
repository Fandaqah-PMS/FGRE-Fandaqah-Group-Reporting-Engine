<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { THIS_YEAR, LAST_YEAR } from '../../lib/palette';
import { delta, isNum } from '../../lib/format';
import { useI18n } from '../../lib/i18n';
import Delta from '../Delta.vue';

/**
 * Ranked bars, one row per hotel: this year (coral) over last year (grey).
 * rows: [{ id, name, name_ar, ty, ly }]. Click a hotel to switch to it.
 */
const props = defineProps({
    rows: { type: Array, required: true },
    format: { type: Function, required: true },
    deltaKind: { type: String, default: 'pct' },
    showLy: { type: Boolean, default: true },
    color: { type: String, default: THIS_YEAR },
    reference: { default: null },          // e.g. the group figure, drawn as a dashed coral line
    referenceLabel: { type: String, default: '' },
});

const { t, name } = useI18n();
const hover = ref(null);
const sorted = computed(() => [...props.rows].sort((a, b) => Number(b.ty ?? -1) - Number(a.ty ?? -1)));
const max = computed(() => Math.max(1e-9, Number(props.reference ?? 0), ...props.rows.flatMap((r) => [Number(r.ty ?? 0), Number(r.ly ?? 0)])));
const width = (v) => (isNum(v) && Number(v) > 0 ? `${(Number(v) / max.value) * 100}%` : '0%');
</script>

<template>
    <div>
        <div v-if="showLy || isNum(reference)" class="mb-4 flex flex-wrap gap-4 text-xs font-semibold text-ink-2" role="list" :aria-label="t('Legend')">
            <template v-if="showLy">
                <span class="flex items-center gap-1.5" role="listitem"><span class="size-2.5 rounded-[3px]" :style="{ background: color }" />{{ t('This year') }}</span>
                <span class="flex items-center gap-1.5" role="listitem"><span class="size-2.5 rounded-[3px]" :style="{ background: LAST_YEAR }" />{{ t('Last year') }}</span>
            </template>
            <span v-if="isNum(reference)" class="flex items-center gap-1.5" role="listitem">
                <span class="h-3 border-s-2 border-dashed border-coral" />{{ referenceLabel || t('Group') }} <bdi class="tabular text-navy">{{ format(reference) }}</bdi>
            </span>
        </div>
        <ul class="space-y-4">
            <li v-for="(row, i) in sorted" :key="row.id" class="relative outline-none" tabindex="0"
                @pointerenter="hover = row.id" @pointerleave="hover = null" @focus="hover = row.id" @blur="hover = null">
                <div class="mb-1.5 flex items-center gap-2 text-[13px]">
                    <span class="grid size-5 place-items-center rounded-md bg-neutral text-[10px] font-bold text-ink-2">{{ i + 1 }}</span>
                    <Link :href="`/hotels/${row.id}`" class="min-w-0 flex-1 truncate font-semibold text-navy hover:text-coral">{{ name(row) }}</Link>
                    <bdi class="font-bold text-navy tabular">{{ format(row.ty) }}</bdi>
                    <Delta v-if="showLy" :current="row.ty" :previous="row.ly" :kind="deltaKind" />
                </div>
                <div class="relative flex flex-col gap-[3px]">
                    <span v-if="isNum(reference)" class="pointer-events-none absolute -inset-y-1 z-[1] border-s-2 border-dashed border-coral" :style="{ insetInlineStart: width(reference) }" aria-hidden="true" />
                    <div class="h-3 rounded-e-[4px] transition-all" :class="hover === row.id && 'brightness-105'" :style="{ width: width(row.ty), background: color }" />
                    <div v-if="showLy" class="h-1.5 rounded-e-[4px]" :style="{ width: width(row.ly), background: LAST_YEAR }" />
                </div>
                <div v-if="hover === row.id && showLy" role="tooltip" class="pointer-events-none absolute end-0 top-full z-10 mt-1 rounded-xl border border-line bg-white px-3 py-2 text-xs shadow-lg">
                    <div class="flex items-center gap-2"><span class="h-[3px] w-3 rounded" :style="{ background: THIS_YEAR }" /><b class="text-navy tabular" dir="ltr">{{ format(row.ty) }}</b><span class="text-muted">{{ t('This year') }}</span></div>
                    <div class="flex items-center gap-2"><span class="h-[3px] w-3 rounded" :style="{ background: LAST_YEAR }" /><b class="text-navy tabular" dir="ltr">{{ format(row.ly) }}</b><span class="text-muted">{{ t('Last year') }}</span></div>
                    <div v-if="delta(row.ty, row.ly, deltaKind)" class="mt-1 text-muted" dir="ltr">{{ delta(row.ty, row.ly, deltaKind).label }}</div>
                </div>
            </li>
        </ul>
    </div>
</template>
