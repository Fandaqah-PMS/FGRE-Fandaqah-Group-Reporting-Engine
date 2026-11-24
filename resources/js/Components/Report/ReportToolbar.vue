<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Icon from '../Icon.vue';
import { useI18n } from '../../lib/i18n';
import UiButton from '../ui/UiButton.vue';

/**
 * One toolbar for every report:
 *   view   Group total | Side by side | Hotel vs group
 *   basis  compare with Last year | Budget
 *   hotels which hotels (all, a region, or picked one by one)
 *   export Excel (CSV) | PDF (print)
 */
const props = defineProps({
    view: { type: String, default: 'total' },
    basis: { type: String, default: 'ly' },
    hotels: { type: Array, default: () => [] },        // [{ id, name, name_ar, region }]
    selection: { type: Array, default: null },          // ids, null = all
    focus: { default: null },
    views: { type: Array, default: () => ['total', 'compare', 'focus'] },
    bases: { type: Array, default: () => ['ly', 'budget'] },
    canExport: { type: Boolean, default: true },
});
const emit = defineEmits(['change', 'export', 'print']);
const { t, name } = useI18n();

const viewLabels = computed(() => ({ total: t('Group total'), compare: t('Side by side'), focus: t('Hotel vs group') }));
const viewIcons = { total: 'layers', compare: 'table', focus: 'building' };
const basisLabels = computed(() => ({ ly: t('Last year'), budget: t('Budget') }));

const regions = computed(() => {
    const map = {};
    for (const h of props.hotels) if (h.region) (map[h.region] ??= []).push(h.id);
    return Object.entries(map).filter(([, ids]) => ids.length).sort();
});
const chosen = ref(new Set(props.selection ?? props.hotels.map((h) => h.id)));
const pickerOpen = ref(false);
const exportOpen = ref(false);
const root = ref(null);
const pickerLabel = computed(() => {
    if (!props.selection || props.selection.length === props.hotels.length) return t('All hotels (:n)', { n: props.hotels.length });
    const region = regions.value.find(([, ids]) => ids.length === props.selection.length && ids.every((id) => props.selection.includes(id)));
    return region ? `${t(region[0])} (${region[1].length})` : t(':n hotels', { n: props.selection.length });
});

const toggle = (id) => { const s = new Set(chosen.value); s.has(id) ? s.delete(id) : s.add(id); chosen.value = s; };
const applyHotels = (ids) => {
    pickerOpen.value = false;
    const all = ids.length === props.hotels.length;
    emit('change', { hotels: all ? null : ids.join(',') });
};
const onDoc = (e) => { if (root.value && !root.value.contains(e.target)) { pickerOpen.value = false; exportOpen.value = false; } };
onMounted(() => document.addEventListener('pointerdown', onDoc));
onBeforeUnmount(() => document.removeEventListener('pointerdown', onDoc));
</script>

<template>
    <div ref="root" class="card flex flex-wrap items-center gap-3 p-3 print:hidden">
        <div v-if="views.length > 1" class="seg !border-0 !bg-paper" role="tablist" :aria-label="t('View')">
            <button v-for="v in views" :key="v" type="button" role="tab" :aria-selected="view === v" @click="emit('change', { view: v })">
                <Icon :name="viewIcons[v]" :size="15" />{{ viewLabels[v] }}
            </button>
        </div>

        <select v-if="view === 'focus'" :value="focus" class="field !w-auto !py-2 text-sm font-semibold" :aria-label="t('Hotel')"
                @change="emit('change', { focus: $event.target.value })">
            <option v-for="h in hotels" :key="h.id" :value="h.id">{{ name(h) }}</option>
        </select>

        <div v-if="bases.length" class="flex items-center gap-2">
            <span class="text-xs font-semibold text-muted">{{ t('Compare with') }}</span>
            <div class="seg">
                <button v-for="b in bases" :key="b" type="button" :aria-pressed="basis === b" @click="emit('change', { basis: b })">{{ basisLabels[b] }}</button>
            </div>
        </div>

        <div v-if="hotels.length > 1 && view !== 'focus'" class="relative">
            <button type="button" class="flex items-center gap-2 rounded-xl border border-line bg-surface px-3 py-2 text-[13px] font-semibold text-ink hover:border-coral/40" @click="pickerOpen = !pickerOpen">
                <Icon name="building" :size="15" class="text-coral" />{{ pickerLabel }}<Icon name="chevron" :size="14" class="text-muted" />
            </button>
            <div v-if="pickerOpen" class="rise absolute start-0 top-full z-40 mt-2 w-72 rounded-2xl border border-line bg-surface p-2 shadow-2xl">
                <button type="button" class="w-full rounded-lg px-3 py-2 text-start text-sm font-bold text-ink hover:bg-paper" @click="applyHotels(hotels.map((h) => h.id))">{{ t('All hotels') }}</button>
                <template v-if="regions.length > 1">
                    <div class="px-3 pt-2 pb-1 text-[10px] font-bold tracking-wider text-muted uppercase">{{ t('Hotel sets') }}</div>
                    <button v-for="[region, ids] in regions" :key="region" type="button" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-start text-sm text-ink hover:bg-paper" @click="applyHotels(ids)">
                        <span class="font-semibold">{{ t(region) }}</span><span class="text-xs text-muted">{{ ids.length }}</span>
                    </button>
                </template>
                <div class="px-3 pt-2 pb-1 text-[10px] font-bold tracking-wider text-muted uppercase">{{ t('Pick hotels') }}</div>
                <label v-for="h in hotels" :key="h.id" class="flex cursor-pointer items-center gap-2.5 rounded-lg px-3 py-1.5 text-sm text-ink hover:bg-paper">
                    <input type="checkbox" class="size-4 accent-coral" :checked="chosen.has(h.id)" @change="toggle(h.id)" />{{ name(h) }}
                </label>
                <UiButton block class="mt-2" :disabled="chosen.size === 0" @click="applyHotels([...chosen])">{{ t('Apply') }}</UiButton>
            </div>
        </div>

        <div v-if="canExport" class="relative ms-auto">
            <button type="button" class="flex items-center gap-2 rounded-xl border border-line bg-surface px-3 py-2 text-[13px] font-semibold text-ink hover:border-coral/40" @click="exportOpen = !exportOpen">
                <Icon name="inbox" :size="15" class="rotate-180 text-coral" />{{ t('Export') }}
            </button>
            <div v-if="exportOpen" class="rise absolute end-0 top-full z-40 mt-2 w-48 rounded-2xl border border-line bg-surface p-1.5 shadow-2xl">
                <button type="button" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-ink hover:bg-paper" @click="exportOpen = false; emit('export')"><Icon name="table" :size="15" />{{ t('Excel (CSV)') }}</button>
                <button type="button" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-ink hover:bg-paper" @click="exportOpen = false; emit('print')"><Icon name="book" :size="15" />{{ t('PDF / print') }}</button>
            </div>
        </div>
    </div>
</template>
