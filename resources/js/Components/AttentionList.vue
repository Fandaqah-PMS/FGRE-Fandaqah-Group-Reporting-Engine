<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from './Icon.vue';
import { useI18n } from '../lib/i18n';

// "Needs attention": most serious first, icon + label for each level (never colour alone).
const props = defineProps({ items: { type: Array, default: () => [] }, date: String });
const { t, name } = useI18n();
const showAll = ref(false);
const shown = computed(() => (showAll.value ? props.items : props.items.slice(0, 5)));

const LEVEL = {
    critical: { icon: 'alert', cls: 'bg-coral text-white', bar: 'border-coral', label: 'Critical' },
    warning: { icon: 'trend', cls: 'bg-peach text-navy', bar: 'border-peach', label: 'Warning' },
    info: { icon: 'sparkles', cls: 'bg-neutral text-ink-2', bar: 'border-muted', label: 'Note' },
};
const LINK = {
    missing_report: '/documents', needs_review: '/documents?status=needs_review', tb_unbalanced: '/trial-balance',
    ar_growing: '/ledgers', below_budget: '/manager-flash?view=compare&basis=budget&period=mtd',
};
const counts = computed(() => ({
    critical: props.items.filter((i) => i.level === 'critical').length,
    warning: props.items.filter((i) => i.level === 'warning').length,
}));
const href = (i) => LINK[i.type] ?? (i.hotel ? `/manager-flash?view=focus&period=mtd&focus=${i.hotel.id}&date=${props.date}` : '/');
</script>

<template>
    <section class="card rise p-6">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-3">
                <span class="grid size-10 place-items-center rounded-xl" :class="items.length ? 'bg-coral-tint text-coral' : 'bg-success-tint text-success'">
                    <Icon :name="items.length ? 'alert' : 'check'" :size="19" />
                </span>
                <div>
                    <h3 class="card-title">{{ t('Needs attention') }}</h3>
                    <p class="card-sub">{{ items.length ? t(':c critical · :w warnings', { c: counts.critical, w: counts.warning }) : t('Nothing needs your attention today.') }}</p>
                </div>
            </div>
        </div>
        <ul v-if="items.length" class="space-y-2">
            <li v-for="(i, n) in shown" :key="n">
                <Link :href="href(i)" class="flex items-center gap-3 rounded-xl border-s-4 bg-paper px-4 py-3 transition hover:bg-neutral/60" :class="LEVEL[i.level].bar">
                    <span class="chip shrink-0 !px-2 !py-0.5 text-[10px]" :class="LEVEL[i.level].cls"><Icon :name="LEVEL[i.level].icon" :size="12" />{{ t(LEVEL[i.level].label) }}</span>
                    <span class="min-w-0 flex-1 text-sm">
                        <b class="text-navy">{{ i.hotel ? name(i.hotel) : t('Group') }}</b>
                        <span class="text-ink-2"> · {{ t(`attention_${i.type}`, i.params ?? {}) }}</span>
                    </span>
                    <Icon name="chevron" :size="15" class="shrink-0 -rotate-90 text-muted rtl:rotate-90" />
                </Link>
            </li>
        </ul>
        <button v-if="items.length > 5" type="button" class="mt-3 text-sm font-bold text-coral hover:underline" @click="showAll = !showAll">
            {{ showAll ? t('Show less') : t('Show all :n', { n: items.length }) }}
        </button>
    </section>
</template>
