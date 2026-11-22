<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import SettingsShell from '../../Components/Settings/SettingsShell.vue';
import Modal from '../../Components/Modal.vue';
import Icon from '../../Components/Icon.vue';
import { useI18n } from '../../lib/i18n';

const props = defineProps({ members: Array, roles: Array, canAssignOwner: Boolean });
const { t, name, locale } = useI18n();
const page = usePage();
const hotels = computed(() => page.props.scope?.hotels ?? []);

const ROLE = {
    owner: { icon: 'shield', text: 'Everything, including users, roles and settings.', cls: 'bg-navy text-white' },
    finance: { icon: 'scale', text: 'Reports and financials, budgets, review queue.', cls: 'bg-coral-tint text-coral-dark' },
    operations: { icon: 'flash', text: 'Reports, exports and uploads.', cls: 'bg-peach-tint text-peach-ink' },
    viewer: { icon: 'grid', text: 'Dashboard and reports, read only.', cls: 'bg-paper text-ink-2' },
};

const search = ref('');
const roleFilter = ref('all');
const shown = computed(() => props.members.filter((m) => (roleFilter.value === 'all' || m.role === roleFilter.value)
    && (!search.value || `${m.name} ${m.email}`.toLowerCase().includes(search.value.toLowerCase()))));
const count = (role) => props.members.filter((m) => m.role === role).length;

const fmt = (iso) => (iso ? new Intl.DateTimeFormat(locale.value === 'ar' ? 'ar-SA-u-ca-gregory-nu-latn' : 'en-GB', { dateStyle: 'medium' }).format(new Date(iso)) : null);
const initials = (n) => n.split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase();
const hotelNames = (m) => (m.all_properties ? t('All hotels') : hotels.value.filter((h) => m.hotels.includes(h.id)).map((h) => name(h)).join('، '));

// Add / edit dialog
const dialog = ref({ open: false, member: null });
const form = useForm({ name: '', email: '', role: 'viewer', all_properties: true, hotels: [], locale: locale.value });
function openDialog(member = null) {
    form.clearErrors();
    form.defaults(member
        ? { name: member.name, email: member.email, role: member.role, all_properties: member.all_properties, hotels: [...member.hotels], locale: locale.value }
        : { name: '', email: '', role: 'viewer', all_properties: true, hotels: [], locale: locale.value });
    form.reset();
    dialog.value = { open: true, member };
}
const toggleHotel = (id) => (form.hotels = form.hotels.includes(id) ? form.hotels.filter((x) => x !== id) : [...form.hotels, id]);
function submit() {
    const opts = { preserveScroll: true, onSuccess: () => (dialog.value.open = false) };
    dialog.value.member ? form.put(`/settings/users/${dialog.value.member.id}`, opts) : form.post('/settings/users', opts);
}
const roleLocked = (r) => r === 'owner' && !props.canAssignOwner;

