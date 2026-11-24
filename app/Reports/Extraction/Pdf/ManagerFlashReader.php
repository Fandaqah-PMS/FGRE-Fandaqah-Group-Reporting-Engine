<?php

namespace App\Reports\Extraction\Pdf;

use App\Reports\Extraction\ExtractionSchema;
use Carbon\CarbonImmutable;

/**
 * "Manager - Flash" (program manager_report, EOD-016), as printed by Opera and Fandaqah,
 * in English or Arabic (Arabic pages are mirrored: columns right to left, label last).
 *
 * Columns are found from the page header on every page: a line of years above a line of
 * DAY / MONTH / YEAR (six columns: this year and last year), or one month column for a
 * month report ("APRIL" / "2026", filter "Calendar/Month 2026, 04"). Each amount goes to
 * the header column it sits under. Rows are recognised by label, never by position.
 */
class ManagerFlashReader implements PdfReportReader
{
    public const TITLE = '/manager\s*-\s*flash|تقرير المدير السريع/iu';

    /** Printed label (English and Arabic, as Text::key) => canonical metric. */
    private const LABELS = [
        'total rooms in hotel' => 'total_rooms', 'إجمالي غرف الفندق' => 'total_rooms',
        'rooms occupied' => 'rooms_occupied', 'الغرف المشغولة' => 'rooms_occupied',
        'out of order rooms' => 'ooo_rooms', 'الغرف خارج الخدمة' => 'ooo_rooms',
        'out of service rooms' => 'oos_rooms', 'الغرف المعطلة مؤقتاً' => 'oos_rooms',
        'complimentary rooms' => 'comp_rooms', 'الغرف المجانية' => 'comp_rooms',
        'house use rooms' => 'house_use_rooms', 'غرف الاستخدام الداخلي' => 'house_use_rooms',
        'day use rooms' => 'day_use_rooms', 'غرف الاستخدام النهاري' => 'day_use_rooms',
        'room revenue' => 'room_revenue', 'rooms revenue' => 'room_revenue', 'إيراد الغرف' => 'room_revenue',
        'food and beverage revenue' => 'fnb_revenue', 'food & beverage revenue' => 'fnb_revenue', 'f&b revenue' => 'fnb_revenue', 'إيراد الأطعمة والمشروبات' => 'fnb_revenue',
        'other revenue' => 'other_revenue', 'إيرادات أخرى' => 'other_revenue',
        'total revenue' => 'total_revenue', 'إجمالي الإيراد' => 'total_revenue',
        'total in-house persons' => 'persons_in_house', 'إجمالي الأشخاص المقيمين' => 'persons_in_house',
        'arrival rooms' => 'arrival_rooms', 'غرف الوصول' => 'arrival_rooms',
        'departure rooms' => 'departure_rooms', 'غرف المغادرة' => 'departure_rooms',
        'no show rooms' => 'no_show_rooms', 'غرف عدم الحضور' => 'no_show_rooms',
        'cancelled reservations for today' => 'cancellations', 'الحجوزات الملغاة لليوم' => 'cancellations',
        '% rooms occupied' => 'occupancy_pct', '% الغرف المشغولة' => 'occupancy_pct',
        'adr' => 'adr', 'average daily rate' => 'adr', 'متوسط السعر اليومي' => 'adr',
        'revpar' => 'revpar',
    ];

    /** Revenue departments (category, printed name) taken from the revenue metrics. */
    private const DEPARTMENTS = [
        'room_revenue' => ['rooms', 'Rooms'],
        'fnb_revenue' => ['fnb', 'Food and Beverage'],
        'other_revenue' => ['other', 'Other'],
    ];

    private const MONTHS = [
        'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4, 'may' => 5, 'june' => 6, 'july' => 7,
        'august' => 8, 'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12,
        'يناير' => 1, 'فبراير' => 2, 'مارس' => 3, 'أبريل' => 4, 'ابريل' => 4, 'مايو' => 5, 'يونيو' => 6, 'يوليو' => 7,
        'أغسطس' => 8, 'اغسطس' => 8, 'سبتمبر' => 9, 'أكتوبر' => 10, 'اكتوبر' => 10, 'نوفمبر' => 11, 'ديسمبر' => 12,
    ];

