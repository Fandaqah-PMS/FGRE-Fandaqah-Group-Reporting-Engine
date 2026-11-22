<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PageHeader from '../Components/PageHeader.vue';
import ViewToggle from '../Components/ViewToggle.vue';
import KpiCard from '../Components/ui/KpiCard.vue';
import Icon from '../Components/Icon.vue';
import DivergingBars from '../Components/Charts/DivergingBars.vue';
import ReportToolbar from '../Components/Report/ReportToolbar.vue';
import { downloadCsv, printPage } from '../lib/export';
import { money } from '../lib/format';
import { BRAND } from '../lib/palette';
import { useViewMode } from '../lib/view';
import { useI18n } from '../lib/i18n';
import EmptyState from '../Components/ui/EmptyState.vue';

const props = defineProps({ dates: Array, date: String, rows: Array, totals: Object, folios: Object });
const { t, name } = useI18n();
const page = usePage();
const view = useViewMode();

const scope = computed(() => page.props.scope ?? { current: null, hotels: [] });
const scopeHotel = computed(() => scope.value.hotels.find((h) => h.id === scope.value.current) ?? null);
const bal = (v) => (v === null || v === undefined ? '–' : Number(v) < 0 ? `(${money(-v, 0)})` : money(v, 0));

const ledgers = computed(() => [
    { key: 'guest', label: t('Guest ledger'), icon: 'users', help: t('What in-house guests owe (credit = paid in advance).'), color: BRAND.navy },
    { key: 'ar', label: t('AR ledger'), icon: 'wallet', help: t('What companies and agents owe the hotel.'), color: BRAND.coral },
    { key: 'deposit', label: t('Deposit ledger'), icon: 'coins', help: t('Advance deposits held for future stays.'), color: BRAND.muted },
]);
function exportCsv() {
    downloadCsv(`front-office-ledgers-${props.date}.csv`, [
        [t('Hotel'), ...ledgers.value.map((l) => l.label), t('Hotel balance')],
        ...props.rows.map((r) => [name(r), r.guest, r.ar, r.deposit, Number(r.guest ?? 0) + Number(r.ar ?? 0) + Number(r.deposit ?? 0)]),
    ]);
}
const allFolios = ref(false);
const folioRows = computed(() => (allFolios.value ? props.folios?.list : props.folios?.list.slice(0, 10)) ?? []);
const folioMax = computed(() => Math.max(1, ...(props.folios?.hotels ?? []).map((h) => h.owing)));
const stay = (f) => [f.arrival, f.departure].filter(Boolean).map((d) => d.slice(5).split('-').reverse().join('/')).join(' → ');
function exportFolios() {
    downloadCsv(`guest-folios-${props.date}.csv`, [
        [t('Room'), t('Guest'), t('Hotel'), t('Arrival'), t('Departure'), t('Charges'), t('Payments'), t('Balance'), t('Status')],
        ...props.folios.list.map((f) => [f.room, f.guest, name(f.hotel), f.arrival, f.departure, f.charges, f.payments, f.balance, f.status]),
    ]);
}
const hotelBalance = computed(() => ['guest', 'ar', 'deposit'].reduce((s, k) => s + Number(props.totals?.[k] ?? 0), 0));
</script>

