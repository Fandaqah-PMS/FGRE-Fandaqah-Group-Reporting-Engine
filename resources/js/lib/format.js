// Numbers always use Western digits, as Fandaqah reports do in both languages.
const nf = (digits) => new Intl.NumberFormat('en-US', { minimumFractionDigits: digits, maximumFractionDigits: digits });

export const isNum = (v) => v !== null && v !== undefined && v !== '' && !Number.isNaN(Number(v));

export function money(v, digits = 2) {
    return isNum(v) ? nf(digits).format(Number(v)) : '–';
}

export function int(v) {
    return isNum(v) ? nf(0).format(Number(v)) : '–';
}

export function pct(v) {
    return isNum(v) ? `${nf(1).format(Number(v))}%` : '–';
}

/** 1,284 / 12.9K / 4.2M */
export function compact(v) {
    if (!isNum(v)) return '–';
    const n = Number(v);
    const abs = Math.abs(n);
    if (abs >= 1e6) return `${nf(abs >= 1e7 ? 1 : 2).format(n / 1e6)}M`;
    if (abs >= 1e4) return `${nf(1).format(n / 1e3)}K`;
    return nf(0).format(n);
}

/**
 * Change vs last year. kind = 'pp' (percentage points, for occupancy) or 'pct' (relative).
 * Returns null when either side is missing.
 */
export function delta(current, previous, kind = 'pct') {
    if (!isNum(current) || !isNum(previous)) return null;
    const c = Number(current);
    const p = Number(previous);
    if (kind === 'pp') return { value: c - p, label: `${c - p >= 0 ? '+' : '−'}${nf(1).format(Math.abs(c - p))} pp` };
    if (p === 0) return null;
    const r = ((c - p) / Math.abs(p)) * 100;
    return { value: r, label: `${r >= 0 ? '+' : '−'}${nf(1).format(Math.abs(r))}%` };
}

export function shortDate(iso, locale = 'en') {
    if (!iso) return '–';
    const d = new Date(`${iso}T00:00:00`);
    return new Intl.DateTimeFormat(locale === 'ar' ? 'ar-SA-u-ca-gregory-nu-latn' : 'en-GB', { day: 'numeric', month: 'short' }).format(d);
}

export function longDate(iso, locale = 'en') {
    if (!iso) return '–';
    const d = new Date(`${iso}T00:00:00`);
    return new Intl.DateTimeFormat(locale === 'ar' ? 'ar-SA-u-ca-gregory-nu-latn' : 'en-GB', {
        weekday: 'short', day: 'numeric', month: 'long', year: 'numeric',
    }).format(d);
}
