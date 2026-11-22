<script setup>
// Form field: label above, the input (slot, styled with .field), then the error or a hint.
defineProps({
    label: { type: String, required: true },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
});
</script>

<template>
    <label class="block">
        <span class="mb-1.5 block text-[13px] font-bold text-ink">{{ label }}</span>
        <slot />
        <span v-if="error" class="mt-1 block text-xs font-semibold text-danger">{{ error }}</span>
        <span v-else-if="hint" class="mt-1 block text-xs text-muted">{{ hint }}</span>
    </label>
</template>
