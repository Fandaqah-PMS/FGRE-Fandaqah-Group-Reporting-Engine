<?php

namespace App\Reports\Extraction\Pdf;

use Carbon\CarbonImmutable;
use Normalizer;

/**
 * Text helpers for reading PMS report PDFs: cleaning extracted text (Arabic presentation
 * forms, bidi marks, glyphs without a Unicode mapping), numbers, dates and label keys.
 */
final class Text
{
    /** A printed amount or count: 1,234.56 · -9,720.07 · (12.00) · 17.19 · 5.56% · 12.00- */
    public const NUMBER = '\(?-?\d[\d,]*(?:\.\d+)?\)?-?%?';

    /** dd.mm.yy or dd.mm.yyyy (also with / or -) */
    public const DATE = '\d{1,2}[.\/-]\d{1,2}[.\/-]\d{2,4}';

    /**
     * Clean one extracted line: drop bidi controls and unmapped glyphs (private use area),
     * turn Arabic presentation forms into normal letters (NFKC), unify dashes and digits,
     * and join a minus sign printed apart from its number ("-  9,720.07").
     */
    public static function clean(string $line): string
    {
        $line = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}\x{E000}-\x{F8FF}\x{FFFD}]/u', '', $line) ?? $line;
        $line = Normalizer::normalize($line, Normalizer::FORM_KC) ?: $line;
        $line = strtr($line, [
            "\u{2010}" => '-', "\u{2011}" => '-', "\u{2012}" => '-', "\u{2013}" => '-', "\u{2014}" => '-', "\u{2212}" => '-',
            "\u{00A0}" => ' ', "\u{066B}" => '.', "\u{066C}" => ',',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);

        // "-  9,720.07" → "-9,720.07" (not inside a date range such as "01.11.26 - 19.11.26")
        return preg_replace('/(?<![\d.])(?<=^|\s)-\s+(?=\d[\d,]*\.\d{2}(?!\.\d))/u', '-', rtrim($line)) ?? $line;
    }

    public static function isNumber(string $token): bool
    {
        return preg_match('/^'.self::NUMBER.'$/', trim($token)) === 1 && preg_match('/\d/', $token) === 1;
    }

    /** "1,234.56" → 1234.56, "(12.00)" / "12.00-" / "-12.00" → -12.0, "5.56%" → 5.56; null if not a number. */
    public static function number(?string $token): ?float
    {
        if ($token === null || ! self::isNumber($token)) {
            return null;
        }

        $t = trim($token);
        $negative = str_starts_with($t, '(') || str_starts_with($t, '-') || (str_ends_with($t, '-') && strlen($t) > 1);
        $digits = str_replace([',', '(', ')', '-', '%'], '', $t);

        return is_numeric($digits) ? ($negative ? -1 : 1) * (float) $digits : null;
    }

    /** "19.11.26" → "2026-11-19" (day.month.year, as Fandaqah and Opera print dates). */
    public static function date(?string $token): ?string
    {
        if ($token === null || ! preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{2}|\d{4})$/', trim($token), $m)) {
            return null;
        }

        $year = strlen($m[3]) === 2 ? 2000 + (int) $m[3] : (int) $m[3];

        return checkdate((int) $m[2], (int) $m[1], $year) ? CarbonImmutable::create($year, (int) $m[2], (int) $m[1])->toDateString() : null;
    }

    /**
     * Key for matching a printed row label against a dictionary: lower case, Arabic letters
     * unified (أ/إ/آ → ا, ة → ه, ى → ي, no diacritics), punctuation dropped. The letter ي is
     * removed entirely because PDF fonts often have no Unicode mapping for its final form,
     * so "إجمالي" can come out as "إجمال".
     */
    public static function key(string $label): string
    {
        $s = mb_strtolower(self::clean($label));
        $s = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $s) ?? $s;
        $s = strtr($s, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي']);
        $s = str_replace('ي', '', $s);
        $s = preg_replace('/[^\p{L}\p{N}%& ]+/u', ' ', $s) ?? $s;

        return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    }

    /**
     * Split a line into cells (text separated by two or more spaces), with their character
     * offsets, so numbers can be put under the right column header.
     *
     * @return list<array{text: string, start: int, end: int}>
     */
    public static function cells(string $line): array
    {
        preg_match_all('/\S+(?: \S+)*/u', $line, $m, PREG_OFFSET_CAPTURE);
        $cells = [];
        foreach ($m[0] as [$text, $byteOffset]) {
            $start = mb_strlen(substr($line, 0, $byteOffset));
            $cells[] = ['text' => $text, 'start' => $start, 'end' => $start + mb_strlen($text)];
        }

        return $cells;
    }

    /**
     * A line as label + amounts, whatever the direction (Arabic reports print the label on the
     * right and the amounts on the left). Amounts are returned in reading order of the
     * left-to-right version of the report.
     *
     * @return array{label: string, numbers: list<float>, rtl: bool}
     */
    public static function row(string $line): array
    {
        $cells = self::cells($line);
        $label = [];
        $numbers = [];
        foreach ($cells as $c) {
            if (self::isNumber($c['text'])) {
                $numbers[] = self::number($c['text']);
            } else {
                $label[] = $c['text'];
            }
        }

        // Mirrored Arabic line: amounts first and label last, or (no amounts) Arabic words in
        // separate cells, which pdftotext prints in visual left-to-right order.
        $rtl = $cells !== [] && (
            (self::isNumber($cells[0]['text']) && ! self::isNumber(end($cells)['text']))
            || ($numbers === [] && count($label) > 1 && preg_match('/\p{Arabic}/u', $line) && ! preg_match('/[A-Za-z]/', $line))
        );

        return [
            'label' => implode(' ', $rtl ? array_reverse($label) : $label),
            'numbers' => $rtl ? array_reverse($numbers) : $numbers,
            'rtl' => $rtl,
        ];
    }
}
