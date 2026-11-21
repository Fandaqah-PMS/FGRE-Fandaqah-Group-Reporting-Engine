<script setup>
import { computed, ref } from 'vue';
import { SURFACE } from '../../lib/palette';
import { isNum } from '../../lib/format';
import { useI18n } from '../../lib/i18n';

/**
 * Part-to-whole ring (≤ 6 parts) with the total in the centre and a legend
 * listing every value and share, so nothing depends on colour or hover.
 * segments: [{ key, label, value, color }]
 */
const props = defineProps({
    segments: { type: Array, required: true },
    format: { type: Function, required: true },
    centerLabel: { type: String, default: '' },
    size: { type: Number, default: 180 },
});

const { t } = useI18n();
const active = ref(null);

const total = computed(() => props.segments.reduce((s, x) => s + (isNum(x.value) ? Math.max(0, Number(x.value)) : 0), 0));
const R = 80, STROKE = 26, C = 2 * Math.PI * R;
const arcs = computed(() => {
    let offset = 0;
    return props.segments.map((s) => {
        const share = total.value > 0 ? Math.max(0, Number(s.value) || 0) / total.value : 0;
        const len = share * C;
        const arc = { ...s, share, dash: `${Math.max(0, len - 2)} ${C}`, offset: -offset };
        offset += len;
        return arc;
    });
});
</script>

<template>
    <div class="flex flex-col items-center gap-5">
        <div class="relative shrink-0" :style="{ width: `${size}px`, height: `${size}px` }">
            <svg viewBox="0 0 200 200" class="size-full -rotate-90" role="img" :aria-label="centerLabel">
                <circle cx="100" cy="100" :r="R" fill="none" stroke="#EDEBE6" :stroke-width="STROKE" />
                <circle v-for="a in arcs" :key="a.key" cx="100" cy="100" :r="R" fill="none" :stroke="a.color" :stroke-width="active === a.key ? STROKE + 6 : STROKE"
                        :stroke-dasharray="a.dash" :stroke-dashoffset="a.offset" class="transition-all" tabindex="0"
                        @pointerenter="active = a.key" @pointerleave="active = null" @focus="active = a.key" @blur="active = null" />
                <circle cx="100" cy="100" :r="R - STROKE / 2 - 1" fill="none" :stroke="SURFACE" stroke-width="2" />
            </svg>
            <div class="absolute inset-0 grid place-items-center text-center">
                <div>
                    <div class="text-[11px] font-semibold text-muted">{{ active ? arcs.find((a) => a.key === active)?.label : centerLabel }}</div>
                    <div class="text-lg font-extrabold text-navy tabular" dir="ltr">
                        {{ active ? format(arcs.find((a) => a.key === active)?.value) : format(total) }}
                    </div>
                    <div v-if="active" class="text-[11px] font-semibold text-ink-2 tabular" dir="ltr">{{ (arcs.find((a) => a.key === active).share * 100).toFixed(1) }}%</div>
                </div>
            </div>
        </div>
        <ul class="w-full space-y-2.5" :aria-label="t('Legend')">
            <li v-for="a in arcs" :key="a.key" class="flex items-center gap-2.5 text-sm" @pointerenter="active = a.key" @pointerleave="active = null">
                <span class="size-3 shrink-0 rounded-[4px]" :style="{ background: a.color }" />
                <span class="flex-1 font-medium text-ink-2">{{ a.label }}</span>
                <bdi class="font-bold text-navy tabular">{{ format(a.value) }}</bdi>
                <bdi class="w-12 text-end text-xs font-semibold text-muted tabular">{{ (a.share * 100).toFixed(1) }}%</bdi>
            </li>
        </ul>
    </div>
</template>
