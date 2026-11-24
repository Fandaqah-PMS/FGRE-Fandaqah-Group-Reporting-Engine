<script setup>
import Icon from '../Icon.vue';
import { toneClass } from './tones';

// An icon on a tinted square: the one way to put an icon next to a title, a stat or a row.
const props = defineProps({
    icon: { type: String, required: true },
    tone: { type: String, default: 'neutral' },
    size: { type: String, default: 'md' },   // sm 32 px · md 40 px · lg 48 px
});
const SIZES = { sm: ['size-8 rounded-lg', 15], md: ['size-10 rounded-xl', 18], lg: ['size-12 rounded-2xl', 22] };
</script>

<template>
    <span class="grid shrink-0 place-items-center" :class="[SIZES[props.size][0], toneClass(props.tone)]">
        <Icon :name="props.icon" :size="SIZES[props.size][1]" />
    </span>
</template>
