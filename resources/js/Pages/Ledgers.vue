<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PageHeader from '../Components/PageHeader.vue';
import ViewToggle from '../Components/ViewToggle.vue';
import KpiCard from '../Components/KpiCard.vue';
import Icon from '../Components/Icon.vue';
import DivergingBars from '../Components/Charts/DivergingBars.vue';
import { money } from '../lib/format';
import { BRAND } from '../lib/palette';
import { useViewMode } from '../lib/view';
import { useI18n } from '../lib/i18n';

const props = defineProps({ dates: Array, date: String, rows: Array, totals: Object });
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
const hotelBalance = computed(() => ['guest', 'ar', 'deposit'].reduce((s, k) => s + Number(props.totals?.[k] ?? 0), 0));
</script>

<template>
    <Head :title="t('Front Office Ledgers')" />

    <PageHeader :eyebrow="t('Reports / Front office')" :title="t('Front Office Ledgers')">
        <template #subtitle>
            <Icon :name="scopeHotel ? 'building' : 'layers'" :size="15" class="text-coral" />
            <b class="text-navy">{{ scopeHotel ? name(scopeHotel) : t('All hotels, consolidated') }}</b>
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

    <div v-if="!rows.length" class="card mt-6 p-10 text-center text-ink-2">{{ t('No front-office trial balance received yet.') }}</div>

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
                            <td><Link :href="`/hotels/${r.id}`" class="font-bold text-navy hover:text-coral">{{ name(r) }}</Link></td>
                            <td v-for="l in ledgers" :key="l.key" class="text-end">
                                <bdi>{{ bal(r[l.key]) }}</bdi>
                                <div class="text-xs text-muted">{{ t('Yesterday') }} <bdi>{{ bal(r.opening[l.key]) }}</bdi></div>
                            </td>
                            <td class="text-end font-bold"><bdi>{{ bal(Number(r.guest ?? 0) + Number(r.ar ?? 0) + Number(r.deposit ?? 0)) }}</bdi></td>
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
    </div>
</template>