const resend = (m) => router.post(`/settings/users/${m.id}/invite`, {}, { preserveScroll: true });
const remove = (m) => {
    if (window.confirm(t('Remove :name from this group? They will lose access to all its hotels.', { name: m.name }))) {
        router.delete(`/settings/users/${m.id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <SettingsShell :title="t('Users')" :subtitle="t('Who can open FGRE for this group, their role and their hotels.')">
        <template #actions>
            <button type="button" class="btn-coral !py-2.5 text-sm" @click="openDialog()"><Icon name="userPlus" :size="17" />{{ t('Add user') }}</button>
        </template>

        <p v-if="page.props.errors?.role" class="flex items-center gap-2 rounded-xl bg-coral-tint px-4 py-3 text-sm font-semibold text-coral-dark">
            <Icon name="alert" :size="16" />{{ page.props.errors.role }}
        </p>

        <!-- Role summary / filter -->
        <section class="grid grid-cols-2 gap-3 xl:grid-cols-4">
            <button v-for="r in roles" :key="r" type="button" class="card flex items-center gap-3 p-4 text-start transition hover:shadow-md"
                    :class="roleFilter === r && '!border-coral ring-2 ring-coral/15'" @click="roleFilter = roleFilter === r ? 'all' : r">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl" :class="ROLE[r]?.cls"><Icon :name="ROLE[r]?.icon ?? 'user'" :size="18" /></span>
                <span class="min-w-0">
                    <span class="block text-lg leading-tight font-semibold text-ink tabular">{{ count(r) }}</span>
                    <span class="block truncate text-xs font-semibold text-ink-2">{{ t(`role_${r}`) }}</span>
                </span>
            </button>
        </section>

        <section class="card overflow-hidden">
            <div class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-4">
                <div class="relative min-w-[14rem] flex-1">
                    <Icon name="search" :size="16" class="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-muted" />
                    <input v-model="search" type="search" class="field !py-2 !ps-9 text-sm" :placeholder="t('Search by name or email')" />
                </div>
                <span class="text-xs text-muted">{{ t(':n of :total people', { n: shown.length, total: members.length }) }}</span>
                <Link href="/settings/roles" class="text-xs font-bold text-coral hover:underline">{{ t('What can each role do?') }}</Link>
            </div>
            <div class="overflow-x-auto">
                <table class="tbl min-w-[50rem]">
                    <thead><tr><th>{{ t('Person') }}</th><th>{{ t('Role') }}</th><th>{{ t('Hotels') }}</th><th>{{ t('Last sign-in') }}</th><th class="w-0"></th></tr></thead>
                    <tbody>
                        <tr v-for="m in shown" :key="m.id">
                            <td>
                                <div class="flex items-center gap-3">
                                    <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-peach text-xs font-bold text-on-peach">{{ initials(m.name) }}</span>
                                    <div class="min-w-0">
                                        <div class="truncate font-bold text-ink">{{ m.name }} <span v-if="m.is_me" class="text-xs font-medium text-muted">({{ t('you') }})</span></div>
                                        <div class="truncate text-xs text-ink-2" dir="ltr">{{ m.email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="chip whitespace-nowrap" :class="ROLE[m.role]?.cls"><Icon :name="ROLE[m.role]?.icon ?? 'user'" :size="12" />{{ t(`role_${m.role}`) }}</span></td>
                            <td class="max-w-[16rem] text-sm text-ink-2"><span class="line-clamp-2">{{ hotelNames(m) }}</span></td>
                            <td class="text-sm whitespace-nowrap">
                                <bdi v-if="m.last_login_at" class="text-ink-2">{{ fmt(m.last_login_at) }}</bdi>
                                <span v-else class="chip bg-peach-tint !py-0.5 text-[11px] text-peach-ink"><Icon name="mail" :size="11" />{{ t('Invitation pending') }}</span>
                            </td>
                            <td>
                                <div v-if="!m.is_me" class="flex items-center justify-end gap-1">
                                    <button v-if="!m.last_login_at" type="button" class="rounded-lg px-2 py-1.5 text-xs font-bold whitespace-nowrap text-ink-2 hover:bg-paper hover:text-ink" @click="resend(m)">{{ t('Resend invitation') }}</button>
                                    <button type="button" class="rounded-lg p-1.5 text-ink-2 hover:bg-paper hover:text-ink" :aria-label="t('Edit')" :title="t('Edit')" @click="openDialog(m)"><Icon name="edit" :size="16" /></button>
                                    <button type="button" class="rounded-lg p-1.5 text-muted hover:bg-coral-tint hover:text-coral-dark" :aria-label="t('Remove')" :title="t('Remove')" @click="remove(m)"><Icon name="trash" :size="16" /></button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!shown.length"><td colspan="5" class="py-10 text-center text-sm text-muted">{{ t('Nobody matches.') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <Modal :open="dialog.open" :icon="dialog.member ? 'edit' : 'userPlus'" width="max-w-3xl"
               :title="dialog.member ? t('Edit access') : t('Add user')"
               :subtitle="dialog.member ? dialog.member.email : t('They receive an email to choose their password.')" @close="dialog.open = false">
            <form id="user-form" class="space-y-6" @submit.prevent="submit">
                <div v-if="!dialog.member" class="grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="mb-1.5 block text-[13px] font-bold text-ink">{{ t('Full name') }}</span>
                        <input v-model="form.name" class="field" required maxlength="120" />
                        <span v-if="form.errors.name" class="mt-1 block text-xs font-semibold text-danger">{{ form.errors.name }}</span>
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-[13px] font-bold text-ink">{{ t('Email') }}</span>
                        <input v-model="form.email" type="email" class="field" required dir="ltr" />
                        <span v-if="form.errors.email" class="mt-1 block text-xs font-semibold text-danger">{{ form.errors.email }}</span>
                    </label>
                </div>

                <fieldset>
                    <legend class="mb-2.5 text-xs font-bold tracking-wide text-ink-2 uppercase rtl:tracking-normal">{{ t('Role') }}</legend>
                    <div class="grid gap-2.5 sm:grid-cols-2">
                        <label v-for="r in roles" :key="r" class="relative flex cursor-pointer items-start gap-3 rounded-2xl border-2 p-3.5 transition"
                               :class="[form.role === r ? 'border-coral bg-coral-tint/50' : 'border-line hover:border-muted/60', roleLocked(r) && 'cursor-not-allowed opacity-50']">
                            <input v-model="form.role" type="radio" :value="r" class="sr-only" :disabled="roleLocked(r)" />
                            <span class="grid size-9 shrink-0 place-items-center rounded-xl" :class="ROLE[r]?.cls"><Icon :name="ROLE[r]?.icon ?? 'user'" :size="17" /></span>
                            <span>
                                <span class="block font-bold text-ink">{{ t(`role_${r}`) }}</span>
                                <span class="mt-0.5 block text-xs text-ink-2">{{ t(ROLE[r]?.text ?? '') }}</span>
                            </span>
                            <Icon v-if="form.role === r" name="check" :size="16" class="absolute end-3 top-3 text-coral" />
                        </label>
                    </div>
                    <p v-if="form.errors.role" class="mt-2 text-xs font-semibold text-danger">{{ form.errors.role }}</p>
                </fieldset>

                <fieldset>
                    <legend class="mb-2.5 text-xs font-bold tracking-wide text-ink-2 uppercase rtl:tracking-normal">{{ t('Hotel access') }}</legend>
                    <div class="seg mb-3 w-fit">
                        <button type="button" :aria-pressed="form.all_properties" @click="form.all_properties = true">{{ t('All hotels') }}</button>
                        <button type="button" :aria-pressed="!form.all_properties" @click="form.all_properties = false">{{ t('Selected hotels') }}</button>
                    </div>
                    <p v-if="form.all_properties" class="text-xs text-muted">{{ t('Includes hotels added later.') }}</p>
                    <div v-else class="flex flex-wrap gap-1.5">
                        <button v-for="h in hotels" :key="h.id" type="button" class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                                :class="form.hotels.includes(h.id) ? 'border-navy bg-navy text-white' : 'border-line text-ink-2 hover:border-muted'"
                                :aria-pressed="form.hotels.includes(h.id)" @click="toggleHotel(h.id)">
                            <Icon v-if="form.hotels.includes(h.id)" name="check" :size="12" />{{ name(h) }}
                        </button>
                    </div>
                    <p v-if="form.errors.hotels" class="mt-2 text-xs font-semibold text-danger">{{ form.errors.hotels }}</p>
                </fieldset>

                <div v-if="!dialog.member">
                    <span class="mb-1.5 block text-xs font-bold tracking-wide text-ink-2 uppercase rtl:tracking-normal">{{ t('Invitation language') }}</span>
                    <div class="seg w-fit">
                        <button type="button" :aria-pressed="form.locale === 'ar'" @click="form.locale = 'ar'">العربية</button>
                        <button type="button" :aria-pressed="form.locale === 'en'" @click="form.locale = 'en'">English</button>
                    </div>
                </div>
            </form>
            <template #footer>
                <button type="button" class="rounded-xl px-4 py-2.5 text-sm font-bold text-ink-2 hover:bg-paper" @click="dialog.open = false">{{ t('Cancel') }}</button>
                <button type="submit" form="user-form" class="btn-coral !py-2.5 text-sm" :disabled="form.processing || (!form.all_properties && !form.hotels.length)">
                    <Icon :name="dialog.member ? 'check' : 'mail'" :size="16" />{{ dialog.member ? t('Save') : t('Send invitation') }}
                </button>
            </template>
        </Modal>
    </SettingsShell>
</template>
