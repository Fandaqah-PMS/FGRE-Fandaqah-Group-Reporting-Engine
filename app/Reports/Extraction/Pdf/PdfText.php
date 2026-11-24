<?php

namespace App\Reports\Extraction\Pdf;

/**
 * The text of a report PDF, extracted twice by pdftotext:
 *  - table mode: rows stay aligned with their amounts (used to read the tables);
 *  - layout mode: page headers and footers come out whole (used for dates, hotel, flags).
 * Every line is already cleaned (Text::clean). Pages are split on form feeds.
 */
final class PdfText
{
    /** @var list<list<string>> */
    public readonly array $pages;

    /** @var list<string> */
    public readonly array $layoutLines;

    public function __construct(string $table, ?string $layout = null)
    {
        $this->pages = array_values(array_map(
            fn (string $page) => array_map(Text::clean(...), explode("\n", str_replace("\r", '', $page))),
            array_filter(explode("\f", $table), fn ($p) => trim($p) !== ''),
        ));
        $this->layoutLines = array_map(Text::clean(...), explode("\n", str_replace(["\r", "\f"], ['', "\n"], $layout ?? $table)));
    }

    /** @return list<string> every table line, all pages */
    public function lines(): array
    {
        return array_merge(...($this->pages ?: [[]]));
    }

    /** Layout text and table text together, for searching headers and footers. */
    public function all(): string
    {
        return implode("\n", [...$this->layoutLines, ...$this->lines()]);
    }

    public function has(string $pattern): bool
    {
        return preg_match($pattern, $this->all()) === 1;
    }

    /** First capture group of the first match, in layout text first, then table text. */
    public function find(string $pattern): ?string
    {
        return preg_match($pattern, $this->all(), $m) ? ($m[1] ?? $m[0]) : null;
    }

    /** True when there is (almost) no text: a scan or a photo of a report. */
    public function isEmpty(): bool
    {
        return mb_strlen(preg_replace('/\s+/u', '', implode('', $this->lines())) ?? '') < 40;
    }

    /** The print date in the page header ("Date/Time: 21.11.26", or the date next to the hotel name). */
    public function printDate(): ?string
    {
        $date = $this->find('/(?:date\s*\/\s*time|التاريخ\s*\/\s*الوقت)\s*:?\s*('.Text::DATE.')/iu');
        if ($date !== null) {
            return Text::date($date);
        }
        foreach (array_slice(array_values(array_filter($this->layoutLines, fn ($l) => trim($l) !== '')), 0, 3) as $line) {
            if (preg_match('/(?<![\d.])('.Text::DATE.')(?![\d.])/', $line, $m)) {
                return Text::date($m[1]);
            }
        }

        return null;
    }

    public function provisional(): bool
    {
        return $this->has('/PROVISIONAL|not closed by\s+night\s+audit|مؤقت|لم\s+يغلق/iu');
    }

    /**
     * The hotel name printed in the page header: the first header cell that is not the title,
     * a date, a time, the print user or a "label:" field.
     */
    public function propertyName(string $titlePattern): ?string
    {
        foreach (array_slice($this->pages[0] ?? [], 0, 8) as $line) {
            foreach (Text::cells($line) as $cell) {
                $t = trim($cell['text']);
                if (preg_match($titlePattern, $t)) {
                    return null;    // reached the title: no hotel name printed above it
                }
                if (preg_match('/'.Text::DATE.'|^\d{1,2}:\d{2}|:|^(page|صفحة)\b|provisional|مؤقت|@/iu', $t) || ! preg_match('/\p{L}{2,}/u', $t)) {
                    continue;
                }

                return $t;
            }
        }

        return null;
    }

    /** The report title as printed (first match of the title pattern). */
    public function title(string $titlePattern): ?string
    {
        foreach ($this->lines() as $line) {
            if (preg_match($titlePattern, $line, $m)) {
                return trim($m[0]);
            }
        }

        return null;
    }
}