<template>
    <Head :title="t('Front Office Ledgers')" />

    <PageHeader :eyebrow="t('Reports / Front office')" :title="t('Front Office Ledgers')">
        <template #subtitle>
            <Icon :name="scopeHotel ? 'building' : 'layers'" :size="15" class="text-coral" />
            <b class="text-ink">{{ scopeHotel ? name(scopeHotel) : t('All hotels, consolidated') }}</b>
            <template v-if="date"><span class="text-muted">·</span>{{ t('Balance today from the night-audit trial balance') }} <bdi class="tabular">{{ date }}</bdi></template>
        </template>
        <template #actions>
            <select v-if="dates.length > 1" :value="date" dir="ltr" class="field !w-auto !py-2 text-sm font-semibold" :aria-label="t('Business date')"
                    @change="router.get('/ledgers', { date: $event.target.value }, { preserveScroll: true })">
                <option v-for="d in dates" :key="d" :value="d">{{ d }}</option>
            </select>
            <ViewToggle />
        </template>
    </PageHeader>

    <ReportToolbar v-if="rows.length" class="mt-5" :views="[]" :bases="[]" :can-export="!!page.props.auth?.can?.['export reports']" @export="exportCsv" @print="printPage" />

    <EmptyState v-if="!rows.length" class="mt-6" icon="book" :title="t('No front-office trial balance received yet.')" />

    <div v-else class="mt-6 space-y-6">
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <KpiCard v-for="l in ledgers" :key="l.key" :dot="l.color" :label="l.label" :value="bal(totals[l.key])" unit="SAR" />
            <KpiCard :dot="BRAND.peach" :label="t('Hotel balance')" :value="bal(hotelBalance)" unit="SAR" />
        </section>

        <section v-if="view === 'charts'" class="grid gap-6 xl:grid-cols-3">
            <div v-for="l in ledgers" :key="l.key" class="card rise p-6">
                <div class="flex items-center gap-2">
                    <span class="size-3 rounded-[4px]" :style="{ background: l.color }" />
                    <h3 class="card-title">{{ l.label }}</h3>
                </div>
                <p class="mb-5 text-xs text-muted">{{ l.help }}</p>
                <DivergingBars :rows="rows.map((r) => ({ id: r.id, name: r.name, name_ar: r.name_ar, value: r[l.key] }))" :format="(v) => bal(v)" :color="l.color" />
                <div class="mt-4 flex justify-between text-[11px] font-semibold text-muted"><span>{{ t('Credit') }}</span><span>{{ t('Debit') }}</span></div>
            </div>
        </section>

        <section v-else class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="tbl min-w-[44rem] tabular">
                    <thead><tr>
                        <th>{{ t('Hotel') }}</th>
                        <th v-for="l in ledgers" :key="l.key" class="!text-end">{{ l.label }}</th>
                        <th class="!text-end">{{ t('Hotel balance') }}</th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="r in rows" :key="r.id">
                            <td><Link :href="`/hotels/${r.id}`" class="font-bold text-ink hover:text-coral">{{ name(r) }}</Link></td>
                            <td v-for="l in ledgers" :key="l.key" class="text-end">
                                <bdi>{{ bal(r[l.key]) }}</bdi>
                                <div class="text-xs text-muted">{{ t('Yesterday') }} <bdi>{{ bal(r.opening[l.key]) }}</bdi></div>
                            </td>
                            <td class="text-end font-semibold"><bdi>{{ bal(Number(r.guest ?? 0) + Number(r.ar ?? 0) + Number(r.deposit ?? 0)) }}</bdi></td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-paper font-bold">
                        <tr>
                            <td class="px-4 py-3">{{ t('Group total') }}</td>
                            <td v-for="l in ledgers" :key="l.key" class="px-4 text-end"><bdi>{{ bal(totals[l.key]) }}</bdi></td>
                            <td class="px-4 text-end"><bdi>{{ bal(hotelBalance) }}</bdi></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
        <p class="text-xs text-muted">{{ t('Credit balances in parentheses.') }}</p>

        <!-- Guest folios (guest trial balance) -->
        <template v-if="folios">
            <div class="pt-4">
                <div class="eyebrow">{{ t('Guest trial balance') }}</div>
                <h2 class="mt-1 font-display text-2xl font-semibold text-ink">{{ t('Guest folios') }}</h2>
                <p class="text-sm text-ink-2">{{ t('What each guest and company account owes at night audit.') }}</p>
            </div>
            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard :dot="BRAND.navy" :label="t('Owed by guests')" :value="bal(folios.owing.amount)" unit="SAR" :note="t(':n open folios', { n: folios.owing.count })" />
                <KpiCard :dot="BRAND.muted" :label="t('Paid in advance')" :value="bal(folios.credit.amount)" unit="SAR" :note="t(':n folios in credit', { n: folios.credit.count })" />
                <KpiCard :dot="BRAND.coral" :label="t('Checked out, still owing')" :value="bal(folios.unpaid.amount)" unit="SAR" :highlight="folios.unpaid.count > 0"
                         :note="folios.unpaid.count ? t(':n guests left without settling', { n: folios.unpaid.count }) : t('Every departed guest has settled.')" />
                <KpiCard :dot="BRAND.green" :label="t('Guests in house')" :value="String(folios.in_house)" :note="t('Folios of guests still staying')" />
            </section>

            <section class="grid gap-6 2xl:grid-cols-[22rem_1fr]">
                <div class="card rise p-6">
                    <h3 class="card-title">{{ t('By hotel') }}</h3>
                    <p class="mb-5 card-sub">{{ t('Owed by guests, and the part from guests who already left.') }}</p>
                    <ul class="space-y-4">
                        <li v-for="h in folios.hotels" :key="h.id">
                            <div class="flex items-baseline justify-between gap-2 text-sm">
                                <span class="truncate font-bold text-ink">{{ name(h) }}</span>
                                <bdi class="font-medium tabular">{{ bal(h.owing) }}</bdi>
                            </div>
                            <div class="mt-1.5 flex h-2.5 gap-0.5 overflow-hidden rounded-full bg-paper">
                                <span class="h-full rounded-full" :style="{ width: `${(100 * (h.owing - h.unpaid)) / folioMax}%`, background: BRAND.navy }" />
                                <span v-if="h.unpaid" class="h-full rounded-full" :style="{ width: `${(100 * h.unpaid) / folioMax}%`, background: BRAND.coral }" />
                            </div>
                            <div class="mt-1 flex justify-between text-[11px] text-muted">
                                <span>{{ t(':n folios', { n: h.folios }) }}</span>
                                <span v-if="h.unpaid" class="font-semibold text-coral-dark">{{ t('Checked out') }} <bdi>{{ bal(h.unpaid) }}</bdi></span>
                            </div>
                        </li>
                    </ul>
                    <div class="mt-5 flex flex-wrap gap-4 text-[11px] font-semibold text-ink-2">
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm" :style="{ background: BRAND.navy }" />{{ t('In house') }}</span>
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm" :style="{ background: BRAND.coral }" />{{ t('Checked out, still owing') }}</span>
                    </div>
                </div>

                <div class="card min-w-0 overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-2 px-6 pt-5 pb-3">
                        <div>
                            <h3 class="card-title">{{ t('Folios to follow up') }}</h3>
                            <p class="card-sub">{{ t('Unpaid check-outs first, then the largest balances.') }}</p>
                        </div>
                        <button v-if="page.props.auth?.can?.['export reports']" type="button" class="rounded-lg border border-line px-3 py-1.5 text-xs font-bold text-ink-2 hover:bg-paper" @click="exportFolios">
                            {{ t('Export') }}
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="tbl min-w-[52rem] tabular">
                            <thead><tr>
                                <th>{{ t('Room') }}</th><th>{{ t('Status') }}</th><th>{{ t('Guest') }}</th><th v-if="!scopeHotel">{{ t('Hotel') }}</th><th>{{ t('Stay') }}</th>
                                <th class="!text-end">{{ t('Charges') }}</th><th class="!text-end">{{ t('Payments') }}</th><th class="!text-end">{{ t('Balance') }}</th>
                            </tr></thead>
                            <tbody>
                                <tr v-for="(f, i) in folioRows" :key="i">
                                    <td class="font-bold text-ink"><bdi>{{ f.room ?? '–' }}</bdi></td>
                                    <td>
                                        <span v-if="f.unpaid" class="chip !px-2 !py-0.5 text-[11px] whitespace-nowrap bg-coral text-white"><Icon name="alert" :size="11" />{{ t('Left unpaid') }}</span>
                                        <span v-else-if="f.balance < 0" class="chip !px-2 !py-0.5 text-[11px] whitespace-nowrap bg-neutral text-ink-2">{{ t('In credit') }}</span>
                                        <span v-else-if="f.status === 'in_house'" class="chip !px-2 !py-0.5 text-[11px] whitespace-nowrap bg-success-tint text-success">{{ t('In house') }}</span>
                                        <span v-else class="chip !px-2 !py-0.5 text-[11px] whitespace-nowrap bg-paper text-ink-2">{{ t('Other') }}</span>
                                    </td>
                                    <td class="max-w-[14rem] truncate whitespace-nowrap">{{ f.guest ?? '–' }}</td>
                                    <td v-if="!scopeHotel" class="whitespace-nowrap text-ink-2">{{ name(f.hotel) }}</td>
                                    <td class="text-xs whitespace-nowrap text-ink-2"><bdi>{{ stay(f) }}</bdi></td>
                                    <td class="text-end"><bdi>{{ money(f.charges, 0) }}</bdi></td>
                                    <td class="text-end"><bdi>{{ money(f.payments, 0) }}</bdi></td>
                                    <td class="text-end font-semibold" :class="f.balance < 0 ? 'text-ink-2' : 'text-ink'"><bdi>{{ bal(f.balance) }}</bdi></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <button v-if="folios.list.length > 10" type="button" class="w-full border-t border-line px-6 py-3 text-start text-xs font-bold text-coral hover:bg-paper" @click="allFolios = !allFolios">
                        {{ allFolios ? t('Show less') : t('Show all :n', { n: folios.list.length }) }}
                    </button>
                </div>
            </section>
        </template>
    </div>
</template>
