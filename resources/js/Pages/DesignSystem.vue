<script setup>
import { Head } from '@inertiajs/vue3';
import PageHeader from '../Components/PageHeader.vue';
import KpiCard from '../Components/ui/KpiCard.vue';
import StatTile from '../Components/ui/StatTile.vue';
import UiButton from '../Components/ui/UiButton.vue';
import Badge from '../Components/ui/Badge.vue';
import IconTile from '../Components/ui/IconTile.vue';
import CardHeader from '../Components/ui/CardHeader.vue';
import EmptyState from '../Components/ui/EmptyState.vue';
import Field from '../Components/ui/Field.vue';
import Donut from '../Components/Charts/Donut.vue';
import Gauge from '../Components/Charts/Gauge.vue';
import ThemeSwitch from '../Components/ThemeSwitch.vue';
import { TONES } from '../Components/ui/tones';
import { BRAND, CHART, SERIES } from '../lib/palette';

/**
 * Living style guide (docs/DESIGN-SYSTEM.md): every building block FGRE screens are made of,
 * with sample data, in the current theme. If a screen needs something that is not here,
 * add it here first, then use it.
 */
const spark = [62, 66, 61, 70, 68, 73, 71, 75, 72, 78, 74, 77];
const TOKENS = [
    ['paper', 'bg-paper', 'Page background'], ['surface', 'bg-surface', 'Cards, inputs'], ['ink', 'bg-ink', 'Text'],
    ['ink-2', 'bg-ink-2', 'Secondary text'], ['muted', 'bg-muted', 'Hints, axes'], ['line', 'bg-line', 'Borders'],
    ['navy', 'bg-navy', 'Brand surface, selected'], ['coral', 'bg-coral', 'Brand accent, primary action'],
    ['peach', 'bg-peach', 'Soft accent'], ['success', 'bg-success', 'Good / balanced'],
];
</script>

