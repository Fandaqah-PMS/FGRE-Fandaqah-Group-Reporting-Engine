<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { coralRamp } from '../../lib/palette';
import { isNum, shortDate } from '../../lib/format';
import { useI18n } from '../../lib/i18n';

/**
 * Occupancy heatmap: hotels × days, one coral ramp (light = low, coral = high).
 * Values show on hover/focus; the scale is printed below and the table view has every number.
 */
const props = defineProps({ data: { type: Object, required: true } });   // { dates, hotels: [{ id, name, name_ar, values: {date: pct} }] }

const { t, name, locale } = useI18n();
const active = ref(null);
const values = computed(() => props.data.hotels.flatMap((h) => Object.values(h.values)).filter(isNum).map(Number));
const min = computed(() => Math.min(...values.value, 100));
const max = computed(() => Math.max(...values.value, 0));
const scale = (v) => (max.value - min.value > 0 ? (Number(v) - min.value) / (max.value - min.value) : 1);
</script>

<template>
    <div>
        <div class="overflow-x-auto">
            <table class="w-full border-separate border-spacing-[3px]" dir="ltr">
                <thead>
                    <tr>
                        <th class="w-44" />
                        <th v-for="(d, i) in data.dates" :key="d" class="text-[10px] font-semibold text-muted" :class="i % 2 && 'opacity-0 sm:opacity-100'">{{ shortDate(d, locale).split(' ')[0] }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="h in data.hotels" :key="h.id">
                        <th class="w-44 pe-2 text-start text-xs leading-tight font-semibold text-navy" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
                            <Link :href="`/hotels/${h.id}`" class="hover:text-coral">{{ name(h) }}</Link>
                        </th>
                        <td v-for="d in data.dates" :key="d" class="relative h-8 min-w-7 rounded-md outline-none" tabindex="0"
                            :style="{ background: isNum(h.values[d]) ? coralRamp(scale(h.values[d])) : '#F3F1EC' }"
                            @pointerenter="active = `${h.id}|${d}`" @pointerleave="active = null" @focus="active = `${h.id}|${d}`" @blur="active = null">
                            <div v-if="active === `${h.id}|${d}`" role="tooltip"
                                 class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1.5 -translate-x-1/2 rounded-lg bg-navy px-2.5 py-1.5 text-[11px] whitespace-nowrap text-white shadow-lg">
                                <b class="tabular">{{ isNum(h.values[d]) ? `${Number(h.values[d]).toFixed(1)}%` : t('No report') }}</b>
                                <span class="opacity-70"> · {{ shortDate(d, locale) }}</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="mt-3 flex items-center gap-2 text-[11px] font-semibold text-muted" dir="ltr">
            <bdi class="tabular">{{ min.toFixed(0) }}%</bdi>
            <span class="h-2 w-32 rounded-full" :style="{ background: `linear-gradient(90deg, ${coralRamp(0)}, ${coralRamp(1)})` }" />
            <bdi class="tabular">{{ max.toFixed(0) }}%</bdi>
            <span class="ms-3 inline-block size-2.5 rounded-sm bg-[#F3F1EC]" />{{ t('No report') }}
        </div>
    </div>
</template>
