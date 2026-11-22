<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PageHeader from '../Components/PageHeader.vue';
import StatusBadge from '../Components/StatusBadge.vue';
import Icon from '../Components/Icon.vue';
import { int } from '../lib/format';
import { useI18n } from '../lib/i18n';

const props = defineProps({ documents: Object, status: String, counts: Object });
const { t, name } = useI18n();
const page = usePage();
const canReview = computed(() => page.props.auth?.can?.['review documents']);
const open = ref(null);

const total = computed(() => Object.values(props.counts ?? {}).reduce((s, n) => s + Number(n), 0));
const tiles = computed(() => [
    { key: null, label: t('All reports'), icon: 'inbox', value: total.value, tone: 'bg-navy text-white' },
    { key: 'processed', label: t('Processed'), icon: 'check', value: props.counts?.processed ?? 0, tone: 'bg-success-tint text-success' },
    { key: 'needs_review', label: t('Needs review'), icon: 'alert', value: props.counts?.needs_review ?? 0, tone: 'bg-coral-tint text-coral' },
    { key: 'failed', label: t('Failed'), icon: 'close', value: props.counts?.failed ?? 0, tone: 'bg-neutral text-ink-2' },
]);
const reprocess = (id) => router.post(`/documents/${id}/reprocess`, {}, { preserveScroll: true });
const sourceIcon = (s) => ({ email: 'mail', mcp: 'layers', upload: 'inbox', api: 'globe' }[s] ?? 'inbox');
</script>

<template>
    <Head :title="t('Reports received')" />

    <PageHeader :eyebrow="t('Data / Inbox')" :title="t('Reports received')"
                :subtitle="t('Every report FGRE received by email, MCP or upload. Reports that failed a check wait here for review.')" />

    <section class="mt-6 grid grid-cols-2 gap-4 xl:grid-cols-4">
        <Link v-for="tile in tiles" :key="tile.key ?? 'all'" :href="tile.key ? `/documents?status=${tile.key}` : '/documents'" preserve-scroll
              class="card rise flex items-center gap-4 p-5 transition hover:-translate-y-0.5"
              :class="(status ?? null) === tile.key && 'ring-2 ring-coral'">
            <span class="grid size-12 place-items-center rounded-2xl" :class="tile.tone"><Icon :name="tile.icon" :size="22" /></span>
            <span>
                <span class="block text-xl font-semibold text-ink tabular" dir="ltr">{{ int(tile.value) }}</span>
                <span class="block text-[13px] font-semibold text-ink-2">{{ tile.label }}</span>
            </span>
        </Link>
    </section>

    <section class="card mt-6 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="tbl min-w-[56rem]">
                <thead><tr>
                    <th>{{ t('Received') }}</th><th>{{ t('Hotel') }}</th><th>{{ t('Report') }}</th><th>{{ t('Business date') }}</th><th>{{ t('Source') }}</th><th>{{ t('Status') }}</th><th />
                </tr></thead>
                <tbody>
                    <template v-for="d in documents.data" :key="d.id">
                        <tr :class="d.reason && 'cursor-pointer'" @click="d.reason && (open = open === d.id ? null : d.id)">
                            <td class="text-ink-2 tabular"><bdi>{{ d.received.slice(0, 16) }}</bdi></td>
                            <td class="font-bold text-ink">{{ d.hotel ? name(d.hotel) : '–' }}</td>
                            <td>{{ t(d.type) }}<span v-if="d.provisional" class="chip ms-1 bg-coral-tint !px-2 !py-0 text-[10px] text-coral-dark">{{ t('Provisional') }}</span></td>
                            <td class="tabular"><bdi>{{ d.period_start ? `${d.period_start} → ` : '' }}{{ d.date ?? '–' }}</bdi></td>
                            <td class="text-ink-2"><span class="flex max-w-56 items-center gap-1.5 truncate" :title="d.from"><Icon :name="sourceIcon(d.source)" :size="15" class="text-coral" />{{ t(d.source ?? '') }}<template v-if="d.from"> · {{ d.from }}</template></span></td>
                            <td><StatusBadge :status="d.status" /></td>
                            <td class="text-end">
                                <button v-if="canReview && ['needs_review', 'failed'].includes(d.status)" type="button"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-line px-2.5 py-1.5 text-xs font-bold text-ink hover:border-coral hover:text-coral" @click.stop="reprocess(d.id)">
                                    <Icon name="refresh" :size="14" />{{ t('Run again') }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="open === d.id"><td colspan="7" class="!bg-coral-tint/50 text-sm whitespace-pre-line text-ink"><Icon name="alert" :size="15" class="me-1 inline text-coral" />{{ d.reason }}</td></tr>
                    </template>
                    <tr v-if="!documents.data.length"><td colspan="7" class="py-12 text-center text-ink-2">{{ t('Nothing here.') }}</td></tr>
                </tbody>
            </table>
        </div>
        <div v-if="documents.last_page > 1" class="flex items-center justify-between border-t border-line px-5 py-3 text-sm">
            <bdi class="text-ink-2 tabular">{{ documents.from }}–{{ documents.to }} / {{ documents.total }}</bdi>
            <div class="flex gap-2">
                <Link v-if="documents.prev_page_url" :href="documents.prev_page_url" preserve-scroll class="rounded-lg border border-line px-3 py-1.5 font-semibold">{{ t('Previous') }}</Link>
                <Link v-if="documents.next_page_url" :href="documents.next_page_url" preserve-scroll class="rounded-lg border border-line px-3 py-1.5 font-semibold">{{ t('Next') }}</Link>
            </div>
        </div>
    </section>
</template>