    public function key(): string
    {
        return 'manager_flash';
    }

    public function matches(PdfText $text): bool
    {
        return $text->has(self::TITLE) || $text->has('/\bmanager_report\b/');
    }

    public function read(PdfText $text): array
    {
        $out = ReaderHeader::make($text, 'manager_flash', self::TITLE);
        $labels = [];
        foreach (self::LABELS as $label => $metric) {
            $labels[Text::key($label)] = $metric;
        }

        $values = [];      // metric => period => value
        $printed = [];     // metric => label as printed
        $single = false;

        foreach ($text->pages as $page) {
            [$columns, $headerAt, $isSingle] = $this->columns($page);
            if ($columns === []) {
                continue;
            }
            $single = $single || $isSingle;

            foreach (array_slice($page, $headerAt + 1) as $line) {
                if (preg_match('/^\s*(filter|التصف)/iu', $line)) {
                    break;
                }
                $cells = Text::cells($line);
                $numbers = array_values(array_filter($cells, fn ($c) => Text::isNumber($c['text'])));
                $words = array_values(array_filter($cells, fn ($c) => ! Text::isNumber($c['text'])));
                if ($numbers === [] || $words === []) {
                    continue;
                }

                $label = implode(' ', array_column($words, 'text'));
                $metric = $labels[Text::key($label)] ?? null;
                if ($metric === null || isset($printed[$metric])) {
                    continue;   // not a metric we keep, or a later row with the same label
                }

                $printed[$metric] = $label;
                foreach ($this->assign($numbers, $columns) as $period => $value) {
                    $values[$metric][$period] = $value;
                }
            }
        }

        $metrics = [];
        foreach ($printed as $metric => $label) {
            $metrics[] = ['metric' => $metric, 'label_as_printed' => $label, ...array_map(fn ($p) => $values[$metric][$p] ?? null, array_combine(ExtractionSchema::PERIODS, ExtractionSchema::PERIODS))];
        }

        $departments = [];
        foreach (self::DEPARTMENTS as $metric => [$category, $name]) {
            if (isset($values[$metric])) {
                $departments[] = ['department' => $name, 'category' => $category, ...array_map(fn ($p) => $values[$metric][$p] ?? null, array_combine(ExtractionSchema::PERIODS, ExtractionSchema::PERIODS))];
            }
        }

        $out['manager_flash'] = ['metrics' => $metrics, 'revenue_departments' => $departments];
        $this->dates($text, $out, $single);

        if ($metrics === []) {
            $out['issues'][] = 'No Manager Flash rows were recognised on this report.';
        }

        return $out;
    }

    /**
     * Column headers of a page: [list of [period, start, end], index of the header line, single-period].
     *
     * @param  list<string>  $page
     * @return array{0: list<array{period: string, start: int, end: int}>, 1: int, 2: bool}
     */
    private function columns(array $page): array
    {
        foreach ($page as $i => $line) {
            $cells = Text::cells($line);
            $kinds = array_values(array_filter($cells, fn ($c) => preg_match('/^(DAY|MONTH|YEAR|يوم|اليوم|شهر|الشهر|سنة|السنة)$/iu', $c['text'])));

            // Six-column flash: DAY / MONTH / YEAR under a line of years.
            if (count($kinds) >= 2 && count($kinds) === count($cells)) {
                $years = $this->yearCells($page, $i);
                $max = $years === [] ? null : max(array_column($years, 'year'));
                $columns = [];
                foreach ($kinds as $k) {
                    $year = $this->nearest($years, $k)['year'] ?? $max;
                    $base = match (true) {
                        (bool) preg_match('/^(DAY|يوم|اليوم)$/iu', $k['text']) => 'day',
                        (bool) preg_match('/^(MONTH|شهر|الشهر)$/iu', $k['text']) => 'mtd',
                        default => 'ytd',
                    };
                    $columns[] = ['period' => $year !== null && $year < $max ? "{$base}_ly" : $base, 'start' => $k['start'], 'end' => $k['end']];
                }

                return [$columns, $i, false];
            }

            // Month report: one column headed by the month name.
            if (count($cells) === 1 && isset(self::MONTHS[mb_strtolower($cells[0]['text'])])) {
                return [[['period' => 'mtd', 'start' => $cells[0]['start'], 'end' => $cells[0]['end'] + 6]], $i + ($this->isYearLine($page[$i + 1] ?? '') ? 1 : 0), true];
            }
        }

        return [[], 0, false];
    }

