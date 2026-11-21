import { int, isNum, money, pct } from './format';

/**
 * Report definitions: every report is a list of sections and rows. Each row says
 *   code / label   short heading and full name (USALI terms)
 *   key            field in the data
 *   f              formatter
 *   better         'high' | 'low' | null  (for best-in-group, shading and change colours)
 *   agg            'sum' (add hotels up; group average = total ÷ hotels) | 'ratio' (recomputed
 *                  from summed parts, never averaged) | 'balance' (shown per hotel only)
 *   kind           'pp' for percentages (change in points) | 'pct'
 * The three views (Group total, Side by side, Hotel vs group), comparisons and exports
 * all read these, so a new report only needs a new definition.
 */
const money0 = (v) => money(v, 0);
const money2 = (v) => money(v);
const pct1 = (v) => pct(v);

export function managerFlashSections(t) {
    return [
        { key: 'rooms', title: t('Rooms statistics'), rows: [
            { code: 'RA', key: 'total_rooms', name: t('Rooms available'), label: t('Rooms available (USALI: out-of-order rooms included)'), f: int, agg: 'sum' },
            { code: 'RS', key: 'paid_occupied', name: t('Rooms sold'), label: t('Rooms sold (occupied − complimentary − house use)'), f: int, agg: 'sum', better: 'high' },
            { code: 'RO', key: 'rooms_occupied', label: t('Rooms occupied'), f: int, agg: 'sum' },
            { code: 'COMP', key: 'comp_rooms', label: t('Complimentary rooms'), f: int, agg: 'sum' },
            { code: 'HU', key: 'house_use_rooms', label: t('House use rooms'), f: int, agg: 'sum' },
            { code: 'OOO', key: 'ooo_rooms', label: t('Out of order rooms'), f: int, agg: 'sum', better: 'low' },
            { code: 'OOS', key: 'oos_rooms', label: t('Out of service rooms'), f: int, agg: 'sum', better: 'low' },
            { code: 'DU', key: 'day_use_rooms', name: t('Day use rooms'), label: t('Day use rooms (not room nights)'), f: int, agg: 'sum' },
        ] },
        { key: 'kpi', title: t('Key performance (USALI)'), rows: [
            { code: 'OCC%', key: 'occupancy_pct', name: t('Occupancy'), label: t('Occupancy: rooms sold ÷ rooms available'), f: pct1, agg: 'ratio', better: 'high', kind: 'pp' },
            { code: 'ADR', key: 'adr', name: t('ADR'), label: t('Average daily rate: room revenue ÷ rooms sold'), f: money2, agg: 'ratio', better: 'high' },
            { code: 'RevPAR', key: 'revpar', name: t('RevPAR'), label: t('Revenue per available room: room revenue ÷ rooms available'), f: money2, agg: 'ratio', better: 'high' },
            { code: 'TRevPAR', key: 'trevpar', name: t('TRevPAR'), label: t('Total revenue per available room'), f: money2, agg: 'ratio', better: 'high' },
            { code: 'OCC-X', key: 'occupancy_excl_ooo_pct', name: t('Occupancy excl. out of order'), label: t('Occupancy excluding out-of-order rooms'), f: pct1, agg: 'ratio', better: 'high', kind: 'pp' },
        ] },
        { key: 'revenue', title: t('Revenue'), rows: [
            { code: 'RRV', key: 'room_revenue', label: t('Rooms revenue'), f: money0, agg: 'sum', better: 'high' },
            { code: 'FBR', key: 'fnb_revenue', label: t('Food and beverage revenue'), f: money0, agg: 'sum', better: 'high' },
            { code: 'OOD', key: 'other_revenue', name: t('Other revenue'), label: t('Other operated departments and miscellaneous income'), f: money0, agg: 'sum', better: 'high' },
            { code: 'TRV', key: 'total_revenue', label: t('Total operating revenue'), f: money0, agg: 'sum', better: 'high' },
        ] },
        { key: 'guests', title: t('Guests and activity'), rows: [
            { code: 'IH', key: 'persons_in_house', label: t('Guests in house'), f: int, agg: 'sum', better: 'high' },
            { code: 'ARR', key: 'arrival_rooms', label: t('Arrival rooms'), f: int, agg: 'sum' },
            { code: 'DEP', key: 'departure_rooms', label: t('Departure rooms'), f: int, agg: 'sum' },
            { code: 'NS', key: 'no_show_rooms', label: t('No-show rooms'), f: int, agg: 'sum', better: 'low' },
            { code: 'CXL', key: 'cancellations', label: t('Cancellations'), f: int, agg: 'sum', better: 'low' },
        ] },
    ];
}

/** Change from base to value: points for percentages, % otherwise. */
export function change(row, value, base) {
    if (!isNum(value) || !isNum(base)) return null;
    const v = Number(value), b = Number(base);
    if (row.kind === 'pp') {
        const d = v - b;
        return { d, label: `${d >= 0 ? '+' : '−'}${Math.abs(d).toFixed(1)} pp` };
    }
    if (b === 0) return null;
    const d = ((v - b) / Math.abs(b)) * 100;
    return { d, label: `${d >= 0 ? '+' : '−'}${Math.abs(d).toFixed(1)}%` };
}

/** 'good' | 'bad' | null for a change on a row. */
export function tone(row, d) {
    if (d === null || d === 0 || !row.better) return null;
    return (d > 0) === (row.better === 'high') ? 'good' : 'bad';
}
