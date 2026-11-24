<script setup>
// Page title block from the web identity: coral eyebrow, El Messiri title, muted subtitle, actions.
defineProps({ eyebrow: String, title: String, subtitle: String });
</script>

<template>
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <div v-if="eyebrow" class="eyebrow">{{ eyebrow }}</div>
            <h1 class="mt-1 font-display text-[2rem] leading-tight font-semibold text-ink">{{ title }}</h1>
            <p v-if="subtitle || $slots.subtitle" class="mt-1.5 flex flex-wrap items-center gap-2 text-sm text-ink-2">
                <slot name="subtitle">{{ subtitle }}</slot>
            </p>
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2"><slot name="actions" /></div>
    </header>
</template>
