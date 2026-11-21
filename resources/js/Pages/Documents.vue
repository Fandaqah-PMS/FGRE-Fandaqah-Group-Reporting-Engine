<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import StatusBadge from '../Components/StatusBadge.vue';
import { useI18n } from '../lib/i18n';

const props = defineProps({ documents: Object, status: String, counts: Object });
const { t, name } = useI18n();
const page = usePage();
const canReview = computed(() => page.props.auth?.can?.['review documents']);

const tabs = computed(() => [
    { key: null, label: t('All') },
    { key: 'needs_review', label: t('Needs review') },
    { key: 'failed', label: t('Failed') },
    { key: 'processed', label: t('Processed') },
]);
const total = computed(() => Object.values(props.counts ?? {}).reduce((s, n) => s + Number(n), 0));
const open = ref(null);

const reprocess = (id) => router.post(`/documents/${id}/reprocess`, {}, { preserveScroll: true });
</script>

<template>
    <Head :title="t('Reports received')" />

    <h1 class="font-display text-3xl font-semibold">{{ t('Reports received') }}</h1>
    <p class="mt-1 text-sm text-secondary">{{ t('Every report FGRE received by email, MCP or upload. Reports that failed a check wait here for review.') }}</p>

    <div class="mt-5 flex flex-wrap gap-2">
        <Link v-for="tab in tabs" :key="tab.key ?? 'all'" :href="tab.key ? `/documents?status=${tab.key}` : '/documents'" preserve-scroll
              class="rounded-full border px-3 py-1.5 text-sm font-medium"
              :class="status === tab.key || (!status && !tab.key) ? 'border-navy bg-navy text-white' : 'border-line bg-white text-secondary hover:text-navy'">
            {{ tab.label }}
            <span class="ms-1 tabular opacity-70">{{ tab.key ? counts[tab.key] ?? 0 : total }}</span>
        </Link>
    </div>

    <section class="card mt-4 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[52rem] text-sm">
                <thead class="border-b border-line bg-off-white/60 text-xs text-secondary">
                    <tr>
                        <th class="px-5 py-2.5 text-start font-medium">{{ t('Received') }}</th>
                        <th class="px-3 text-start font-medium">{{ t('Hotel') }}</th>
                        <th class="px-3 text-start font-medium">{{ t('Report') }}</th>
                        <th class="px-3 text-start font-medium">{{ t('Business date') }}</th>
                        <th class="px-3 text-start font-medium">{{ t('Source') }}</th>
                        <th class="px-3 text-start font-medium">{{ t('Status') }}</th>
                        <th class="px-5" />
                    </tr>
                </thead>
                <tbody>
                    <template v-for="d in documents.data" :key="d.id">
                        <tr class="border-b border-line" :class="d.reason && 'cursor-pointer hover:bg-off-white/50'" @click="d.reason && (open = open === d.id ? null : d.id)">
                            <td class="px-5 py-2.5 text-secondary tabular" dir="ltr">{{ d.received.slice(0, 16) }}</td>
                            <td class="px-3 font-medium">{{ d.hotel ? name(d.hotel) : '–' }}</td>
                            <td class="px-3">{{ t(d.type) }}<span v-if="d.provisional" class="ms-1 text-xs text-coral-text">({{ t('Provisional') }})</span></td>
                            <td class="px-3 tabular" dir="ltr">{{ d.period_start ? `${d.period_start} → ` : '' }}{{ d.date ?? '–' }}</td>
                            <td class="px-3 text-secondary"><span class="block max-w-48 truncate" :title="d.from">{{ t(d.source ?? '') }}<template v-if="d.from"> · {{ d.from }}</template></span></td>
                            <td class="px-3"><StatusBadge :status="d.status" /></td>
                            <td class="px-5 text-end">
                                <button v-if="canReview && ['needs_review', 'failed'].includes(d.status)" type="button"
                                        class="rounded-lg border border-line px-2.5 py-1 text-xs font-medium text-secondary hover:border-navy hover:text-navy"
                                        @click.stop="reprocess(d.id)">{{ t('Run again') }}</button>
                            </td>
                        </tr>
                        <tr v-if="open === d.id" class="border-b border-line bg-coral-tint/40">
                            <td colspan="7" class="px-5 py-3 text-sm whitespace-pre-line text-navy">{{ d.reason }}</td>
                        </tr>
                    </template>
                    <tr v-if="!documents.data.length"><td colspan="7" class="px-5 py-10 text-center text-secondary">{{ t('Nothing here.') }}</td></tr>
                </tbody>
            </table>
        </div>
        <div v-if="documents.last_page > 1" class="flex items-center justify-between border-t border-line px-5 py-3 text-sm">
            <span class="text-secondary tabular">{{ documents.from }}–{{ documents.to }} / {{ documents.total }}</span>
            <div class="flex gap-2">
                <Link v-if="documents.prev_page_url" :href="documents.prev_page_url" preserve-scroll class="rounded-lg border border-line px-3 py-1">{{ t('Previous') }}</Link>
                <Link v-if="documents.next_page_url" :href="documents.next_page_url" preserve-scroll class="rounded-lg border border-line px-3 py-1">{{ t('Next') }}</Link>
            </div>
        </div>
    </section>
</template>
