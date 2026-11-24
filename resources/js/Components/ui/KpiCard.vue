<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Delta from '../Delta.vue';
import Sparkline from '../Charts/Sparkline.vue';
import Badge from './Badge.vue';
import IconTile from './IconTile.vue';
import { BRAND } from '../../lib/palette';
import { useI18n } from '../../lib/i18n';

/**
 * THE KPI card: every key figure in FGRE uses it (docs/DESIGN-SYSTEM.md). One anatomy:
 *
 *   ● label ......................... [change vs last year | badge | icon]
 *   VALUE unit
 *   note (or "Last year …")
 *   footer: sparkline · progress bar · slot
 *
 * Options, not new designs: `href` (link card) or `selectable` (filter button) with `active`;
 * `icon` replaces the dot; `highlight` for the one card that needs attention; `valueTone`
 * for a status word as the value (Balanced / Not balanced).
 */
const props = defineProps({
    label: { type: String, required: true },
    value: { type: String, required: true },
    unit: { type: String, default: '' },
    dot: { type: String, default: null },
    icon: { type: String, default: null },
    iconTone: { type: String, default: 'neutral' },
    current: { default: null },
    previous: { default: null },
    previousLabel: { type: String, default: '' },
    kind: { type: String, default: 'pct' },
    compareTo: { type: String, default: 'last_year' },   // last_year · yesterday (what `previous` is)
    neutralChange: Boolean,                              // balances: a change is neither good nor bad
    note: { type: String, default: '' },
    spark: { type: Array, default: null },
    progress: { type: Number, default: null },
    badge: { type: Object, default: null },        // { label, tone, icon }
    highlight: Boolean,
    valueTone: { type: String, default: 'default' }, // default · success · danger
    href: { type: String, default: null },
    selectable: Boolean,
    active: Boolean,
});
defineEmits(['click']);

const { t } = useI18n();
const tag = computed(() => (props.href ? Link : props.selectable ? 'button' : 'div'));
const dotColor = computed(() => props.dot ?? BRAND.coral);
const compareCaption = computed(() => (props.compareTo === 'yesterday' ? t('vs yesterday') : t('vs last year')));
const VALUE_TONE = { default: 'text-ink', success: 'text-success', danger: 'text-coral-dark' };
</script>

<template>
    <component :is="tag" :href="href ?? undefined" :type="selectable ? 'button' : undefined"
               class="card rise flex min-h-[8.5rem] flex-col px-4 py-3.5 text-start"
               :class="[
                   highlight && '!border-coral/30 bg-[linear-gradient(160deg,var(--color-coral-tint)_0%,var(--color-surface)_65%)]',
                   (href || selectable) && 'transition hover:-translate-y-0.5 hover:shadow-md',
                   active && '!border-coral ring-2 ring-coral/20',
               ]"
               @click="$emit('click', $event)">
        <div class="flex w-full items-center justify-between gap-1.5">
            <div class="flex min-w-0 items-center gap-1.5 text-[12.5px] font-semibold text-ink-2">
                <span v-if="!icon" class="size-2 shrink-0 rounded-full" :style="{ background: dotColor }" />
                <span class="truncate">{{ label }}</span>
            </div>
            <Delta v-if="previous !== null" :current="current" :previous="previous" :kind="kind" :neutral="neutralChange" :caption="compareCaption" class="shrink-0" />
            <Badge v-else-if="badge" :tone="badge.tone" :icon="badge.icon" size="sm">{{ badge.label }}</Badge>
            <IconTile v-else-if="icon" :icon="icon" :tone="iconTone" size="sm" />
        </div>
        <div dir="ltr" class="mt-2 flex w-full items-baseline gap-1 rtl:justify-end">
            <span class="text-[1.5rem] leading-none font-semibold tracking-tight tabular" :class="VALUE_TONE[valueTone] ?? VALUE_TONE.default">{{ value }}</span>
            <span v-if="unit" class="text-[11px] font-medium text-muted">{{ unit }}</span>
        </div>
        <div v-if="previousLabel || note" class="mt-1 w-full truncate text-xs text-muted">
            <template v-if="previousLabel">{{ compareTo === 'yesterday' ? t('Yesterday') : t('Last year') }} <bdi class="tabular">{{ previousLabel }}</bdi></template>
            <template v-else>{{ note }}</template>
        </div>
        <div v-if="spark" class="mt-auto pt-2"><Sparkline :values="spark" :height="28" /></div>
        <div v-else-if="progress !== null" class="mt-auto pt-3">
            <div class="h-1.5 overflow-hidden rounded-full bg-coral-tint">
                <div class="h-full rounded-full bg-coral transition-all" :style="{ width: `${Math.max(0, Math.min(100, progress))}%` }" />
            </div>
        </div>
        <div v-if="$slots.default" class="mt-auto pt-2"><slot /></div>
    </component>
</template>
