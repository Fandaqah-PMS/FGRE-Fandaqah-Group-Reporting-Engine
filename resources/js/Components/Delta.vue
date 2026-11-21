<script setup>
import { computed } from 'vue';
import { delta } from '../lib/format';
import { useI18n } from '../lib/i18n';

// Change vs last year. Direction is shown with an arrow AND the sign, never colour alone.
const props = defineProps({
    current: { default: null },
    previous: { default: null },
    kind: { type: String, default: 'pct' },   // 'pct' | 'pp'
    upIsGood: { type: Boolean, default: true },
    size: { type: String, default: 'sm' },
});

const { t } = useI18n();
const d = computed(() => delta(props.current, props.previous, props.kind));
const good = computed(() => d.value && (d.value.value === 0 ? null : (d.value.value > 0) === props.upIsGood));
</script>

<template>
    <span v-if="d" class="inline-flex items-center gap-1 font-medium tabular" dir="ltr"
          :class="[size === 'sm' ? 'text-xs' : 'text-sm', good === null ? 'text-secondary' : good ? 'text-success' : 'text-danger']"
          :title="t('vs last year')">
        <svg v-if="d.value !== 0" width="10" height="10" viewBox="0 0 10 10" aria-hidden="true" :class="d.value < 0 && 'rotate-180'">
            <path d="M5 1.5 9 8H1z" fill="currentColor" />
        </svg>
        {{ d.label }}
    </span>
    <span v-else class="text-xs text-grey">–</span>
</template>
