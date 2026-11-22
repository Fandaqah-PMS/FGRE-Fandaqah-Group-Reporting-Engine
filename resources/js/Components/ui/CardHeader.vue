<script setup>
import IconTile from './IconTile.vue';

// Card title row: [icon] title / subtitle on the start side; meta text and actions on the end.
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    meta: { type: String, default: '' },
    icon: { type: String, default: null },
    iconTone: { type: String, default: 'coral' },
});
</script>

<template>
    <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
        <div class="flex min-w-0 items-center gap-3">
            <IconTile v-if="icon" :icon="icon" :tone="iconTone" />
            <div class="min-w-0">
                <h3 class="card-title">{{ title }}</h3>
                <p v-if="subtitle" class="card-sub">{{ subtitle }}</p>
            </div>
        </div>
        <div v-if="meta || $slots.actions" class="flex flex-wrap items-center gap-2">
            <span v-if="meta" class="card-sub"><bdi>{{ meta }}</bdi></span>
            <slot name="actions" />
        </div>
    </div>
</template>
