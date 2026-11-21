<script setup>
import { computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import Icon from '../Icon.vue';
import EmailPreview from './EmailPreview.vue';
import { useI18n } from '../../lib/i18n';

/**
 * Subscribe to the group report by email: how often, when, charts / tables / both,
 * which hotels, language. Opens prefilled from the page (current hotels and view);
 * `subscription` = edit an existing one.
 */
const props = defineProps({
    open: Boolean,
    subscription: { type: Object, default: null },
    defaults: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['close']);
const { t, name, locale } = useI18n();
const page = usePage();

const hotels = computed(() => page.props.scope?.hotels ?? []);
const email = computed(() => page.props.auth?.user?.email ?? '');

const blank = () => ({
    frequency: 'daily', send_time: '08:00', weekday: 0, month_day: 1, content: 'both',
    hotels: hotels.value.map((h) => h.id), include_attention: true, locale: locale.value,
});
const form = useForm(blank());

watch(() => props.open, (open) => {
    if (!open) return;
    const s = props.subscription;
    const base = blank();
    const values = s
        ? { ...base, ...s, weekday: s.weekday ?? 0, month_day: s.month_day ?? 1, hotels: s.hotels ?? base.hotels }
        : { ...base, ...props.defaults, hotels: props.defaults.hotels?.length ? [...props.defaults.hotels] : base.hotels };
    form.defaults(values);
    form.reset();
    form.clearErrors();
});

const FREQ = computed(() => [
    { key: 'daily', icon: 'calendar', label: t('Daily'), text: t('Every morning after night audit: the day and month to date.') },
    { key: 'weekly', icon: 'chart', label: t('Weekly'), text: t('The last 7 days, day by day, and month to date.') },
    { key: 'monthly', icon: 'layers', label: t('Monthly'), text: t('The previous month and year to date.') },
]);
const CONTENT = computed(() => [
    { key: 'charts', icon: 'pie', label: t('Charts'), text: t('KPI tiles and bar charts per hotel.') },
    { key: 'tables', icon: 'table', label: t('Tables'), text: t('Hotels side by side, plus an Excel (CSV) file.') },
    { key: 'both', icon: 'grid', label: t('Both'), text: t('Charts first, then the tables.') },
]);
// 22 Nov 2026 is a Sunday: weekday 0.
const weekdays = computed(() => Array.from({ length: 7 }, (_, i) => ({
    value: i,
    label: new Intl.DateTimeFormat(locale.value === 'ar' ? 'ar-SA-u-ca-gregory-nu-latn' : 'en-GB', { weekday: 'long' }).format(new Date(2026, 10, 22 + i)),
})));

const allHotels = computed(() => form.hotels.length === hotels.value.length);
const toggleHotel = (id) => {
    form.hotels = form.hotels.includes(id) ? form.hotels.filter((x) => x !== id) : [...form.hotels, id];
};

function submit() {
    const opts = { preserveScroll: true, onSuccess: () => emit('close') };
    form.transform((d) => ({ ...d, weekday: d.frequency === 'weekly' ? d.weekday : null, month_day: d.frequency === 'monthly' ? d.month_day : null }));
    props.subscription ? form.put(`/subscriptions/${props.subscription.id}`, opts) : form.post('/subscriptions', opts);
}
</script>

<template>
    <Teleport to="body">
        <Transition enter-from-class="opacity-0" leave-to-class="opacity-0" enter-active-class="transition duration-200" leave-active-class="transition duration-150">
            <div v-if="open" class="fixed inset-0 z-50 flex items-end justify-center bg-navy/45 backdrop-blur-[2px] sm:items-center sm:p-6" @click.self="emit('close')" @keydown.esc="emit('close')">
                <form class="flex max-h-[94vh] w-full max-w-5xl flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl sm:rounded-3xl" role="dialog" aria-modal="true" :aria-label="t('Email this report')" @submit.prevent="submit">
                    <!-- Header -->
                    <div class="relative flex items-start gap-4 overflow-hidden bg-navy px-6 py-5 text-white">
                        <div class="brand-pattern pointer-events-none absolute inset-y-0 end-0 w-1/2 opacity-[0.08]" />
                        <span class="relative grid size-11 shrink-0 place-items-center rounded-2xl bg-coral text-white"><Icon name="mail" :size="20" /></span>
                        <div class="relative min-w-0 flex-1">
                            <h2 class="font-display text-xl font-semibold">{{ subscription ? t('Edit subscription') : t('Email this report') }}</h2>
                            <p class="mt-0.5 truncate text-sm text-white/70">{{ t('The group Manager Flash, delivered to :email', { email }) }}</p>
                        </div>
                        <button type="button" class="relative rounded-lg p-1.5 text-white/70 hover:bg-white/10 hover:text-white" :aria-label="t('Close')" @click="emit('close')"><Icon name="close" /></button>
                    </div>

                    <div class="grid min-h-0 flex-1 overflow-y-auto lg:grid-cols-[1fr_20rem]">
                        <div class="space-y-6 p-6">
                            <!-- How often -->
                            <fieldset>
                                <legend class="mb-2.5 text-xs font-bold tracking-wide text-ink-2 uppercase rtl:tracking-normal">{{ t('How often') }}</legend>
                                <div class="grid gap-2.5 sm:grid-cols-3">
                                    <label v-for="f in FREQ" :key="f.key" class="group relative cursor-pointer rounded-2xl border-2 p-3.5 transition"
                                           :class="form.frequency === f.key ? 'border-coral bg-coral-tint/60' : 'border-line hover:border-muted/60'">
                                        <input v-model="form.frequency" type="radio" :value="f.key" class="sr-only" />
                                        <span class="flex items-center gap-2 font-bold text-navy">
                                            <span class="grid size-8 place-items-center rounded-xl" :class="form.frequency === f.key ? 'bg-coral text-white' : 'bg-paper text-ink-2'"><Icon :name="f.icon" :size="16" /></span>
                                            {{ f.label }}
                                        </span>
                                        <span class="mt-2 block text-xs leading-relaxed text-ink-2">{{ f.text }}</span>
                                        <Icon v-if="form.frequency === f.key" name="check" :size="16" class="absolute end-3 top-3 text-coral" />
                                    </label>
                                </div>
                            </fieldset>

                            <!-- When -->
                            <fieldset>
                                <legend class="mb-2.5 text-xs font-bold tracking-wide text-ink-2 uppercase rtl:tracking-normal">{{ t('When') }}</legend>
                                <div v-if="form.frequency === 'weekly'" class="mb-3 flex flex-wrap gap-1.5">
                                    <button v-for="d in weekdays" :key="d.value" type="button" class="rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                                            :class="form.weekday === d.value ? 'border-navy bg-navy text-white' : 'border-line text-ink-2 hover:border-muted'"
                                            :aria-pressed="form.weekday === d.value" @click="form.weekday = d.value">{{ d.label }}</button>
                                </div>
                                <div class="flex flex-wrap items-center gap-3 text-sm text-ink-2">
                                    <template v-if="form.frequency === 'monthly'">
                                        <span>{{ t('On day') }}</span>
                                        <select v-model.number="form.month_day" class="field !w-auto !py-2 font-semibold" dir="ltr" :aria-label="t('Day of the month')">
                                            <option v-for="d in 28" :key="d" :value="d">{{ d }}</option>
                                        </select>
                                        <span>{{ t('of each month') }}</span>
                                    </template>
                                    <span>{{ t('at') }}</span>
                                    <input v-model="form.send_time" type="time" step="900" class="field !w-auto !py-2 font-semibold" dir="ltr" :aria-label="t('Send time')" />
                                    <span class="text-xs text-muted">{{ t('Saudi time') }}</span>
                                </div>
                                <p v-if="form.frequency === 'daily'" class="mt-2 text-xs text-muted">{{ t('If a hotel sends its night audit late, the email waits for it and goes out once per business date.') }}</p>
                                <p v-for="k in ['send_time', 'weekday', 'month_day']" v-show="form.errors[k]" :key="k" class="mt-1 text-xs font-semibold text-danger">{{ form.errors[k] }}</p>
                            </fieldset>

                            <!-- What -->
                            <fieldset>
                                <legend class="mb-2.5 text-xs font-bold tracking-wide text-ink-2 uppercase rtl:tracking-normal">{{ t('What you get') }}</legend>
                                <div class="grid gap-2.5 sm:grid-cols-3">
                                    <label v-for="c in CONTENT" :key="c.key" class="relative cursor-pointer rounded-2xl border-2 p-3.5 transition"
                                           :class="form.content === c.key ? 'border-navy bg-paper' : 'border-line hover:border-muted/60'">
                                        <input v-model="form.content" type="radio" :value="c.key" class="sr-only" />
                                        <span class="flex items-center gap-2 font-bold text-navy">
                                            <Icon :name="c.icon" :size="17" :class="form.content === c.key ? 'text-coral' : 'text-muted'" />{{ c.label }}
                                        </span>
                                        <span class="mt-1.5 block text-xs leading-relaxed text-ink-2">{{ c.text }}</span>
                                        <Icon v-if="form.content === c.key" name="check" :size="16" class="absolute end-3 top-3 text-navy" />
                                    </label>
                                </div>
                            </fieldset>

                            <!-- Hotels -->
                            <fieldset v-if="hotels.length > 1">
                                <legend class="mb-2.5 flex w-full items-center justify-between text-xs font-bold tracking-wide text-ink-2 uppercase rtl:tracking-normal">
                                    {{ t('Hotels') }}
                                    <button type="button" class="text-[11px] font-bold tracking-normal text-coral normal-case hover:underline"
                                            @click="form.hotels = allHotels ? [] : hotels.map((h) => h.id)">{{ allHotels ? t('Clear') : t('All my hotels') }}</button>
                                </legend>
                                <div class="flex flex-wrap gap-1.5">
                                    <button v-for="h in hotels" :key="h.id" type="button" class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                                            :class="form.hotels.includes(h.id) ? 'border-navy bg-navy text-white' : 'border-line text-ink-2 hover:border-muted'"
                                            :aria-pressed="form.hotels.includes(h.id)" @click="toggleHotel(h.id)">
                                        <Icon v-if="form.hotels.includes(h.id)" name="check" :size="12" />{{ name(h) }}
                                    </button>
                                </div>
                                <p class="mt-2 text-xs text-muted">{{ allHotels ? t('All your hotels, including hotels added later.') : t(':n of :total hotels', { n: form.hotels.length, total: hotels.length }) }}</p>
                                <p v-if="form.errors.hotels || form.errors['hotels.0']" class="mt-1 text-xs font-semibold text-danger">{{ form.errors.hotels || form.errors['hotels.0'] }}</p>
                            </fieldset>

                            <!-- Options -->
                            <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-paper p-4">
                                <label class="flex cursor-pointer items-center gap-3 text-sm font-semibold text-navy">
                                    <span class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition" :class="form.include_attention ? 'bg-coral' : 'bg-line'">
                                        <input v-model="form.include_attention" type="checkbox" class="peer sr-only" />
                                        <span class="absolute top-0.5 size-5 rounded-full bg-white shadow transition-all" :class="form.include_attention ? 'start-[1.375rem]' : 'start-0.5'" />
                                    </span>
                                    {{ t('Include what needs attention') }}
                                </label>
                                <div class="seg" role="radiogroup" :aria-label="t('Email language')">
                                    <button type="button" role="radio" :aria-checked="form.locale === 'ar'" :aria-pressed="form.locale === 'ar'" @click="form.locale = 'ar'">العربية</button>
                                    <button type="button" role="radio" :aria-checked="form.locale === 'en'" :aria-pressed="form.locale === 'en'" @click="form.locale = 'en'">English</button>
                                </div>
                            </div>
                        </div>

                        <!-- Live preview -->
                        <aside class="hidden border-s border-line bg-paper p-5 lg:block">
                            <div class="mb-3 text-xs font-bold tracking-wide text-ink-2 uppercase rtl:tracking-normal">{{ t('Preview') }}</div>
                            <EmailPreview :frequency="form.frequency" :content="form.content" :attention="form.include_attention" :hotel-count="form.hotels.length" />
                        </aside>
                    </div>

                    <!-- Footer -->
                    <div class="flex flex-wrap items-center justify-end gap-2 border-t border-line bg-white px-6 py-4">
                        <button type="button" class="rounded-xl px-4 py-2.5 text-sm font-bold text-ink-2 hover:bg-paper" @click="emit('close')">{{ t('Cancel') }}</button>
                        <button type="submit" class="btn-coral !py-2.5 text-sm" :disabled="form.processing || (hotels.length > 1 && !form.hotels.length)">
                            <Icon name="mail" :size="16" />{{ subscription ? t('Save') : t('Subscribe') }}
                        </button>
                    </div>
                </form>
            </div>
        </Transition>
    </Teleport>
</template>
