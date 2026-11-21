<script setup>
import { computed } from 'vue';
import { useI18n } from '../lib/i18n';

// Status: icon + label, never colour alone.
const props = defineProps({ status: String });
const { t } = useI18n();

const style = computed(() => ({
    processed: { cls: 'bg-success-soft text-success', icon: 'M3 8.5 6.5 12 13 4.5', label: t('Processed') },
    needs_review: { cls: 'bg-coral-tint text-coral-text', icon: 'M8 4v5M8 11.5v.5', label: t('Needs review') },
    failed: { cls: 'bg-danger-soft text-danger', icon: 'M4.5 4.5l7 7M11.5 4.5l-7 7', label: t('Failed') },
    extracting: { cls: 'bg-warm text-secondary', icon: 'M8 3v5l3 2', label: t('Processing') },
    received: { cls: 'bg-warm text-secondary', icon: 'M8 3v5l3 2', label: t('Queued') },
    rejected: { cls: 'bg-warm text-secondary', icon: 'M4 8h8', label: t('Rejected') },
}[props.status] ?? { cls: 'bg-warm text-secondary', icon: 'M4 8h8', label: props.status }));
</script>

<template>
    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap" :class="style.cls">
        <svg width="12" height="12" viewBox="0 0 16 16" aria-hidden="true"><path :d="style.icon" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
        {{ style.label }}
    </span>
</template>
