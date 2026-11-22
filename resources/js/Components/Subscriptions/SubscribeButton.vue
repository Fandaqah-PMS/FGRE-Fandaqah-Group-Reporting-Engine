<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Icon from '../Icon.vue';
import SubscribeDialog from './SubscribeDialog.vue';
import { useViewMode } from '../../lib/view';
import { useI18n } from '../../lib/i18n';

// "Email me this": opens the subscription dialog prefilled with the hotels and view on screen.
const props = defineProps({ hotels: { type: Array, default: null } });
const { t } = useI18n();
const page = usePage();
const view = useViewMode();
const open = ref(false);

const defaults = computed(() => {
    const scope = page.props.scope ?? {};
    return {
        hotels: props.hotels ?? (scope.current ? [scope.current] : null),
        content: view.value === 'tables' ? 'tables' : 'both',
    };
});
</script>

<template>
    <button type="button" class="inline-flex items-center gap-2 rounded-xl border border-coral/30 bg-surface px-3.5 py-2 text-sm font-bold text-coral transition hover:bg-coral hover:text-white"
            @click="open = true">
        <Icon name="mail" :size="16" />{{ t('Subscribe') }}
    </button>
    <SubscribeDialog :open="open" :defaults="defaults" @close="open = false" />
</template>
