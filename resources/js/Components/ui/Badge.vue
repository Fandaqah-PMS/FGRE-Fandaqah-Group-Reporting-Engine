<script setup>
import Icon from '../Icon.vue';
import { toneClass } from './tones';

// A status or label pill. Tone from the tone scale; an icon keeps meaning without colour.
const props = defineProps({
    tone: { type: String, default: 'neutral' },
    icon: { type: String, default: null },
    size: { type: String, default: 'md' },   // sm (in tables, cards) · md
});
</script>

<template>
    <span class="chip whitespace-nowrap" :class="[toneClass(props.tone), props.size === 'sm' ? '!px-2 !py-0.5 text-[11px]' : '']">
        <Icon v-if="props.icon" :name="props.icon" :size="props.size === 'sm' ? 11 : 13" /><slot />
    </span>
</template>
