<script setup>
import { reactive, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import SettingsShell from '../../Components/Settings/SettingsShell.vue';
import Modal from '../../Components/Modal.vue';
import Icon from '../../Components/Icon.vue';
import { useI18n } from '../../lib/i18n';

const props = defineProps({ hotels: Array, providers: Array, regions: Array });
const { t, name } = useI18n();

const dialog = ref({ open: false, hotel: null });
const blank = { name: '', name_ar: '', code: '', pms_provider_id: props.providers[0]?.id ?? null, total_rooms: null, region: '', timezone: 'Asia/Riyadh', allowed_sender_emails: [], aliases: [], is_active: true };
const form = useForm({ ...blank });
const inputs = reactive({ sender: '', alias: '' });

function open(hotel = null) {
    form.clearErrors();
    form.defaults(hotel ? { ...blank, ...hotel, allowed_sender_emails: [...hotel.allowed_sender_emails], aliases: [...hotel.aliases], region: hotel.region ?? '' } : { ...blank });
    form.reset();
    inputs.sender = inputs.alias = '';
    dialog.value = { open: true, hotel };
}
const addTo = (key, field) => {
    const v = inputs[field].trim();
    if (v && !form[key].includes(v)) form[key] = [...form[key], v];
    inputs[field] = '';
};
const removeFrom = (key, v) => (form[key] = form[key].filter((x) => x !== v));
function submit() {
    addTo('allowed_sender_emails', 'sender');
    addTo('aliases', 'alias');
    const opts = { preserveScroll: true, onSuccess: () => (dialog.value.open = false) };
    dialog.value.hotel ? form.put(`/settings/hotels/${dialog.value.hotel.id}`, opts) : form.post('/settings/hotels', opts);
}
const errorFor = (prefix) => Object.entries(form.errors).find(([k]) => k === prefix || k.startsWith(`${prefix}.`))?.[1];
</script>

<template>
    <SettingsShell :title="t('Hotels')" :subtitle="t('Rooms feed occupancy and RevPAR; sender addresses decide which emailed reports are accepted.')">
        <template #actions>
            <button type="button" class="btn-coral !py-2.5 text-sm" @click="open()"><Icon name="plus" :size="17" />{{ t('Add hotel') }}</button>
        </template>

        <section class="grid gap-4 xl:grid-cols-2">
            <article v-for="h in hotels" :key="h.id" class="card rise flex flex-col p-5" :class="!h.is_active && 'opacity-70'">
                <div class="flex items-start gap-4">
                    <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-navy text-white"><Icon name="building" :size="21" /></span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="truncate font-display text-lg font-semibold text-navy">{{ name(h) }}</h3>
                            <span v-if="!h.is_active" class="chip bg-neutral !py-0.5 text-[11px] text-ink-2">{{ t('Inactive') }}</span>
                        </div>
                        <div class="text-xs text-muted">{{ h.name_ar && name(h) !== h.name ? h.name : h.name_ar }}</div>
                    </div>
                    <button type="button" class="rounded-lg p-2 text-ink-2 hover:bg-paper hover:text-navy" :aria-label="t('Edit')" @click="open(h)"><Icon name="edit" :size="17" /></button>
                </div>
                <dl class="mt-4 grid grid-cols-2 gap-3 rounded-2xl bg-paper p-4 text-sm sm:grid-cols-4">
                    <div><dt class="text-[11px] font-bold text-muted">{{ t('Code') }}</dt><dd class="mt-0.5 font-semibold text-navy tabular">{{ h.code }}</dd></div>
                    <div><dt class="text-[11px] font-bold text-muted">{{ t('PMS') }}</dt><dd class="mt-0.5 font-semibold text-navy">{{ h.pms }}</dd></div>
                    <div><dt class="text-[11px] font-bold text-muted">{{ t('Rooms') }}</dt><dd class="mt-0.5 font-semibold text-navy tabular">{{ h.total_rooms ?? '–' }}</dd></div>
                    <div><dt class="text-[11px] font-bold text-muted">{{ t('Region') }}</dt><dd class="mt-0.5 font-semibold text-navy">{{ h.region ? t(h.region) : '–' }}</dd></div>
                </dl>
                <div class="mt-4">
                    <div class="mb-1.5 flex items-center gap-1.5 text-[11px] font-bold text-muted"><Icon name="mail" :size="13" />{{ t('Reports accepted from') }}</div>
                    <div class="flex flex-wrap gap-1.5">
                        <span v-for="e in h.allowed_sender_emails" :key="e" class="chip bg-white !py-0.5 text-[11px] text-navy ring-1 ring-line" dir="ltr">{{ e }}</span>
                        <span v-if="!h.allowed_sender_emails.length" class="chip bg-coral-tint !py-0.5 text-[11px] text-coral-dark"><Icon name="alert" :size="11" />{{ t('No sender yet: emailed reports are not accepted') }}</span>
                    </div>
                </div>
                <div class="mt-auto pt-4 text-xs text-muted">
                    <template v-if="h.last_report_at">{{ t('Last report received') }} <bdi class="tabular">{{ h.last_report_at.replace('T', ' ') }}</bdi></template>
                    <template v-else>{{ t('No report received yet') }}</template>
                </div>
            </article>
        </section>

        <Modal :open="dialog.open" icon="building" width="max-w-3xl" :title="dialog.hotel ? t('Edit hotel') : t('Add hotel')"
               :subtitle="dialog.hotel ? name(dialog.hotel) : t('Names exactly as the PMS prints them, so reports are matched.')" @close="dialog.open = false">
            <form id="hotel-form" class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                <label class="block">
                    <span class="mb-1.5 block text-[13px] font-bold text-navy">{{ t('Name (English)') }}</span>
                    <input v-model="form.name" class="field" required maxlength="150" dir="ltr" />
                    <span v-if="form.errors.name" class="mt-1 block text-xs font-semibold text-danger">{{ form.errors.name }}</span>
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-[13px] font-bold text-navy">{{ t('Name (Arabic)') }}</span>
                    <input v-model="form.name_ar" class="field" maxlength="150" dir="rtl" />
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-[13px] font-bold text-navy">{{ t('Code') }}</span>
                    <input v-model="form.code" class="field uppercase" required maxlength="50" dir="ltr" placeholder="RUH-01" />
                    <span v-if="form.errors.code" class="mt-1 block text-xs font-semibold text-danger">{{ form.errors.code }}</span>
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-[13px] font-bold text-navy">{{ t('PMS') }}</span>
                    <select v-model.number="form.pms_provider_id" class="field" required>
                        <option v-for="p in providers" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-[13px] font-bold text-navy">{{ t('Total rooms') }}</span>
                    <input v-model.number="form.total_rooms" type="number" min="1" max="10000" class="field" required dir="ltr" />
                    <span class="mt-1 block text-xs text-muted">{{ form.errors.total_rooms || t('Rooms available per night, out of order included (USALI).') }}</span>
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-[13px] font-bold text-navy">{{ t('Region') }}</span>
                    <input v-model="form.region" class="field" list="regions" maxlength="60" />
                    <datalist id="regions"><option v-for="r in regions" :key="r" :value="r" /></datalist>
                    <span class="mt-1 block text-xs text-muted">{{ t('Groups hotels in the side-by-side reports.') }}</span>
                </label>

                <div class="sm:col-span-2">
                    <span class="mb-1.5 block text-[13px] font-bold text-navy">{{ t('Reports accepted from') }}</span>
                    <div class="flex flex-wrap gap-1.5 rounded-xl border border-line p-2">
                        <span v-for="e in form.allowed_sender_emails" :key="e" class="chip bg-paper text-navy" dir="ltr">{{ e }}
                            <button type="button" class="text-muted hover:text-coral" :aria-label="t('Remove')" @click="removeFrom('allowed_sender_emails', e)"><Icon name="close" :size="12" /></button>
                        </span>
                        <input v-model="inputs.sender" type="email" dir="ltr" class="min-w-[14rem] flex-1 bg-transparent px-1 py-1 text-sm outline-none" :placeholder="t('reports@pms.example, then Enter')"
                               @keydown.enter.prevent="addTo('allowed_sender_emails', 'sender')" @blur="addTo('allowed_sender_emails', 'sender')" />
                    </div>
                    <span class="mt-1 block text-xs" :class="errorFor('allowed_sender_emails') ? 'font-semibold text-danger' : 'text-muted'">
                        {{ errorFor('allowed_sender_emails') || t('Emails from other addresses are not processed for this hotel.') }}
                    </span>
                </div>
                <div class="sm:col-span-2">
                    <span class="mb-1.5 block text-[13px] font-bold text-navy">{{ t('Other names on reports') }}</span>
                    <div class="flex flex-wrap gap-1.5 rounded-xl border border-line p-2">
                        <span v-for="a in form.aliases" :key="a" class="chip bg-paper text-navy">{{ a }}
                            <button type="button" class="text-muted hover:text-coral" :aria-label="t('Remove')" @click="removeFrom('aliases', a)"><Icon name="close" :size="12" /></button>
                        </span>
                        <input v-model="inputs.alias" class="min-w-[14rem] flex-1 bg-transparent px-1 py-1 text-sm outline-none" :placeholder="t('Another name, then Enter')"
                               @keydown.enter.prevent="addTo('aliases', 'alias')" @blur="addTo('aliases', 'alias')" />
                    </div>
                    <span class="mt-1 block text-xs text-muted">{{ t('For example the legal name or an old name the PMS still prints.') }}</span>
                </div>
                <label v-if="dialog.hotel" class="flex items-center gap-3 text-sm font-semibold text-navy sm:col-span-2">
                    <input v-model="form.is_active" type="checkbox" class="size-4 accent-coral" />
                    {{ t('Active (inactive hotels keep their history but stop accepting reports)') }}
                </label>
            </form>
            <template #footer>
                <button type="button" class="rounded-xl px-4 py-2.5 text-sm font-bold text-ink-2 hover:bg-paper" @click="dialog.open = false">{{ t('Cancel') }}</button>
                <button type="submit" form="hotel-form" class="btn-coral !py-2.5 text-sm" :disabled="form.processing">{{ t('Save') }}</button>
            </template>
        </Modal>
    </SettingsShell>
</template>
