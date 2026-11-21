import { ref, watch } from 'vue';

// Charts / tables switch, shared by every report page and remembered per browser.
const KEY = 'fgre.view';
let initial = 'charts';
try {
    initial = localStorage.getItem(KEY) === 'tables' ? 'tables' : 'charts';
} catch {
    // storage unavailable (private mode): default to charts
}

const view = ref(initial);
watch(view, (v) => {
    try {
        localStorage.setItem(KEY, v);
    } catch {
        // ignore
    }
});

export function useViewMode() {
    return view;
}
