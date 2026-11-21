<script setup>
import { computed } from 'vue';
import { change, tone } from '../../lib/reports';
import { isNum } from '../../lib/format';
import { BRAND } from '../../lib/palette';
import { useI18n } from '../../lib/i18n';

/**
 * Hotel vs group: for each row, the hotel's figure next to the group benchmark (group
 * ratio for KPIs, average hotel for totals), the best hotel, and the comparison basis,
 * with a bullet bar: navy bar = hotel, coral tick = group, grey tick = basis.
 * get(row) returns { value, group, best: { value, name }, base }.
 */
const props = defineProps({
    sections: { type: Array, required: true },
    hotelName: { type: String, required: true },
    get: { type: Function, required: true },
    basisLabel: { type: String, required: true },
    showBase: { type: Boolean, default: true },
});
const { t } = useI18n();

const scale = (d) => Math.max(1e-9, ...[d.value, d.group, d.best?.value, d.base].filter(isNum).map((v) => Math.abs(Number(v))));
const pos = (v, d) => `${Math.min(100, (Math.abs(Number(v)) / scale(d)) * 100)}%`;
const toneCls = (row, ch) => (ch ? (tone(row, ch.d) === 'good' ? 'bg-success-tint text-success' : tone(row, ch.d) === 'bad' ? 'bg-coral-tint text-coral-dark' : 'bg-neutral text-ink-2') : '');
const data = computed(() => props.sections.map((s) => ({ ...s, rows: s.rows.map((r) => ({ r, d: props.get(r) })) })));
</script>

<template>
    <div>
        <div class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-ink-2">
            <span class="flex items-center gap-1.5"><span class="h-2 w-5 rounded-full" :style="{ background: BRAND.navy }" />{{ hotelName }}</span>
            <span class="flex items-center gap-1.5"><span class="h-3 border-s-2 border-coral" />{{ t('Group (average hotel for totals)') }}</span>
            <span v-if="showBase" class="flex items-center gap-1.5"><span class="h-3 border-s-2 border-muted" />{{ basisLabel }}</span>
        </div>
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[60rem] text-sm tabular">
                    <thead>
                        <tr class="bg-navy text-white">
                            <th class="px-4 py-3 text-start text-xs font-bold">{{ t('Measure') }}</th>
                            <th class="px-3 py-3 text-end text-xs font-bold">{{ hotelName }}</th>
                            <th class="w-56 px-3 py-3 text-xs font-bold"></th>
                            <th class="px-3 py-3 text-end text-xs font-bold">{{ t('Group') }}</th>
                            <th class="px-3 py-3 text-end text-xs font-bold">{{ t('vs group') }}</th>
                            <th class="px-3 py-3 text-end text-xs font-bold">{{ t('Best hotel') }}</th>
                            <th v-if="showBase" class="px-3 py-3 text-end text-xs font-bold">{{ basisLabel }}</th>
                            <th v-if="showBase" class="px-3 py-3 text-end text-xs font-bold">{{ t('vs :basis', { basis: basisLabel }) }}</th>
                        </tr>
                    </thead>
                    <tbody v-for="s in data" :key="s.key">
                        <tr><td :colspan="showBase ? 8 : 6" class="bg-paper px-4 py-2 text-[11px] font-bold tracking-wider text-coral uppercase">{{ s.title }}</td></tr>
                        <tr v-for="{ r, d } in s.rows" :key="r.key" class="border-t border-line">
                            <td class="px-4 py-2.5"><span class="me-2 text-[11px] font-bold text-muted" dir="ltr">{{ r.code }}</span><span class="font-semibold text-navy" :title="r.label">{{ r.name ?? r.label }}</span></td>
                            <td class="px-3 text-end font-extrabold text-navy"><bdi>{{ r.f(d.value) }}</bdi></td>
                            <td class="px-3">
                                <div class="relative h-3 rounded-full bg-neutral" dir="ltr">
                                    <div class="absolute inset-y-0 left-0 rounded-full bg-navy" :style="{ width: isNum(d.value) ? pos(d.value, d) : '0' }" />
                                    <span v-if="isNum(d.base)" class="absolute -inset-y-1 w-[3px] rounded bg-muted" :style="{ left: pos(d.base, d) }" />
                                    <span v-if="isNum(d.group)" class="absolute -inset-y-1.5 w-[3px] rounded bg-coral" :style="{ left: pos(d.group, d) }" />
                                </div>
                            </td>
                            <td class="px-3 text-end text-ink-2"><bdi>{{ r.f(d.group) }}</bdi></td>
                            <td class="px-3 text-end">
                                <template v-for="ch in [change(r, d.value, d.group)]" :key="'g'">
                                    <bdi v-if="ch" class="chip !px-2 !py-0.5" :class="toneCls(r, ch)">{{ ch.label }}</bdi>
                                </template>
                            </td>
                            <td class="px-3 text-end text-xs text-ink-2">
                                <template v-if="d.best"><bdi class="font-bold text-navy">{{ r.f(d.best.value) }}</bdi><div class="truncate">{{ d.best.name }}</div></template>
                            </td>
                            <td v-if="showBase" class="px-3 text-end text-ink-2"><bdi>{{ r.f(d.base) }}</bdi></td>
                            <td v-if="showBase" class="px-3 text-end">
                                <template v-for="ch in [change(r, d.value, d.base)]" :key="'b'">
                                    <bdi v-if="ch" class="chip !px-2 !py-0.5" :class="toneCls(r, ch)">{{ ch.label }}</bdi>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
