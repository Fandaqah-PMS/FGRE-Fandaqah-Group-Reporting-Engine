<script setup>
import { Link, router } from '@inertiajs/vue3';
import SettingsShell from '../../Components/Settings/SettingsShell.vue';
import Icon from '../../Components/Icon.vue';
import { useI18n } from '../../lib/i18n';

const props = defineProps({ logs: Object, filter: String });
const { t, locale } = useI18n();

const KIND = {
    user: { icon: 'users', cls: 'bg-navy text-white', label: 'Users' },
    role: { icon: 'shield', cls: 'bg-coral-tint text-coral', label: 'Roles' },
    hotel: { icon: 'building', cls: 'bg-peach-tint text-peach-ink', label: 'Hotels' },
    group: { icon: 'settings', cls: 'bg-paper text-ink-2', label: 'Group' },
};
const kind = (l) => KIND[l.action.split('.')[0]] ?? KIND.group;
const when = (iso) => new Intl.DateTimeFormat(locale.value === 'ar' ? 'ar-SA-u-ca-gregory-nu-latn' : 'en-GB', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
const perm = (list) => list.map((p) => t(`perm_${p}`)).join('، ');
const detail = (l) => {
    const d = l.details ?? {};
    if (l.action === 'role.updated') {
        return [d.added?.length && `${t('Added')}: ${perm(d.added)}`, d.removed?.length && `${t('Removed')}: ${perm(d.removed)}`].filter(Boolean).join(' · ');
    }
    if (Array.isArray(d.role)) return `${t(`role_${d.role[0]}`)} → ${t(`role_${d.role[1]}`)}`;
    if (d.role) return t(`role_${d.role}`);
    if (d.fields) return d.fields.map((f) => t(`field_${f}`)).join('، ');
    return '';
};
const setFilter = (f) => router.get('/settings/activity', f ? { action: f } : {}, { preserveScroll: true });
</script>

<template>
    <SettingsShell :title="t('Activity log')" :subtitle="t('Every change to users, roles, hotels and group settings.')">
        <div class="seg w-fit">
            <button type="button" :aria-pressed="!filter" @click="setFilter(null)">{{ t('All') }}</button>
            <button v-for="(k, key) in KIND" :key="key" type="button" :aria-pressed="filter === key" @click="setFilter(key)">{{ t(k.label) }}</button>
        </div>

        <section class="card p-2 sm:p-4">
            <ol v-if="logs.data.length" class="relative">
                <li v-for="l in logs.data" :key="l.id" class="relative flex gap-4 rounded-xl px-3 py-3 hover:bg-paper/60">
                    <span class="grid size-9 shrink-0 place-items-center rounded-xl" :class="kind(l).cls"><Icon :name="kind(l).icon" :size="16" /></span>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm text-ink">
                            <b>{{ l.user ?? t('System') }}</b> · {{ t(`audit_${l.action}`) }}
                            <bdi v-if="l.subject" class="font-semibold">{{ l.action.startsWith('role.') ? t(`role_${l.subject}`) : l.subject }}</bdi>
                        </div>
                        <div v-if="detail(l)" class="mt-0.5 text-xs text-ink-2">{{ detail(l) }}</div>
                    </div>
                    <div class="shrink-0 text-end text-xs text-muted">
                        <bdi>{{ when(l.at) }}</bdi>
                        <div v-if="l.ip" class="tabular" dir="ltr">{{ l.ip }}</div>
                    </div>
                </li>
            </ol>
            <div v-else class="p-10 text-center text-sm text-muted">{{ t('No changes recorded yet.') }}</div>
        </section>

        <nav v-if="logs.last_page > 1" class="flex items-center justify-between text-sm">
            <Link v-if="logs.prev_page_url" :href="logs.prev_page_url" preserve-scroll class="font-bold text-coral">{{ t('Newer') }}</Link><span v-else />
            <span class="text-muted tabular">{{ logs.current_page }} / {{ logs.last_page }}</span>
            <Link v-if="logs.next_page_url" :href="logs.next_page_url" preserve-scroll class="font-bold text-coral">{{ t('Older') }}</Link><span v-else />
        </nav>
    </SettingsShell>
</template>
