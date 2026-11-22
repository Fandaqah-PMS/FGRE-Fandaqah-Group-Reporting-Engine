<script setup>
import IconTile from './IconTile.vue';

// What a page or card shows when there is nothing yet: icon, one line, what to do next.
defineProps({
    icon: { type: String, default: 'inbox' },
    title: { type: String, required: true },
    text: { type: String, default: '' },
});
</script>

<template>
    <div class="card flex flex-col items-center px-6 py-12 text-center">
        <IconTile :icon="icon" tone="coral" size="lg" />
        <p class="mt-4 font-bold text-ink">{{ title }}</p>
        <p v-if="text" class="mt-1 max-w-md text-sm text-ink-2">{{ text }}</p>
        <div v-if="$slots.default" class="mt-5"><slot /></div>
    </div>
</template>
