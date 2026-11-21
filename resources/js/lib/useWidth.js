import { onBeforeUnmount, onMounted, ref } from 'vue';

/** Width of an element, kept up to date (charts draw in real pixels so text never stretches). */
export function useWidth(min = 260, initial = 640) {
    const el = ref(null);
    const width = ref(initial);
    let observer;
    onMounted(() => {
        observer = new ResizeObserver(([entry]) => (width.value = Math.max(min, Math.floor(entry.contentRect.width))));
        if (el.value) observer.observe(el.value);
    });
    onBeforeUnmount(() => observer?.disconnect());
    return { el, width };
}
