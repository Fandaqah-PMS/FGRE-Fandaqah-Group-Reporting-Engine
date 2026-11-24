import { computed, watchEffect } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * <html lang/dir> is rendered once by the server; keep it in step when the
 * language changes without a full page load (Arabic = right to left).
 * Call once from each layout.
 */
export function useDocumentDirection() {
    const page = usePage();
    watchEffect(() => {
        document.documentElement.lang = page.props.locale ?? 'ar';
        document.documentElement.dir = page.props.dir ?? 'rtl';
    });
}

/** t('Occupancy') → Arabic when the locale is ar (lang/ar.json), else the key itself. */
export function useI18n() {
    const page = usePage();
    const dict = computed(() => page.props.translations ?? {});
    const locale = computed(() => page.props.locale ?? 'ar');

    const t = (key, replace = {}) => {
        let text = dict.value[key] ?? key;
        for (const [k, v] of Object.entries(replace)) text = text.replaceAll(`:${k}`, v);
        return text;
    };

    /** Pick the Arabic or English name of a hotel / group. */
    const name = (item) => (item ? (locale.value === 'ar' && item.name_ar ? item.name_ar : item.name) : '');

    return { t, name, locale, isRtl: computed(() => locale.value === 'ar') };
}
