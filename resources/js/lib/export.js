/**
 * Export what is on screen. CSV opens in Excel (UTF-8 with BOM, so Arabic shows correctly);
 * PDF uses the browser's print dialog with a print layout (no sidebar, no buttons).
 * rows: list of arrays (first row = headings). Values are raw numbers where possible.
 */
export function downloadCsv(filename, rows) {
    const esc = (v) => {
        if (v === null || v === undefined) return '';
        const s = String(v);
        return /[",\n\r]/.test(s) ? `"${s.replaceAll('"', '""')}"` : s;
    };
    const csv = '﻿' + rows.map((r) => r.map(esc).join(',')).join('\r\n');
    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
    const a = Object.assign(document.createElement('a'), { href: url, download: filename });
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

export function printPage() {
    window.print();
}
