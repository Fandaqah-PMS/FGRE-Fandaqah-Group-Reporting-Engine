<script setup>
import { computed } from 'vue';
import { isNum } from '../../lib/format';
import { useI18n } from '../../lib/i18n';

/**
 * Signed balances around a zero line: debit (+) to the end side, credit (−) to the start side.
 * One colour per chart; the sign is read from position and the printed value.
 * rows: [{ id, name, name_ar, value }]
 */
const props = defineProps({
    rows: { type: Array, required: true },
    format: { type: Function, required: true },
    color: { type: String, required: true },
});
const { name } = useI18n();
const max = computed(() => Math.max(1e-9, ...props.rows.map((r) => Math.abs(Number(r.value) || 0))));
const w = (v) => `${(Math.abs(Number(v) || 0) / max.value) * 50}%`;
</script>

<template>
    <ul class="space-y-3">
        <li v-for="row in rows" :key="row.id" class="text-[13px]">
            <div class="mb-1 flex items-center justify-between gap-2">
                <span class="truncate font-semibold text-navy">{{ name(row) }}</span>
                <bdi class="font-bold text-navy tabular">{{ format(row.value) }}</bdi>
            </div>
            <div class="relative h-3 rounded-full bg-paper">
                <span class="absolute inset-y-[-3px] start-1/2 w-px bg-ink-2/40" />
                <div v-if="isNum(row.value) && Number(row.value) !== 0" class="absolute inset-y-0"
                     :class="Number(row.value) > 0 ? 'start-1/2 rounded-e-[4px]' : 'end-1/2 rounded-s-[4px]'"
                     :style="{ width: w(row.value), background: color }" />
            </div>
        </li>
    </ul>
</template>
