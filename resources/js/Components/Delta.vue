<script setup>
import { computed } from 'vue';
import { delta } from '../lib/format';
import { useI18n } from '../lib/i18n';

// Change vs last year: arrow + sign + colour, never colour alone.
const props = defineProps({
    current: { default: null },
    previous: { default: null },
    kind: { type: String, default: 'pct' },   // 'pct' | 'pp'
    upIsGood: { type: Boolean, default: true },
});

const { t } = useI18n();
const d = computed(() => delta(props.current, props.previous, props.kind));
const good = computed(() => d.value && (d.value.value === 0 ? null : (d.value.value > 0) === props.upIsGood));
</script>

<template>
    <span v-if="d" class="inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[11px] font-semibold tabular" dir="ltr"
          :class="good === null ? 'bg-neutral text-ink-2' : good ? 'bg-success-tint text-success' : 'bg-coral-tint text-coral-dark'"
          :title="t('vs last year')">
        <svg v-if="d.value !== 0" width="9" height="9" viewBox="0 0 10 10" aria-hidden="true" :class="d.value < 0 && 'rotate-180'">
            <path d="M5 1.5 9 8H1z" fill="currentColor" />
        </svg>
        {{ d.label }}
    </span>
    <span v-else class="text-xs text-muted">–</span>
</template>
