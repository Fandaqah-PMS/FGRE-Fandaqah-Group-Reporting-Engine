<script setup>
import Icon from './Icon.vue';
import { useViewMode } from '../lib/view';
import { useI18n } from '../lib/i18n';

const view = useViewMode();
const { t } = useI18n();
</script>

<template>
    <div class="seg" role="group" :aria-label="t('View')">
        <button type="button" :aria-pressed="view === 'charts'" @click="view = 'charts'"><Icon name="chart" :size="16" />{{ t('Charts') }}</button>
        <button type="button" :aria-pressed="view === 'tables'" @click="view = 'tables'"><Icon name="table" :size="16" />{{ t('Tables') }}</button>
    </div>
</template>
