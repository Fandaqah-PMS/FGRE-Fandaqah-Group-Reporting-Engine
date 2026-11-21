<script setup>
import Icon from './Icon.vue';
import { useI18n } from '../lib/i18n';

// Dialog in the brand style: navy header, scrolling body, footer for actions. Esc / backdrop close.
defineProps({ open: Boolean, title: String, subtitle: String, icon: { type: String, default: 'settings' }, width: { type: String, default: 'max-w-2xl' } });
const emit = defineEmits(['close']);
const { t } = useI18n();
</script>

<template>
    <Teleport to="body">
        <Transition enter-from-class="opacity-0" leave-to-class="opacity-0" enter-active-class="transition duration-200" leave-active-class="transition duration-150">
            <div v-if="open" class="fixed inset-0 z-50 flex items-end justify-center bg-navy/45 backdrop-blur-[2px] sm:items-center sm:p-6"
                 @click.self="emit('close')" @keydown.esc="emit('close')">
                <div class="flex max-h-[94vh] w-full flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl sm:rounded-3xl" :class="width" role="dialog" aria-modal="true" :aria-label="title">
                    <div class="relative flex items-start gap-4 overflow-hidden bg-navy px-6 py-5 text-white">
                        <div class="brand-pattern pointer-events-none absolute inset-y-0 end-0 w-1/2 opacity-[0.08]" />
                        <span class="relative grid size-11 shrink-0 place-items-center rounded-2xl bg-coral"><Icon :name="icon" :size="20" /></span>
                        <div class="relative min-w-0 flex-1">
                            <h2 class="font-display text-xl font-semibold">{{ title }}</h2>
                            <p v-if="subtitle" class="mt-0.5 truncate text-sm text-white/70">{{ subtitle }}</p>
                        </div>
                        <button type="button" class="relative rounded-lg p-1.5 text-white/70 hover:bg-white/10 hover:text-white" :aria-label="t('Close')" @click="emit('close')"><Icon name="close" /></button>
                    </div>
                    <div class="min-h-0 flex-1 overflow-y-auto p-6"><slot /></div>
                    <div v-if="$slots.footer" class="flex flex-wrap items-center justify-end gap-2 border-t border-line px-6 py-4"><slot name="footer" /></div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
