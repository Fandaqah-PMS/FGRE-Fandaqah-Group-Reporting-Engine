<script setup>
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { money } from '../lib/format';
import { useI18n } from '../lib/i18n';

const props = defineProps({
    dates: Array, date: String, periodStart: String, hotels: Array, accounts: Array, ledgerDate: String, ledgers: Object,
});
const { t, name } = useI18n();

// Signed balances: debit positive, credit shown in parentheses (accounting style).
const bal = (v) => {
    if (v === undefined || v === null) return '';
    const n = Math.abs(Number(v)) < 0.005 ? 0 : Number(v);   // no "-0" from rounding dust
    return n < 0 ? `(${money(-n, 0)})` : money(n, 0);
};

const classes = computed(() => ({
    1: t('Assets'), 2: t('Liabilities'), 3: t('Equity'), 4: t('Revenue'), 5: t('Expenses'), 6: t('Expenses'), 7: t('Expenses'), 8: t('Other'), 9: t('Other'),
}));

const sections = computed(() => {
    const out = [];
    for (const account of props.accounts) {
        const label = classes.value[account.class] ?? t('Other');
        let section = out.find((s) => s.label === label);
        if (!section) out.push((section = { label, accounts: [] }));
        section.accounts.push(account);
    }
    return out;
});

const hotelTotal = (hotelId) => props.accounts.reduce((s, a) => s + Number(a.hotels[hotelId] ?? 0), 0);
const grandTotal = computed(() => props.accounts.reduce((s, a) => s + Number(a.total), 0));
const balanced = computed(() => Math.abs(grandTotal.value) < 0.05);

const ledgerKinds = computed(() => [['guest', t('Guest ledger')], ['ar', t('AR ledger')], ['deposit', t('Deposit ledger')]]);
</script>

<template>
    <Head :title="t('Financials')" />

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl font-semibold">{{ t('Consolidated trial balance') }}</h1>
            <p class="mt-1 text-sm text-secondary">
                {{ t('Closing balances by account and hotel. Credit balances in parentheses.') }}
                <span v-if="periodStart" class="tabular" dir="ltr">{{ periodStart }} → {{ date }}</span>
            </p>
        </div>
        <select v-if="dates.length" :value="date" dir="ltr" class="rounded-xl border border-line bg-white px-3 py-2 text-sm outline-none focus:border-coral focus:ring-2 focus:ring-coral/25"
                :aria-label="t('Period end')" @change="router.get('/financials', { date: $event.target.value })">
            <option v-for="d in dates" :key="d" :value="d">{{ d }}</option>
        </select>
    </div>

    <div v-if="!accounts.length" class="card mt-6 p-8 text-center text-secondary">{{ t('No trial balance received yet.') }}</div>

    <section v-else class="card mt-6 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm tabular" :style="{ minWidth: `${24 + hotels.length * 9}rem` }">
                <thead class="border-b border-line bg-off-white/60 text-xs text-secondary">
                    <tr>
                        <th class="sticky start-0 bg-off-white px-5 py-2.5 text-start font-medium">{{ t('Account') }}</th>
                        <th v-for="h in hotels" :key="h.id" class="px-3 text-end font-medium">{{ name(h) }}</th>
                        <th class="px-5 text-end font-semibold text-navy">{{ t('Group') }}</th>
                    </tr>
                </thead>
                <tbody v-for="section in sections" :key="section.label">
                    <tr class="bg-warm/50"><td :colspan="hotels.length + 2" class="px-5 py-1.5 text-xs font-semibold uppercase tracking-wide text-secondary">{{ section.label }}</td></tr>
                    <tr v-for="a in section.accounts" :key="a.code" class="border-b border-line hover:bg-off-white/50">
                        <td class="sticky start-0 bg-white px-5 py-2"><span class="me-2 text-grey" dir="ltr">{{ a.code }}</span>{{ a.name }}</td>
                        <td v-for="h in hotels" :key="h.id" class="px-3 text-end"><bdi>{{ bal(a.hotels[h.id]) }}</bdi></td>
                        <td class="px-5 text-end font-semibold"><bdi>{{ bal(a.total) }}</bdi></td>
                    </tr>
                </tbody>
                <tfoot class="border-t-2 border-navy/15 bg-off-white/60 font-semibold">
                    <tr>
                        <td class="sticky start-0 bg-off-white px-5 py-3">{{ t('Net (should be zero)') }}</td>
                        <td v-for="h in hotels" :key="h.id" class="px-3 text-end"><bdi>{{ bal(hotelTotal(h.id).toFixed(2)) }}</bdi></td>
                        <td class="px-5 text-end">
                            <bdi><span :class="balanced ? 'text-success' : 'text-danger'">{{ balanced ? '✓' : '!' }}</span> {{ bal(grandTotal.toFixed(2)) }}</bdi>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    <section v-if="ledgerDate" class="card mt-4 overflow-hidden">
        <div class="px-5 pt-5 pb-3">
            <h2 class="font-display text-lg font-semibold">{{ t('Front-office ledgers') }}</h2>
            <p class="text-xs text-grey">{{ t('Balance today from the night-audit trial balance') }} · <span dir="ltr">{{ ledgerDate }}</span></p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[36rem] text-sm tabular">
                <thead class="border-y border-line bg-off-white/60 text-xs text-secondary">
                    <tr>
                        <th class="px-5 py-2.5 text-start font-medium">{{ t('Hotel') }}</th>
                        <th v-for="[k, label] in ledgerKinds" :key="k" class="px-3 text-end font-medium">{{ label }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="h in hotels.filter((h) => ledgers[h.id])" :key="h.id" class="border-b border-line last:border-0">
                        <td class="px-5 py-2.5 font-medium">{{ name(h) }}</td>
                        <td v-for="[k] in ledgerKinds" :key="k" class="px-3 text-end"><bdi>{{ bal(ledgers[h.id][k]) }}</bdi></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
