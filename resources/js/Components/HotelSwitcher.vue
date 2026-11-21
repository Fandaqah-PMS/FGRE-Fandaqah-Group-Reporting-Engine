<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Icon from './Icon.vue';
import { useI18n } from '../lib/i18n';

// All hotels (consolidated) or one hotel. The choice applies to every page.
const page = usePage();
const { t, name } = useI18n();
const open = ref(false);
const root = ref(null);

const scope = computed(() => page.props.scope ?? { current: null, hotels: [] });
const current = computed(() => scope.value.hotels.find((h) => h.id === scope.value.current) ?? null);

function choose(id) {
    open.value = false;
    router.post('/scope', { hotel: id ?? 'all' }, { preserveScroll: true });
}

const onDoc = (e) => root.value && !root.value.contains(e.target) && (open.value = false);
const onKey = (e) => e.key === 'Escape' && (open.value = false);
onMounted(() => { document.addEventListener('pointerdown', onDoc); document.addEventListener('keydown', onKey); });
onBeforeUnmount(() => { document.removeEventListener('pointerdown', onDoc); document.removeEventListener('keydown', onKey); });
</script>

<template>
    <div ref="root" class="relative">
        <button type="button" class="flex w-full max-w-[22rem] items-center gap-3 rounded-2xl border border-line bg-white px-3 py-2 text-start shadow-[var(--shadow-card)] transition hover:border-coral/40"
                :aria-expanded="open" aria-haspopup="listbox" @click="open = !open">
            <span class="grid size-9 shrink-0 place-items-center rounded-xl" :class="current ? 'bg-coral-tint text-coral' : 'bg-navy text-white'">
                <Icon :name="current ? 'building' : 'layers'" :size="18" />
            </span>
            <span class="min-w-0 flex-1">
                <span class="block text-[10px] font-bold tracking-wider text-muted uppercase">{{ current ? t('Single hotel') : t('Consolidated') }}</span>
                <span class="block truncate text-sm font-bold text-navy">{{ current ? name(current) : t('All hotels (:n)', { n: scope.hotels.length }) }}</span>
            </span>
            <Icon name="chevron" :size="16" class="text-muted transition" :class="open && 'rotate-180'" />
        </button>

        <div v-if="open" class="rise absolute start-0 top-full z-40 mt-2 w-[22rem] max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-line bg-white shadow-2xl" role="listbox">
            <button type="button" role="option" :aria-selected="!current" class="flex w-full items-center gap-3 px-4 py-3 text-start hover:bg-paper" @click="choose(null)">
                <span class="grid size-8 place-items-center rounded-lg bg-navy text-white"><Icon name="layers" :size="16" /></span>
                <span class="flex-1">
                    <span class="block text-sm font-bold text-navy">{{ t('All hotels') }}</span>
                    <span class="block text-xs text-muted">{{ t('Consolidated group view') }}</span>
                </span>
                <Icon v-if="!current" name="check" :size="16" class="text-coral" />
            </button>
            <div class="border-t border-line px-4 pt-3 pb-1 text-[10px] font-bold tracking-wider text-muted uppercase">{{ t('Hotels') }}</div>
            <div class="max-h-80 overflow-y-auto pb-2">
                <button v-for="h in scope.hotels" :key="h.id" type="button" role="option" :aria-selected="current?.id === h.id"
                        class="flex w-full items-center gap-3 px-4 py-2.5 text-start hover:bg-paper" @click="choose(h.id)">
                    <span class="grid size-8 place-items-center rounded-lg bg-coral-tint text-coral"><Icon name="building" :size="16" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-navy">{{ name(h) }}</span>
                        <span class="block text-xs text-muted">{{ h.pms }}<template v-if="h.rooms"> · {{ t(':n rooms', { n: h.rooms }) }}</template></span>
                    </span>
                    <Icon v-if="current?.id === h.id" name="check" :size="16" class="text-coral" />
                </button>
            </div>
        </div>
    </div>
</template>
