<script setup>
import { useForm } from '@inertiajs/vue3';
import SettingsShell from '../../Components/Settings/SettingsShell.vue';
import Icon from '../../Components/Icon.vue';
import { useI18n } from '../../lib/i18n';

const props = defineProps({ group: Object, timezones: Array });
const { t } = useI18n();
const form = useForm({ ...props.group, name_ar: props.group.name_ar ?? '' });
const save = () => form.put('/settings/group', { preserveScroll: true });
</script>

<template>
    <SettingsShell :title="t('Group')" :subtitle="t('How your group appears in FGRE and in emails.')">
        <form class="card rise p-6" @submit.prevent="save">
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="mb-1.5 block text-[13px] font-bold text-navy">{{ t('Group name (English)') }}</span>
                    <input v-model="form.name" class="field" required maxlength="150" dir="ltr" />
                    <span v-if="form.errors.name" class="mt-1 block text-xs font-semibold text-danger">{{ form.errors.name }}</span>
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-[13px] font-bold text-navy">{{ t('Group name (Arabic)') }}</span>
                    <input v-model="form.name_ar" class="field" maxlength="150" dir="rtl" />
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-[13px] font-bold text-navy">{{ t('Reporting currency') }}</span>
                    <input v-model="form.currency" class="field uppercase" required maxlength="3" dir="ltr" />
                    <span class="mt-1 block text-xs" :class="form.errors.currency ? 'font-semibold text-danger' : 'text-muted'">{{ form.errors.currency || t('ISO code, e.g. SAR.') }}</span>
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-[13px] font-bold text-navy">{{ t('Timezone') }}</span>
                    <select v-model="form.timezone" class="field" dir="ltr">
                        <option v-for="z in timezones" :key="z" :value="z">{{ z }}</option>
                    </select>
                    <span class="mt-1 block text-xs text-muted">{{ t('Email subscriptions are sent in this timezone.') }}</span>
                </label>
            </div>
            <div class="mt-6 flex items-center justify-between gap-3 border-t border-line pt-5">
                <span class="flex items-center gap-2 text-xs text-muted"><Icon name="history" :size="14" />{{ t('Changes are recorded in the activity log.') }}</span>
                <button type="submit" class="btn-coral !py-2.5 text-sm" :disabled="form.processing || !form.isDirty">{{ t('Save') }}</button>
            </div>
        </form>
    </SettingsShell>
</template>
