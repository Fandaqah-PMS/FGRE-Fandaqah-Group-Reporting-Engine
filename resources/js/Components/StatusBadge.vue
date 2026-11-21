<script setup>
import { computed } from 'vue';
import Icon from './Icon.vue';
import { useI18n } from '../lib/i18n';

// Status: icon + label, never colour alone.
const props = defineProps({ status: String });
const { t } = useI18n();

const style = computed(() => ({
    processed: { cls: 'bg-success-tint text-success', icon: 'check', label: t('Processed') },
    needs_review: { cls: 'bg-coral-tint text-coral-dark', icon: 'alert', label: t('Needs review') },
    failed: { cls: 'bg-neutral text-danger', icon: 'close', label: t('Failed') },
    extracting: { cls: 'bg-peach-tint text-navy', icon: 'refresh', label: t('Processing') },
    received: { cls: 'bg-peach-tint text-navy', icon: 'refresh', label: t('Queued') },
    rejected: { cls: 'bg-neutral text-ink-2', icon: 'close', label: t('Rejected') },
}[props.status] ?? { cls: 'bg-neutral text-ink-2', icon: 'inbox', label: props.status }));
</script>

<template>
    <span class="chip whitespace-nowrap" :class="style.cls"><Icon :name="style.icon" :size="13" />{{ style.label }}</span>
</template>
