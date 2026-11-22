<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import PageHeader from '../Components/PageHeader.vue';
import Icon from '../Components/Icon.vue';
import SubscribeDialog from '../Components/Subscriptions/SubscribeDialog.vue';
import { useI18n } from '../lib/i18n';

const props = defineProps({ subscriptions: Array });
const { t, name, locale } = useI18n();
const page = usePage();

const hotels = computed(() => page.props.scope?.hotels ?? []);
const dialog = ref({ open: false, subscription: null });
const edit = (s = null) => (dialog.value = { open: true, subscription: s });

const FREQ = { daily: ['calendar', 'Daily'], weekly: ['chart', 'Weekly'], monthly: ['layers', 'Monthly'] };
const CONTENT = { charts: ['pie', 'Charts'], tables: ['table', 'Tables'], both: ['grid', 'Charts and tables'] };
const dayName = (i) => new Intl.DateTimeFormat(locale.value === 'ar' ? 'ar-SA-u-ca-gregory-nu-latn' : 'en-GB', { weekday: 'long' }).format(new Date(2026, 10, 22 + i));
const when = (s) => ({
    daily: t('Every day at :time', { time: s.send_time }),
    weekly: t('Every :day at :time', { day: dayName(s.weekday), time: s.send_time }),
    monthly: t('Day :d of each month at :time', { d: s.month_day, time: s.send_time }),
})[s.frequency];
const covers = (s) => ({ daily: t('The day and month to date'), weekly: t('The last 7 days and month to date'), monthly: t('The previous month and year to date') })[s.frequency];
const hotelNames = (s) => (s.hotels ? hotels.value.filter((h) => s.hotels.includes(h.id)).map((h) => name(h)).join('، ') : t('All my hotels'));
const sentAt = (iso) => (iso ? new Intl.DateTimeFormat(locale.value === 'ar' ? 'ar-SA-u-ca-gregory-nu-latn' : 'en-GB', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso)) : null);