    /** @return list<array{year: int, start: int, end: int}> */
    private function yearCells(array $page, int $headerAt): array
    {
        for ($j = $headerAt - 1; $j >= max(0, $headerAt - 3); $j--) {
            if ($this->isYearLine($page[$j])) {
                return array_map(fn ($c) => ['year' => (int) $c['text'], 'start' => $c['start'], 'end' => $c['end']], Text::cells($page[$j]));
            }
        }

        return [];
    }

    private function isYearLine(string $line): bool
    {
        $cells = Text::cells($line);

        return $cells !== [] && count(array_filter($cells, fn ($c) => preg_match('/^(19|20)\d{2}$/', $c['text']))) === count($cells);
    }

    /** The cell (year or column) whose span is closest to the given cell. */
    private function nearest(array $candidates, array $cell): ?array
    {
        $best = null;
        $bestDistance = PHP_INT_MAX;
        foreach ($candidates as $c) {
            $d = min(abs($c['start'] - $cell['start']), abs($c['end'] - $cell['end']));
            if ($d < $bestDistance) {
                [$best, $bestDistance] = [$c, $d];
            }
        }

        return $best;
    }

    /**
     * Put each amount under its column. A full row is matched in order; a row with gaps
     * (e.g. "for tomorrow" lines) by the closest column header.
     *
     * @return array<string, float>
     */
    private function assign(array $numbers, array $columns): array
    {
        usort($numbers, fn ($a, $b) => $a['start'] <=> $b['start']);
        usort($columns, fn ($a, $b) => $a['start'] <=> $b['start']);

        if (count($numbers) === count($columns)) {
            $out = [];
            foreach ($columns as $i => $col) {
                $out[$col['period']] = Text::number($numbers[$i]['text']);
            }

            return $out;
        }

        $out = [];
        foreach ($numbers as $n) {
            $col = $this->nearest(array_filter($columns, fn ($c) => ! isset($out[$c['period']])), $n);
            if ($col !== null) {
                $out[$col['period']] = Text::number($n['text']);
            }
        }

        return $out;
    }

    /** Business date (and period start for a month report) from the footer filter. */
    private function dates(PdfText $text, array &$out, bool $single): void
    {
        $printDate = $text->printDate();

        $date = $text->find('/(?:month\s+to\s+date|year\s+to\s+date|calendar\s*\/\s*day|حت[ىي]?\s+تاريخه)\s*('.Text::DATE.')/iu');
        if ($date !== null) {
            $out['business_date'] = Text::date($date);

            return;
        }

        if ($single && preg_match('/calendar\s*\/\s*month\s+(\d{4})\s*[,،]\s*(\d{1,2})/iu', $text->all(), $m)) {
            $start = CarbonImmutable::create((int) $m[1], (int) $m[2], 1);
            $end = $start->endOfMonth()->startOfDay();
            $printed = $printDate ? CarbonImmutable::parse($printDate) : null;
            $out['period_start'] = $start->toDateString();
            if ($printed !== null && $printed->lte($end)) {
                $out['business_date'] = $printed->subDay()->max($start)->toDateString();
                $out['issues'][] = 'Month report printed before the month ended: the business date is the day before the print date.';
            } else {
                $out['business_date'] = $end->toDateString();
            }
        }
    }
}
