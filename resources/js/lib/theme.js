import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { applyChartTheme } from './palette';

/**
 * Light / Dark / System theme. The page template already sets <html data-theme> before the
 * app loads (no flash); this keeps it in step, follows the OS when the choice is "system",
 * and saves the choice: per user on the server when signed in, in the browser otherwise.
 */
const KEY = 'fgre.theme';
const media = typeof window !== 'undefined' ? window.matchMedia('(prefers-color-scheme: dark)') : null;

export const THEMES = ['light', 'dark', 'system'];
export const themePref = ref('system');
export const resolvedTheme = ref('light');

function resolve(pref) {
    return pref === 'dark' || (pref === 'system' && media?.matches) ? 'dark' : 'light';
}

function apply(pref) {
    const theme = resolve(pref);
    resolvedTheme.value = theme;
    const el = document.documentElement;
    el.dataset.theme = theme;
    el.style.colorScheme = theme;
    applyChartTheme(theme === 'dark');
}

media?.addEventListener('change', () => themePref.value === 'system' && apply('system'));

/** Called once at start-up with the signed-in user's saved choice (or none). */
export function initTheme(serverPref) {
    let pref = serverPref;
    if (!THEMES.includes(pref)) {
        try {
            pref = localStorage.getItem(KEY);
        } catch {
            pref = null;
        }
    }
    themePref.value = THEMES.includes(pref) ? pref : 'system';
    apply(themePref.value);
}

/** Change the theme now; `save` stores it on the user (signed-in pages). */
export function setTheme(pref, save = false) {
    if (!THEMES.includes(pref)) return;
    themePref.value = pref;
    apply(pref);
    try {
        localStorage.setItem(KEY, pref);
    } catch {
        // storage unavailable: the choice still applies for this visit
    }
    if (save) {
        router.post('/preferences/theme', { theme: pref }, { preserveState: true, preserveScroll: true });
    }
}