const toggle = (s) => router.put(`/subscriptions/${s.id}`, { is_active: !s.is_active }, { preserveScroll: true });
const sendNow = (s) => router.post(`/subscriptions/${s.id}/send`, {}, { preserveScroll: true });
const remove = (s) => {
    if (window.confirm(t('Stop this email and remove the subscription?'))) router.delete(`/subscriptions/${s.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head :title="t('Email subscriptions')" />

    <PageHeader :eyebrow="t('Fandaqah / Email')" :title="t('Email subscriptions')" :subtitle="t('Get the group report in your inbox: daily, weekly or monthly, as charts, tables or both.')">
        <template #actions>
            <button type="button" class="btn-coral !py-2.5 text-sm" @click="edit()"><Icon name="mail" :size="16" />{{ t('New subscription') }}</button>
        </template>
    </PageHeader>

    <!-- Empty state -->
    <section v-if="!subscriptions.length" class="card rise relative mt-6 overflow-hidden p-8 sm:p-12">
        <div class="brand-pattern pointer-events-none absolute inset-y-0 end-0 w-1/2 opacity-[0.06]" />
        <div class="relative max-w-xl">
            <span class="grid size-14 place-items-center rounded-2xl bg-coral-tint text-coral"><Icon name="mail" :size="26" /></span>
            <h2 class="mt-5 font-display text-2xl font-semibold text-ink">{{ t('Your hotels, in your inbox') }}</h2>
            <p class="mt-2 text-ink-2">{{ t('Choose how often, which hotels and whether you want charts, tables or both. The email arrives after night audit, in Arabic or English.') }}</p>
            <div class="mt-6 grid gap-3 sm:grid-cols-3">
                <div v-for="f in ['daily', 'weekly', 'monthly']" :key="f" class="rounded-2xl bg-paper p-4">
                    <Icon :name="FREQ[f][0]" :size="18" class="text-coral" />
                    <div class="mt-2 font-bold text-ink">{{ t(FREQ[f][1]) }}</div>
                    <div class="mt-0.5 text-xs text-ink-2">{{ covers({ frequency: f }) }}</div>
                </div>
            </div>
            <button type="button" class="btn-coral mt-7" @click="edit()"><Icon name="mail" :size="17" />{{ t('Subscribe') }}</button>
        </div>
    </section>

    <!-- List -->
    <section v-else class="mt-6 grid gap-4 lg:grid-cols-2">
        <article v-for="s in subscriptions" :key="s.id" class="card rise flex flex-col p-5 transition" :class="!s.is_active && 'opacity-70'">
            <div class="flex items-start gap-4">
                <span class="grid size-12 shrink-0 place-items-center rounded-2xl" :class="s.is_active ? 'bg-navy text-white' : 'bg-neutral text-muted'">
                    <Icon :name="FREQ[s.frequency][0]" :size="21" />
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="font-display text-lg font-semibold text-ink">{{ t(FREQ[s.frequency][1]) }} · {{ t('Manager Flash') }}</h3>
                        <span v-if="s.is_active" class="chip bg-success-tint !py-0.5 text-success"><span class="size-1.5 rounded-full bg-success" />{{ t('Active') }}</span>
                        <span v-else class="chip bg-neutral !py-0.5 text-ink-2"><Icon name="lock" :size="11" />{{ t('Paused') }}</span>
                    </div>
                    <p class="mt-0.5 text-sm text-ink-2">{{ when(s) }} <span class="text-muted">· {{ t('Saudi time') }}</span></p>
                </div>
            </div>

            <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 rounded-2xl bg-paper p-4 text-sm">
                <div><dt class="text-[11px] font-bold text-muted">{{ t('Covers') }}</dt><dd class="mt-0.5 font-semibold text-ink">{{ covers(s) }}</dd></div>
                <div><dt class="text-[11px] font-bold text-muted">{{ t('Format') }}</dt>
                    <dd class="mt-0.5 flex items-center gap-1.5 font-semibold text-ink"><Icon :name="CONTENT[s.content][0]" :size="14" class="text-coral" />{{ t(CONTENT[s.content][1]) }}</dd></div>
                <div class="col-span-2 sm:col-span-1"><dt class="text-[11px] font-bold text-muted">{{ t('Hotels') }}</dt><dd class="mt-0.5 font-semibold text-ink">{{ hotelNames(s) }}</dd></div>
                <div><dt class="text-[11px] font-bold text-muted">{{ t('Language') }}</dt><dd class="mt-0.5 font-semibold text-ink">{{ s.locale === 'ar' ? 'العربية' : 'English' }}<span v-if="s.include_attention" class="text-muted"> · {{ t('with needs attention') }}</span></dd></div>
            </dl>

            <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-line pt-4">
                <span class="me-auto text-xs text-muted">
                    <template v-if="sentAt(s.last_sent_at)">{{ t('Last sent') }} <bdi>{{ sentAt(s.last_sent_at) }}</bdi></template>
                    <template v-else>{{ t('Not sent yet') }}</template>
                </span>
                <a :href="`/subscriptions/${s.id}/preview`" target="_blank" rel="noopener" class="rounded-lg px-2.5 py-1.5 text-xs font-bold text-ink-2 hover:bg-paper hover:text-ink">{{ t('Preview') }}</a>
                <button type="button" class="rounded-lg px-2.5 py-1.5 text-xs font-bold text-ink-2 hover:bg-paper hover:text-ink" @click="sendNow(s)">{{ t('Send me one now') }}</button>
                <button type="button" class="rounded-lg px-2.5 py-1.5 text-xs font-bold text-ink-2 hover:bg-paper hover:text-ink" @click="toggle(s)">{{ s.is_active ? t('Pause') : t('Resume') }}</button>
                <button type="button" class="rounded-lg px-2.5 py-1.5 text-xs font-bold text-ink hover:bg-paper" @click="edit(s)">{{ t('Edit') }}</button>
                <button type="button" class="rounded-lg p-1.5 text-muted hover:bg-coral-tint hover:text-coral-dark" :aria-label="t('Remove')" @click="remove(s)"><Icon name="close" :size="16" /></button>
            </div>
        </article>
    </section>

    <SubscribeDialog :open="dialog.open" :subscription="dialog.subscription" @close="dialog.open = false" />
</template>