<template>
    <Head title="Design system" />
    <PageHeader eyebrow="FGRE" title="Design system" subtitle="One definition for every building block. Screens use these components; they never restyle them.">
        <template #actions><ThemeSwitch /></template>
    </PageHeader>

    <div class="mt-6 space-y-6">
        <!-- Tokens -->
        <section class="card p-6">
            <CardHeader title="Colour tokens" subtitle="Role names, not colours: they switch with the theme. Never use raw white, navy text or hex values in screens." />
            <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-5">
                <div v-for="[name, cls, use] in TOKENS" :key="name" class="overflow-hidden rounded-xl border border-line">
                    <div class="h-12" :class="cls" />
                    <div class="p-2.5"><div class="text-xs font-bold text-ink">{{ name }}</div><div class="text-[11px] text-muted">{{ use }}</div></div>
                </div>
            </div>
        </section>

        <!-- Tones -->
        <section class="card p-6">
            <CardHeader title="Tone scale" subtitle="coral = attention · peach = watch / pending · success = good · navy = strong / selected · neutral = information" />
            <div class="mt-5 flex flex-wrap items-center gap-3">
                <Badge v-for="(cls, tone) in TONES" :key="tone" :tone="tone" icon="check">{{ tone }}</Badge>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <IconTile v-for="(cls, tone) in TONES" :key="tone" :tone="tone" icon="building" />
                <IconTile icon="mail" tone="coral" size="sm" /><IconTile icon="mail" tone="coral" size="lg" />
            </div>
        </section>

        <!-- KPI card -->
        <section class="card p-6">
            <CardHeader title="KPI card" subtitle="The only way to show a key figure. Same anatomy everywhere: dot + label · change · value + unit · note · sparkline / progress." />
            <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <KpiCard label="Occupancy" value="77.7" unit="%" :dot="BRAND.coral" :current="77.7" :previous="71.6" kind="pp" note="443 of 570 rooms sold" :spark="spark" />
                <KpiCard label="ADR" value="591.93" unit="SAR" :dot="BRAND.navy" :current="591.93" :previous="578.65" previous-label="578.65" :spark="spark" />
                <KpiCard label="Hotels reported" value="4 / 5" note="Waiting for: Abha Heights" highlight :progress="80" />
                <KpiCard label="Debits = credits" value="Balanced" value-tone="success" icon="check" icon-tone="success" note="20 accounts · 5 hotels" />
                <KpiCard label="Needs review" value="3" icon="alert" icon-tone="coral" href="/documents?status=needs_review" note="As a link card" />
            </div>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <KpiCard label="Finance" value="7 / 10" unit="permissions" :badge="{ label: 'Customised', tone: 'peach' }" note="1 person" :progress="70" />
                <KpiCard label="Owner" value="1" selectable active icon="shield" icon-tone="navy" note="As a filter (selected)" />
            </div>
        </section>

        <!-- Stat tile -->
        <section class="card p-6">
            <CardHeader title="Stat tile" subtitle="A small figure inside a card." />
            <ul class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <li><StatTile label="Arrivals" value="11" icon="login" :current="11" :previous="9" /></li>
                <li><StatTile label="Departures" value="9" icon="logout" :current="9" :previous="12" /></li>
            </ul>
        </section>

        <!-- Buttons -->
        <section class="card p-6">
            <CardHeader title="Buttons" subtitle="primary: the main action (one per view) · secondary: other actions · ghost: row and card actions · danger: destructive" />
            <div class="mt-5 flex flex-wrap items-center gap-3">
                <UiButton icon="mail">Primary</UiButton>
                <UiButton variant="secondary" icon="table">Secondary</UiButton>
                <UiButton variant="ghost">Ghost</UiButton>
                <UiButton variant="danger" icon="trash">Remove</UiButton>
                <UiButton size="sm">Small</UiButton>
                <UiButton size="lg" icon-end="chevron">Large</UiButton>
                <UiButton disabled>Disabled</UiButton>
            </div>
            <div class="mt-4 flex flex-wrap gap-3 rounded-2xl bg-navy p-4"><UiButton variant="on-dark" icon="globe">On navy</UiButton></div>
        </section>

        <!-- Card header, fields, segmented -->
        <section class="grid gap-6 xl:grid-cols-2">
            <div class="card p-6">
                <CardHeader title="Card header" subtitle="Title, subtitle, meta and actions" meta="Day · 570 rooms" icon="chart">
                    <template #actions><UiButton variant="secondary" size="sm">Action</UiButton></template>
                </CardHeader>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <Field label="Hotel name" hint="As the PMS prints it."><input class="field" value="Al Waha Hotel" /></Field>
                    <Field label="Rooms" error="Give the number of rooms."><input class="field" value="" /></Field>
                </div>
                <div class="seg mt-5 w-fit"><button type="button" aria-pressed="true">Day</button><button type="button" aria-pressed="false">Month to date</button><button type="button" aria-pressed="false">Year to date</button></div>
            </div>
            <EmptyState icon="inbox" title="No report received yet" text="Empty states say what is missing and what to do next.">
                <UiButton variant="secondary" size="sm">Upload a report</UiButton>
            </EmptyState>
        </section>

        <!-- Charts -->
        <section class="grid gap-6 xl:grid-cols-3">
            <div class="card p-6">
                <CardHeader title="Chart colours" subtitle="Fixed roles, never cycled. Validated for both themes." />
                <div class="mt-5 space-y-2 text-xs text-ink-2">
                    <div class="flex items-center gap-2"><span class="h-2.5 w-8 rounded-full" :style="{ background: CHART.thisYear }" />This period</div>
                    <div class="flex items-center gap-2"><span class="h-2.5 w-8 rounded-full" :style="{ background: CHART.lastYearBar }" />Last year</div>
                    <div class="flex items-center gap-2"><span class="h-2.5 w-8 rounded-full" :style="{ background: CHART.average }" />Average / highlight</div>
                    <div class="flex items-center gap-1.5 pt-2"><span v-for="c in SERIES" :key="c" class="size-6 rounded-md" :style="{ background: c }" /><span class="ms-1">Series 1–5 (parts, hotels)</span></div>
                </div>
            </div>
            <div class="card p-6">
                <CardHeader title="Donut" subtitle="Parts of a whole, ≤ 6 parts" />
                <div class="mt-5"><Donut :segments="[{ key: 'a', label: 'Sold', value: 443, color: BRAND.coral }, { key: 'b', label: 'Vacant', value: 121, color: BRAND.lightGrey }, { key: 'c', label: 'Out of order', value: 6, color: BRAND.muted }]"
                                         :format="(v) => String(v)" center-value="77.7%" center-label="Occupancy" :size="210" /></div>
            </div>
            <div class="card p-6">
                <CardHeader title="Gauge" subtitle="One ratio against last year" />
                <div class="mt-5"><Gauge :value="77.7" :previous="71.6" label="Occupancy" /></div>
            </div>
        </section>
    </div>
</template>
