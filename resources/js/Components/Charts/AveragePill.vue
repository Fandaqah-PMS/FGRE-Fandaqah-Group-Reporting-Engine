<script setup>
import { useI18n } from '../../lib/i18n';

defineProps({ value: String });
const { t } = useI18n();
</script>

<template>
    <span v-if="value" class="inline-flex items-center rounded-full bg-coral-tint px-3 py-1 text-xs font-bold text-coral-dark">
        {{ t('Average') }}&nbsp;<bdi class="tabular">{{ value }}</bdi>
    </span>
</template>
