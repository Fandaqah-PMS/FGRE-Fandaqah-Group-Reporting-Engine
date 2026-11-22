<script setup>
import { computed, ref } from 'vue';
import { tint } from '../../lib/palette';
import { isNum } from '../../lib/format';
import { useI18n } from '../../lib/i18n';

/**
 * Embossed ring, as on the Fandaqah PMS dashboard ("Status of units today"): a raised plate,
 * a thick ring of shaded segments with softly rounded ends and gaps, and a raised centre
 * disc with the headline value. Below it, one row per part (dot, name, value and share), so
 * nothing depends on colour or hover. segments: [{ key, label, value, color }]; ≤ 6 parts.
 */
const props = defineProps({
    segments: { type: Array, required: true },
    format: { type: Function, required: true },
    centerLabel: { type: String, default: '' },
    centerValue: { type: String, default: null },
    size: { type: Number, default: 250 },
});

const { t } = useI18n();
const active = ref(null);
const uid = Math.random().toString(36).slice(2, 8);

const R_OUT = 90, R_IN = 58, GAP = 0.045, CORNER = 5;
const total = computed(() => props.segments.reduce((s, x) => s + (isNum(x.value) ? Math.max(0, Number(x.value)) : 0), 0));

const pt = (r, a) => [100 + r * Math.cos(a), 100 + r * Math.sin(a)];
const f = (n) => n.toFixed(2);

/** Annular sector from angle a0 to a1 with rounded corners (radius cr). */
function sector(a0, a1, rIn, rOut, cr) {
    const span = a1 - a0;
    const c = Math.max(0.5, Math.min(cr, ((span * rIn) / 2) * 0.9, (rOut - rIn) / 2));
    const to = c / rOut, ti = c / rIn;
    const large = (a, b) => (b - a > Math.PI ? 1 : 0);
    const [o0, o1, i1, i0] = [pt(rOut, a0 + to), pt(rOut, a1 - to), pt(rIn, a1 - ti), pt(rIn, a0 + ti)];
    return [
        `M${f(o0[0])},${f(o0[1])}`,
        `A${rOut},${rOut} 0 ${large(a0 + to, a1 - to)} 1 ${f(o1[0])},${f(o1[1])}`,
        `Q${f(pt(rOut, a1)[0])},${f(pt(rOut, a1)[1])} ${f(pt(rOut - c, a1)[0])},${f(pt(rOut - c, a1)[1])}`,
        `L${f(pt(rIn + c, a1)[0])},${f(pt(rIn + c, a1)[1])}`,
        `Q${f(pt(rIn, a1)[0])},${f(pt(rIn, a1)[1])} ${f(i1[0])},${f(i1[1])}`,
        `A${rIn},${rIn} 0 ${large(a0 + ti, a1 - ti)} 0 ${f(i0[0])},${f(i0[1])}`,
        `Q${f(pt(rIn, a0)[0])},${f(pt(rIn, a0)[1])} ${f(pt(rIn + c, a0)[0])},${f(pt(rIn + c, a0)[1])}`,
        `L${f(pt(rOut - c, a0)[0])},${f(pt(rOut - c, a0)[1])}`,
        `Q${f(pt(rOut, a0)[0])},${f(pt(rOut, a0)[1])} ${f(o0[0])},${f(o0[1])}`,
        'Z',
    ].join(' ');
}

const arcs = computed(() => {
    const parts = props.segments.map((s, i) => ({ ...s, i, share: total.value > 0 ? Math.max(0, Number(s.value) || 0) / total.value : 0 }));
    const shown = parts.filter((p) => p.share > 0);
    const gap = shown.length > 1 ? GAP : 0;
    // Small parts get a minimum sweep so they stay visible (the legend keeps the exact share).
    const MIN = 0.13;
    const raw = shown.map((p) => Math.max(p.share * 2 * Math.PI, MIN));
    const scale = (2 * Math.PI) / raw.reduce((a, b) => a + b, 0);
    let a = -Math.PI / 2 + gap / 2;
    const paths = {};
    shown.forEach((p, k) => {
        const sweep = raw[k] * scale;
        paths[p.key] = { start: a, end: a + sweep - gap };
        a += sweep;
    });

    return parts.map((p) => ({
        ...p,
        d: paths[p.key] ? sector(paths[p.key].start, paths[p.key].end, R_IN, R_OUT, CORNER) : null,
        dActive: paths[p.key] ? sector(paths[p.key].start, paths[p.key].end, R_IN - 2, R_OUT + 4, CORNER) : null,
    }));
});
const activeArc = computed(() => arcs.value.find((a) => a.key === active.value));
/** A slightly darker shade of a hex colour (bottom-right of each segment). */
function shade(hex, amount = 0.14) {
    const n = parseInt(hex.slice(1), 16);
    return `rgb(${[(n >> 16) & 255, (n >> 8) & 255, n & 255].map((v) => Math.round(v * (1 - amount))).join(',')})`;
}
</script>

