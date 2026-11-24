<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Icon from './Icon.vue';
import { setTheme, themePref } from '../lib/theme';
import { useI18n } from '../lib/i18n';

// Light / Dark / System as three icon buttons (header, sign-in pages).
const props = defineProps({ onDark: Boolean });
const { t } = useI18n();
const page = usePage();
const signedIn = computed(() => !!page.props.auth?.user);

const OPTIONS = [
    { key: 'light', icon: 'sun', label: 'Light' },
    { key: 'dark', icon: 'moon', label: 'Dark' },
    { key: 'system', icon: 'monitor', label: 'System' },
];
</script>

<template>
    <div class="flex items-center gap-0.5 rounded-xl border p-0.5 shadow-sm" role="radiogroup" :aria-label="t('Theme')"
         :class="props.onDark ? 'border-white/20 bg-navy' : 'border-line bg-surface'">
        <button v-for="o in OPTIONS" :key="o.key" type="button" role="radio" :aria-checked="themePref === o.key" :title="t(o.label)" :aria-label="t(o.label)"
                class="grid size-8 place-items-center rounded-lg transition"
                :class="themePref === o.key
                    ? 'bg-coral text-white shadow-[var(--shadow-coral)]'
                    : props.onDark ? 'text-white/70 hover:bg-white/10 hover:text-white' : 'text-ink-2 hover:bg-paper hover:text-ink'"
                @click="setTheme(o.key, signedIn)">
            <Icon :name="o.icon" :size="16" />
        </button>
    </div>
</template>
