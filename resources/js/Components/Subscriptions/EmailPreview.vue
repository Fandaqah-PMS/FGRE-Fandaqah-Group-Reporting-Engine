<script setup>
import { computed } from 'vue';
import { useI18n } from '../../lib/i18n';
import { BRAND } from '../../lib/palette';

// A miniature of the email (not real figures): shows what the chosen options include.
const props = defineProps({ frequency: String, content: String, attention: Boolean, hotelCount: Number });
const { t } = useI18n();

const charts = computed(() => props.content !== 'tables');
const tables = computed(() => props.content !== 'charts');
const hotels = computed(() => Math.max(1, Math.min(props.hotelCount || 1, 5)));
const title = computed(() => ({ daily: t('Daily group report'), weekly: t('Weekly group report'), monthly: t('Monthly group report') })[props.frequency]);
const bars = [[92, 80], [74, 70], [63, 69], [55, 47], [38, 41]];
const days = computed(() => (props.frequency === 'daily' ? [] : props.frequency === 'weekly' ? [60, 72, 55, 80, 90, 66, 75] : [50, 62, 58, 71, 66, 80, 74, 69, 85, 77, 63, 70, 88, 92]));
</script>

<template>
    <div class="overflow-hidden rounded-2xl border border-line bg-white text-[9px] shadow-sm" aria-hidden="true">
        <div class="bg-navy px-3 py-2.5">
            <div class="font-bold text-coral">{{ title }}</div>
            <div class="mt-1 h-2 w-24 rounded bg-white/80" />
            <div class="mt-1 h-1.5 w-16 rounded bg-white/30" />
        </div>
        <div class="space-y-3 p-3">
            <template v-if="charts">
                <div class="grid grid-cols-4 gap-1.5">
                    <div v-for="i in 4" :key="i" class="rounded-md bg-paper p-1.5">
                        <div class="h-1 w-6 rounded bg-muted/50" />
                        <div class="mt-1 h-2 w-8 rounded bg-navy" />
                        <div class="mt-1 h-1 w-5 rounded" :class="i === 3 ? 'bg-danger/60' : 'bg-success/60'" />
                    </div>
                </div>
                <div v-if="days.length" class="flex h-12 items-end gap-[3px] border-b border-line" dir="ltr">
                    <div v-for="(d, i) in days" :key="i" class="flex flex-1 items-end gap-px">
                        <span class="flex-1 rounded-t-sm" :style="{ height: `${d * 0.42}px`, background: BRAND.navy }" />
                        <span class="w-[30%] rounded-t-sm" :style="{ height: `${d * 0.38}px`, background: BRAND.lightGrey }" />
                    </div>
                </div>
                <div class="space-y-1.5">
                    <div v-for="(b, i) in bars.slice(0, hotels)" :key="i">
                        <div class="h-1.5 rounded-full" :style="{ width: `${b[0]}%`, background: BRAND.navy }" />
                        <div class="mt-0.5 h-1 rounded-full" :style="{ width: `${b[1]}%`, background: BRAND.lightGrey }" />
                    </div>
                </div>
            </template>
            <div v-if="tables" class="overflow-hidden rounded-md border border-line">
                <div class="flex bg-navy">
                    <span class="h-3 flex-[1.4]" /><span v-for="i in hotels" :key="i" class="h-3 flex-1 border-s border-white/10" /><span class="h-3 flex-1 bg-coral" />
                </div>
                <div v-for="r in 5" :key="r" class="flex border-t border-line">
                    <span class="flex-[1.4] p-1"><span class="block h-1 w-8 rounded bg-muted/50" /></span>
                    <span v-for="i in hotels" :key="i" class="flex-1 p-1"><span class="ms-auto block h-1 w-5 rounded bg-navy/70" /></span>
                    <span class="flex-1 bg-coral-tint p-1"><span class="ms-auto block h-1 w-5 rounded bg-navy" /></span>
                </div>
            </div>
            <div v-if="tables" class="inline-flex items-center gap-1 rounded bg-success-tint px-1.5 py-0.5 font-bold text-success">CSV</div>
            <div v-if="attention" class="space-y-1">
                <div class="rounded-md border border-line">
                    <div class="flex items-center justify-between rounded-t-md bg-coral-tint px-1.5 py-1"><span class="h-1.5 w-12 rounded bg-navy/70" /><span class="size-1.5 rounded-full bg-coral" /></div>
                    <div class="px-1.5 py-1"><span class="block h-1 w-20 rounded bg-muted/50" /></div>
                </div>
            </div>
            <div class="inline-block rounded-md bg-coral px-2 py-1 font-bold text-white">{{ t('Open the full report') }}</div>
        </div>
    </div>
    <ul class="mt-4 space-y-1.5 text-xs text-ink-2">
        <li v-if="charts" class="flex gap-2"><span class="mt-1 size-2 shrink-0 rounded-sm bg-navy" />{{ t('KPI tiles and bar charts per hotel.') }}</li>
        <li v-if="days.length && charts" class="flex gap-2"><span class="mt-1 size-2 shrink-0 rounded-sm bg-navy" />{{ t('Daily revenue across the period.') }}</li>
        <li v-if="tables" class="flex gap-2"><span class="mt-1 size-2 shrink-0 rounded-sm bg-coral" />{{ t('Hotels side by side, plus an Excel (CSV) file.') }}</li>
        <li v-if="attention" class="flex gap-2"><span class="mt-1 size-2 shrink-0 rounded-sm bg-peach" />{{ t('What needs attention, by hotel.') }}</li>
    </ul>
</template>
