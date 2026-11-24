<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '../Icon.vue';

/**
 * The one button. Variants:
 *   primary    coral, the main action of a page or dialog (one per view)
 *   secondary  outlined, other actions in headers and toolbars
 *   ghost      text only, row and card actions (Edit, Preview …)
 *   danger     text, destructive (Remove)
 *   on-dark    on navy panels
 * Sizes sm · md · lg. With `href` it renders a link. Other attributes (type, disabled,
 * form, @click) pass through to the element.
 */
const props = defineProps({
    variant: { type: String, default: 'primary' },
    size: { type: String, default: 'md' },
    icon: { type: String, default: null },
    iconEnd: { type: String, default: null },
    href: { type: String, default: null },
    block: Boolean,
});

const VARIANTS = {
    primary: 'bg-coral text-white shadow-[var(--shadow-coral)] hover:bg-coral-dark',
    secondary: 'border border-line bg-surface text-ink shadow-sm hover:border-coral/40 hover:text-coral',
    ghost: 'text-ink-2 hover:bg-paper hover:text-ink',
    danger: 'text-muted hover:bg-coral-tint hover:text-coral-dark',
    'on-dark': 'bg-white/10 text-white ring-1 ring-white/15 hover:bg-white/15',
};
const SIZES = { sm: ['gap-1.5 rounded-lg px-2.5 py-1.5 text-xs', 14], md: ['gap-2 rounded-xl px-4 py-2.5 text-sm', 16], lg: ['gap-2 rounded-xl px-5 h-[3.25rem] text-[15px]', 18] };

const classes = computed(() => [
    'inline-flex items-center justify-center font-bold whitespace-nowrap transition disabled:pointer-events-none disabled:opacity-55',
    VARIANTS[props.variant] ?? VARIANTS.primary,
    SIZES[props.size]?.[0] ?? SIZES.md[0],
    props.block && 'w-full',
]);
const iconSize = computed(() => (SIZES[props.size] ?? SIZES.md)[1]);
</script>

<template>
    <Link v-if="href" :href="href" :class="classes">
        <Icon v-if="icon" :name="icon" :size="iconSize" /><slot /><Icon v-if="iconEnd" :name="iconEnd" :size="iconSize" class="rtl:rotate-180" />
    </Link>
    <button v-else type="button" :class="classes">
        <Icon v-if="icon" :name="icon" :size="iconSize" /><slot /><Icon v-if="iconEnd" :name="iconEnd" :size="iconSize" class="rtl:rotate-180" />
    </button>
</template>
