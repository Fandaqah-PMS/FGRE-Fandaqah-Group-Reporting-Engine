<script setup>
import { computed, ref } from 'vue';
import { tint } from '../../lib/palette';
import { isNum } from '../../lib/format';
import { useI18n } from '../../lib/i18n';

/**
 * Embossed ring (PMS dashboard style): soft outer shadow, gradient segments with gaps,
 * raised white centre. Below it, one row per part: value and share, so nothing depends
 * on colour or hover. segments: [{ key, label, value, color }]; ≤ 6 parts.
 */
const props = defineProps({
    segments: { type: Array, required: true },
    format: { type: Function, required: true },
    centerLabel: { type: String, default: '' },
    centerValue: { type: String, default: null },
    size: { type: Number, default: 210 },
});

const { t } = useI18n();
const active = ref(null);
const uid = Math.random().toString(36).slice(2, 8);

const total = computed(() => props.segments.reduce((s, x) => s + (isNum(x.value) ? Math.max(0, Number(x.value)) : 0), 0));
const R = 72, STROKE = 30, C = 2 * Math.PI * R;
const arcs = computed(() => {
    let offset = 0;
    return props.segments.map((s, i) => {
        const share = total.value > 0 ? Math.max(0, Number(s.value) || 0) / total.value : 0;
        const len = share * C;
        const gap = share > 0 && share < 1 ? 3 : 0;
        const arc = { ...s, i, share, dash: `${Math.max(0, len - gap)} ${C}`, offset: -offset };
        offset += len;
        return arc;
    });
});
const activeArc = computed(() => arcs.value.find((a) => a.key === active.value));
</script>

<template>
    <div class="flex flex-col items-center">
        <div class="relative shrink-0 rounded-full bg-surface p-3 shadow-[0_22px_45px_-18px_rgba(14,19,32,0.35),0_0_0_10px_var(--color-neutral)]"
             :style="{ width: `${size}px`, height: `${size}px` }">
            <svg viewBox="0 0 200 200" class="size-full -rotate-90" role="img" :aria-label="centerLabel">
                <defs>
                    <linearGradient v-for="a in arcs" :id="`dg-${uid}-${a.i}`" :key="a.key" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0" :stop-color="tint(a.color, 0.28)" />
                        <stop offset="1" :stop-color="a.color" />
                    </linearGradient>
                </defs>
                <circle cx="100" cy="100" :r="R" fill="none" class="stroke-neutral" :stroke-width="STROKE" />
                <circle v-for="a in arcs" :key="a.key" cx="100" cy="100" :r="R" fill="none" :stroke="`url(#dg-${uid}-${a.i})`"
                        :stroke-width="active === a.key ? STROKE + 6 : STROKE" :stroke-dasharray="a.dash" :stroke-dashoffset="a.offset"
                        class="cursor-pointer outline-none transition-all" tabindex="0"
                        @pointerenter="active = a.key" @pointerleave="active = null" @focus="active = a.key" @blur="active = null" />
            </svg>
            <div class="absolute inset-[27%] grid place-items-center rounded-full bg-surface text-center shadow-[0_6px_16px_-6px_rgba(38,46,67,0.25),inset_0_1px_2px_rgba(38,46,67,0.06)]">
                <div class="px-1">
                    <div class="text-[1.2rem] leading-none font-semibold text-ink tabular" dir="ltr">
                        {{ activeArc ? format(activeArc.value) : (centerValue ?? format(total)) }}
                    </div>
                    <div class="mt-1 line-clamp-2 text-[10.5px] leading-tight font-semibold text-muted">{{ activeArc ? activeArc.label : centerLabel }}</div>
                </div>
            </div>
        </div>
        <ul class="mt-6 w-full divide-y divide-line" :aria-label="t('Legend')">
            <li v-for="a in arcs" :key="a.key" class="flex items-center gap-2.5 py-2.5 text-[13px]" :class="active === a.key && 'bg-paper'"
                @pointerenter="active = a.key" @pointerleave="active = null">
                <span class="size-2.5 shrink-0 rounded-full" :style="{ background: a.color }" />
                <span class="min-w-0 flex-1 font-medium text-ink-2">{{ a.label }}</span>
                <bdi class="font-semibold text-ink tabular">{{ format(a.value) }}</bdi>
                <bdi class="w-12 text-end text-xs text-muted tabular">{{ (a.share * 100).toFixed(1) }}%</bdi>
            </li>
        </ul>
    </div>
</template>
