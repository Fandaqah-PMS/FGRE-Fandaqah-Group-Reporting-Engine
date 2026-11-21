<script setup>
// Legend as pills (PMS dashboard style): dot or line key + label. `dashed` keys a dashed line.
defineProps({ items: { type: Array, required: true } });   // [{ label, color, kind: 'dot' | 'line' | 'dashed' }]
</script>

<template>
    <div class="flex flex-wrap gap-2" role="list">
        <span v-for="i in items" :key="i.label" role="listitem" class="inline-flex items-center gap-1.5 rounded-full bg-paper px-3 py-1 text-xs font-semibold text-ink-2">
            <svg v-if="i.kind === 'line' || i.kind === 'dashed'" width="16" height="6" aria-hidden="true">
                <line x1="1" y1="3" x2="15" y2="3" :stroke="i.color" stroke-width="2.5" stroke-linecap="round" :stroke-dasharray="i.kind === 'dashed' ? '3 3' : null" />
            </svg>
            <span v-else class="size-2 rounded-full" :style="{ background: i.color }" />
            {{ i.label }}
        </span>
    </div>
</template>
