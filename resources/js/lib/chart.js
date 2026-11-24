import { isNum } from './format';

/** Clean ticks from 0 to a "nice" maximum. */
export function niceTicks(max, count = 4) {
    const raw = max > 0 ? max : 1;
    const step0 = raw / count;
    const mag = 10 ** Math.floor(Math.log10(step0));
    const step = [1, 2, 2.5, 5, 10].map((m) => m * mag).find((s) => s >= step0);
    return Array.from({ length: count + 1 }, (_, i) => +(i * step).toFixed(6));
}

/**
 * Smooth path through points (monotone cubic, so the curve never overshoots the data).
 * pts: [[x, y], ...]. Returns an SVG path string.
 */
export function smoothPath(pts) {
    const n = pts.length;
    if (n === 0) return '';
    if (n === 1) return `M${pts[0][0]},${pts[0][1]}`;
    const dx = [], dy = [], m = [];
    for (let i = 0; i < n - 1; i++) {
        dx.push(pts[i + 1][0] - pts[i][0]);
        dy.push(pts[i + 1][1] - pts[i][1]);
        m.push(dy[i] / dx[i]);
    }
    const t = [m[0]];
    for (let i = 1; i < n - 1; i++) t.push(m[i - 1] * m[i] <= 0 ? 0 : (m[i - 1] + m[i]) / 2);
    t.push(m[n - 2]);
    for (let i = 0; i < n - 1; i++) {
        if (m[i] === 0) { t[i] = 0; t[i + 1] = 0; continue; }
        const a = t[i] / m[i], b = t[i + 1] / m[i], h = a * a + b * b;
        if (h > 9) { const s = 3 / Math.sqrt(h); t[i] = s * a * m[i]; t[i + 1] = s * b * m[i]; }
    }
    let d = `M${pts[0][0].toFixed(1)},${pts[0][1].toFixed(1)}`;
    for (let i = 0; i < n - 1; i++) {
        const c1x = pts[i][0] + dx[i] / 3, c1y = pts[i][1] + (t[i] * dx[i]) / 3;
        const c2x = pts[i + 1][0] - dx[i] / 3, c2y = pts[i + 1][1] - (t[i + 1] * dx[i]) / 3;
        d += `C${c1x.toFixed(1)},${c1y.toFixed(1)} ${c2x.toFixed(1)},${c2y.toFixed(1)} ${pts[i + 1][0].toFixed(1)},${pts[i + 1][1].toFixed(1)}`;
    }
    return d;
}

/** Split a series into runs of consecutive numeric values (gaps where data is missing). */
export function runs(values) {
    const out = [];
    let cur = [];
    values.forEach((v, i) => {
        if (isNum(v)) cur.push(i);
        else if (cur.length) { out.push(cur); cur = []; }
    });
    if (cur.length) out.push(cur);
    return out;
}
