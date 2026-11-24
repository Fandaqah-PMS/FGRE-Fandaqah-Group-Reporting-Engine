<script setup>
// Navy hero card with the original brand pattern on the end side (web identity).
defineProps({ eyebrow: String, title: String, text: String });
</script>

<template>
    <section class="rise relative overflow-hidden rounded-[1.5rem] bg-navy text-white shadow-[0_24px_60px_-30px_rgba(38,46,67,0.7)]">
        <div class="brand-pattern pointer-events-none absolute inset-y-0 end-0 w-[28%] opacity-60 sm:w-[46%] sm:opacity-95" aria-hidden="true" />
        <div class="relative px-6 py-8 sm:px-9 sm:py-10">
            <div class="eyebrow">{{ eyebrow }}</div>
            <h2 class="mt-2 max-w-[38rem] font-display text-[1.9rem] leading-tight font-semibold sm:text-[2.3rem]">{{ title }}</h2>
            <p v-if="text" class="mt-2 max-w-xl text-sm text-white/75">{{ text }}</p>
            <div v-if="$slots.default" class="mt-5 flex flex-wrap items-center gap-2"><slot /></div>
        </div>
    </section>
</template>