<template>
    <div class="flex flex-col items-center">
        <!-- raised plate -->
        <div class="relative shrink-0 rounded-full p-[4%]
                    bg-[radial-gradient(circle_at_35%_30%,#ffffff_0%,#f3f1ed_55%,#e7e4de_100%)]
                    shadow-[0_24px_44px_-20px_rgba(38,46,67,0.45),0_2px_6px_rgba(38,46,67,0.08),inset_0_2px_3px_rgba(255,255,255,0.9),inset_0_-3px_8px_rgba(38,46,67,0.06)]
                    dark:bg-[radial-gradient(circle_at_35%_30%,#252e44_0%,#1b2233_60%,#151b29_100%)]
                    dark:shadow-[0_24px_44px_-20px_rgba(0,0,0,0.75),inset_0_1px_2px_rgba(255,255,255,0.06),inset_0_-3px_8px_rgba(0,0,0,0.35)]"
             :style="{ width: `${size}px`, height: `${size}px` }">
            <svg viewBox="0 0 200 200" class="size-full overflow-visible" role="img" :aria-label="centerLabel">
                <defs>
                    <linearGradient v-for="a in arcs" :id="`dg-${uid}-${a.i}`" :key="a.key" gradientUnits="userSpaceOnUse" x1="25" y1="15" x2="175" y2="185">
                        <stop offset="0" :stop-color="tint(a.color, 0.32)" />
                        <stop offset="0.55" :stop-color="a.color" />
                        <stop offset="1" :stop-color="shade(a.color)" />
                    </linearGradient>
                    <filter :id="`ds-${uid}`" x="-20%" y="-20%" width="140%" height="140%">
                        <feDropShadow dx="0" dy="2" stdDeviation="1.6" flood-color="#262E43" flood-opacity="0.22" />
                    </filter>
                    <filter :id="`groove-${uid}`" x="-10%" y="-10%" width="120%" height="120%">
                        <feGaussianBlur in="SourceAlpha" stdDeviation="2.2" result="b" />
                        <feOffset dy="1.5" result="o" />
                        <feComposite in="o" in2="SourceAlpha" operator="out" result="inner" />
                        <feFlood flood-color="#262E43" flood-opacity="0.18" />
                        <feComposite in2="inner" operator="in" />
                        <feComposite in2="SourceGraphic" operator="over" />
                    </filter>
                </defs>
                <!-- the groove the segments sit in -->
                <path :d="sector(-Math.PI / 2 + 0.001, (3 * Math.PI) / 2 - 0.001, R_IN - 3, R_OUT + 3, 0.5)" class="fill-neutral" :filter="`url(#groove-${uid})`" />
                <path v-for="a in arcs.filter((x) => x.d)" :key="a.key" :d="active === a.key ? a.dActive : a.d" :fill="`url(#dg-${uid}-${a.i})`"
                      :filter="`url(#ds-${uid})`" class="cursor-pointer outline-none transition-[d] duration-200" tabindex="0"
                      @pointerenter="active = a.key" @pointerleave="active = null" @focus="active = a.key" @blur="active = null" />
            </svg>
            <!-- raised centre -->
            <div class="absolute inset-[31%] grid place-items-center rounded-full bg-surface text-center
                        shadow-[0_8px_18px_-8px_rgba(38,46,67,0.35),0_0_0_5px_rgba(237,235,230,0.7),inset_0_1px_2px_rgba(255,255,255,0.9)]
                        dark:shadow-[0_8px_18px_-8px_rgba(0,0,0,0.7),0_0_0_5px_rgba(34,42,59,0.9),inset_0_1px_1px_rgba(255,255,255,0.05)]">
                <div class="px-2">
                    <div class="text-[1.45rem] leading-none font-medium text-ink tabular" dir="ltr">
                        {{ activeArc ? format(activeArc.value) : (centerValue ?? format(total)) }}
                    </div>
                    <div class="mt-1.5 max-w-[6.5rem] truncate text-[11px] text-ink-2">{{ activeArc ? activeArc.label : centerLabel }}</div>
                </div>
            </div>
        </div>

        <ul class="mt-7 w-full space-y-1" :aria-label="t('Legend')">
            <li v-for="a in arcs" :key="a.key" class="flex items-center gap-3 rounded-lg px-1 py-2 text-[13.5px] transition" :class="active === a.key && 'bg-paper'"
                @pointerenter="active = a.key" @pointerleave="active = null">
                <span class="size-3 shrink-0 rounded-full shadow-[inset_0_-1px_1px_rgba(0,0,0,0.12)]" :style="{ background: a.color }" />
                <span class="min-w-0 flex-1 truncate text-ink-2">{{ a.label }}</span>
                <bdi class="font-bold text-ink tabular">{{ format(a.value) }}</bdi>
                <bdi class="w-11 text-end text-xs text-muted tabular">{{ (a.share * 100).toFixed(1) }}%</bdi>
            </li>
        </ul>
    </div>
</template>
