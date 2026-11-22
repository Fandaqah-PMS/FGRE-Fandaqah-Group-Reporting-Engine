<script setup>
import { computed, reactive, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import SettingsShell from '../../Components/Settings/SettingsShell.vue';
import Icon from '../../Components/Icon.vue';
import KpiCard from '../../Components/ui/KpiCard.vue';
import { BRAND } from '../../lib/palette';
import { useI18n } from '../../lib/i18n';

const props = defineProps({ roles: Array, categories: Array, canEdit: Boolean });
const { t } = useI18n();

// Local copy of each role's permissions; saved per role.
const draft = reactive({});
watch(() => props.roles, (roles) => roles.forEach((r) => (draft[r.key] = [...r.permissions])), { immediate: true });

const sameSet = (a, b) => a.length === b.length && a.every((x) => b.includes(x));
const dirty = (r) => !sameSet(draft[r.key] ?? [], r.permissions);
const has = (r, p) => (draft[r.key] ?? []).includes(p);
const toggle = (r, p) => {
    if (r.fixed || !props.canEdit) return;
    draft[r.key] = has(r, p) ? draft[r.key].filter((x) => x !== p) : [...draft[r.key], p];
};
const isDefault = (r, p) => r.defaults.includes(p);
const total = computed(() => props.categories.reduce((n, c) => n + c.permissions.length, 0));

const save = (r) => router.put(`/settings/roles/${r.key}`, { permissions: draft[r.key] }, { preserveScroll: true });
const reset = (r) => {
    if (window.confirm(t('Put :role back to the default permissions?', { role: t(`role_${r.key}`) }))) router.delete(`/settings/roles/${r.key}`, { preserveScroll: true });
};
const ICON = { owner: 'shield', finance: 'scale', operations: 'flash', viewer: 'grid' };
</script>

<template>
    <SettingsShell :title="t('Roles & permissions')" :subtitle="t('What each role may do in this group. Everyone in a role gets the same permissions.')">
        <div v-if="!canEdit" class="flex items-center gap-2 rounded-xl bg-paper px-4 py-3 text-sm text-ink-2">
            <Icon name="lock" :size="16" class="text-muted" />{{ t('Only an owner can change permissions.') }}
        </div>

        <!-- Role cards -->
        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <KpiCard v-for="r in roles" :key="r.key" :label="t(`role_${r.key}`)" :value="`${draft[r.key]?.length ?? 0} / ${total}`" :unit="t('permissions')"
                     :dot="r.fixed ? BRAND.navy : BRAND.coral" :note="t(':n people', { n: r.members })"
                     :badge="r.fixed ? { label: t('Fixed'), tone: 'neutral', icon: 'lock' } : r.customized ? { label: t('Customised'), tone: 'peach' } : null"
                     :progress="(100 * (draft[r.key]?.length ?? 0)) / total" />
        </section>

        <!-- Matrix -->
        <section class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[46rem] text-sm">
                    <thead>
                        <tr class="bg-paper">
                            <th class="px-5 py-3 text-start text-[11px] font-bold tracking-wider text-ink-2 uppercase rtl:tracking-normal">{{ t('Permission') }}</th>
                            <th v-for="r in roles" :key="r.key" class="w-32 px-3 py-3 text-center text-xs font-bold text-ink">{{ t(`role_${r.key}`) }}</th>
                        </tr>
                    </thead>
                    <tbody v-for="c in categories" :key="c.label">
                        <tr><td :colspan="roles.length + 1" class="border-t border-line bg-surface px-5 pt-4 pb-1.5 text-[11px] font-bold tracking-[0.14em] text-coral uppercase rtl:tracking-normal">{{ t(c.label) }}</td></tr>
                        <tr v-for="p in c.permissions" :key="p.key" class="hover:bg-paper/50">
                            <td class="px-5 py-2.5">
                                <div class="font-semibold text-ink first-letter:uppercase">{{ t(`perm_${p.key}`) }}</div>
                                <div class="text-xs text-muted">{{ t(p.description) }}</div>
                            </td>
                            <td v-for="r in roles" :key="r.key" class="px-3 py-2.5 text-center">
                                <button type="button" role="checkbox" :aria-checked="has(r, p.key)" :aria-label="`${t(`role_${r.key}`)} · ${t(`perm_${p.key}`)}`"
                                        :disabled="r.fixed || !canEdit"
                                        class="relative inline-grid size-7 place-items-center rounded-lg border-2 transition"
                                        :class="[has(r, p.key) ? (r.fixed ? 'border-navy/30 bg-navy/80 text-white' : 'border-coral bg-coral text-white') : 'border-line bg-surface text-transparent',
                                                 !r.fixed && canEdit && 'hover:border-coral/60', (r.fixed || !canEdit) && 'cursor-default']"
                                        @click="toggle(r, p.key)">
                                    <Icon name="check" :size="14" />
                                    <span v-if="!r.fixed && has(r, p.key) !== isDefault(r, p.key)" class="absolute -end-1 -top-1 size-2.5 rounded-full border-2 border-surface bg-peach" :title="t('Changed from the default')" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot v-if="canEdit">
                        <tr class="border-t border-line bg-paper/60">
                            <td class="px-5 py-3 text-xs text-muted">
                                <span class="me-1.5 inline-block size-2.5 rounded-full bg-peach align-middle" />{{ t('Changed from the default') }}
                            </td>
                            <td v-for="r in roles" :key="r.key" class="px-2 py-3 text-center">
                                <template v-if="!r.fixed">
                                    <button v-if="dirty(r)" type="button" class="rounded-lg bg-coral px-3 py-1.5 text-xs font-bold text-white hover:bg-coral-dark" @click="save(r)">{{ t('Save') }}</button>
                                    <button v-else-if="r.customized" type="button" class="rounded-lg px-2 py-1.5 text-[11px] font-bold text-ink-2 hover:bg-surface hover:text-ink" @click="reset(r)">{{ t('Reset to default') }}</button>
                                </template>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
        <p class="text-xs text-muted">{{ t('The owner role always has every permission, so a group can never lock itself out. Hotel access is set per person on the Users page.') }}</p>
    </SettingsShell>
</template>
