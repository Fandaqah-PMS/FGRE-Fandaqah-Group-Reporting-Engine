<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\InboundSource;
use App\Enums\ReportType;
use App\Models\DailyPropertyStat;
use App\Models\InboundMessage;
use App\Models\ReportDocument;
use App\Reports\Extraction\ExtractionFailed;
use App\Reports\Extraction\ExtractionSchema;
use App\Reports\Extraction\FandaqahReportsPlusExtractor;
use App\Reports\Extraction\Pdf\PdfText;
use App\Reports\Extraction\Pdf\PdfToText;
use App\Reports\Extraction\Pdf\Text;
use App\Reports\Extraction\PdfReportExtractor;
use App\Reports\IngestReportFile;
use App\Reports\Validation\ExtractionValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesGroups;
use Tests\TestCase;

/**
 * Reading report PDFs without AI. The committed Fandaqah samples exist as PDF (English and
 * Arabic) and as Reports Plus JSON for the same report, so the PDF readers must give exactly
 * the JSON numbers. Layouts whose real samples hold guest data are tested on synthetic text
 * in the same layout (tests/Fixtures/pdf-text).
 */
class PdfReadersTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    private const DIR = __DIR__.'/../../docs/samples/fandaqah-pms/';

    private function pdf(string $file): array
    {
        $this->requirePdfToText();

        return app(PdfReportExtractor::class)->fromText(app(PdfToText::class)->read(file_get_contents(self::DIR.$file)))->output;
    }

    private function fixture(string $file): array
    {
        return app(PdfReportExtractor::class)->fromText(new PdfText(file_get_contents(__DIR__.'/../Fixtures/pdf-text/'.$file)))->output;
    }

    private function fromJson(string $file): array
    {
        $mapper = new FandaqahReportsPlusExtractor;

        return $mapper->map($mapper->decode(file_get_contents(self::DIR.$file)));
    }

    private function requirePdfToText(): void
    {
        $result = Process::run([config('fgre.pdf.pdftotext'), '-h']);
        if (! str_contains($result->output().$result->errorOutput(), '-table')) {
            $this->markTestSkipped('xpdf pdftotext (with -table) is not installed.');
        }
    }

    public function test_text_helpers_read_amounts_dates_and_arabic_labels(): void
    {
        $this->assertSame(-9720.07, Text::number(Text::row(Text::clean('       -  9,720.07     Balance Today'))['numbers'][0]));
        $this->assertSame(-12.0, Text::number('(12.00)'));
        $this->assertSame(5.56, Text::number('5.56%'));
        $this->assertSame('2026-11-19', Text::date('19.11.26'));
        // Arabic presentation forms and a final ي lost by the PDF font still match the label.
        $this->assertSame(Text::key('إجمالي غرف الفندق'), Text::key(Text::clean("\u{202B}إجمال\u{F000} غرف الفندق\u{202C}")));
    }

    /** @return array<string, array{string}> */
    public static function flashPdfs(): array
    {
        return ['English' => ['fandaqah-sample-manager-flash-20261119-en.pdf'], 'Arabic (mirrored)' => ['fandaqah-sample-manager-flash-20261119-ar.pdf']];
    }

    #[DataProvider('flashPdfs')]
    public function test_manager_flash_pdf_gives_exactly_the_json_numbers(string $file): void
    {
        $pdf = $this->pdf($file);
        $json = $this->fromJson('fandaqah-sample-manager-flash-20261119-en.json');

        $this->assertSame('manager_flash', $pdf['report_type']);
        $this->assertSame('2026-11-19', $pdf['business_date']);
        $this->assertTrue($pdf['provisional']);

        $fromPdf = collect($pdf['manager_flash']['metrics'])->keyBy('metric');
        foreach ($json['manager_flash']['metrics'] as $row) {
            foreach (ExtractionSchema::PERIODS as $period) {
                $this->assertEquals($row[$period], $fromPdf[$row['metric']][$period] ?? 'missing', "{$row['metric']} {$period}");
            }
        }
        $this->assertSame([], app(ExtractionValidator::class)->validate($pdf, '2026-11-19'));
    }

    /** @return array<string, array{string}> */
    public static function ledgerPdfs(): array
    {
        return ['English' => ['fandaqah-sample-fin-trial-balance-202611-en.pdf'], 'Arabic (mirrored)' => ['fandaqah-sample-fin-trial-balance-202611-ar.pdf']];
    }

    #[DataProvider('ledgerPdfs')]
    public function test_ledger_trial_balance_pdf_gives_exactly_the_json_accounts(string $file): void
    {
        $pdf = $this->pdf($file);
        $json = $this->fromJson('fandaqah-sample-fin-trial-balance-202611-en.json');

        $this->assertSame('trial_balance', $pdf['report_type']);
        $this->assertSame('2026-11-01', $pdf['period_start']);
        $this->assertSame('2026-11-19', $pdf['business_date']);
        $this->assertSame(1936100.0, $pdf['trial_balance']['printed_total_debit']);

        $fromPdf = collect($pdf['trial_balance']['lines'])->keyBy('account_code');
        $this->assertCount(count($json['trial_balance']['lines']), $fromPdf);
        foreach ($json['trial_balance']['lines'] as $line) {
            foreach (['debit', 'credit', 'closing_balance'] as $field) {
                $this->assertEquals($line[$field], $fromPdf[$line['account_code']][$field], "{$line['account_code']} {$field}");
            }
        }
        $this->assertSame([], app(ExtractionValidator::class)->validate($pdf));
    }

    /** @return array<string, array{string}> */
    public static function frontOfficePdfs(): array
    {
        return ['English' => ['fandaqah-sample-trial-balance-20261119-en.pdf'], 'Arabic (mirrored)' => ['fandaqah-sample-trial-balance-20261119-ar.pdf']];
    }

    #[DataProvider('frontOfficePdfs')]
    public function test_front_office_trial_balance_pdf_reads_every_ledger(string $file): void
    {
        $pdf = $this->pdf($file);

        $this->assertSame('front_office_trial_balance', $pdf['report_type']);
        $this->assertSame('2026-11-19', $pdf['business_date']);
        $ledgers = collect($pdf['front_office_trial_balance']['ledgers'])->keyBy('ledger');
        $this->assertSame(['guest', 'ar', 'deposit', 'package'], $ledgers->keys()->all());
        $this->assertSame(-9720.07, $ledgers['guest']['closing_balance']);
        $this->assertSame(6680.45, $ledgers['ar']['opening_balance']);
        $this->assertSame(-16200.0, $ledgers['deposit']['closing_balance']);
        $this->assertSame(-19239.62, $pdf['front_office_trial_balance']['printed_hotel_balance']);
        $this->assertSame([], app(ExtractionValidator::class)->validate($pdf));
    }

    public function test_guest_trial_balance_reads_folios_statuses_and_the_total(): void
    {
        $out = $this->fixture('guest-trial-balance.txt');

        $this->assertSame('guest_trial_balance', $out['report_type']);
        $this->assertSame('2026-04-27', $out['business_date']);
        $this->assertSame('Sample Plaza Hotel', $out['property_name']);

        $lines = collect($out['guest_trial_balance']['lines'])->keyBy('room');
        $this->assertCount(8, $lines);
        $this->assertSame('in_house', $lines['102']['status']);
        $this->assertSame('checked_out', $lines['107']['status'], 'status cut off: departed before the report date');
        $this->assertSame('checked_out', $lines['130']['status'], '"CKO" (cut) is checked out');
        $this->assertSame('GUEST,DELTA ECHO,Mr.', $lines['130']['name'], 'wrapped name joined');
        $this->assertSame('other', $lines['501']['status'], 'future arrival: a reservation');
        $this->assertSame(-1434.09, $lines['230']['closing_balance']);
        $this->assertSame(424.13, $lines['9003']['debit']);
        $this->assertSame('2026-01-26', $lines['9003']['arrival']);
        $this->assertSame(8829.23, $out['guest_trial_balance']['printed_totals']['closing_balance']);
        $this->assertStringContainsString('3 folio(s)', implode(' ', $out['issues']));
        $this->assertSame([], app(ExtractionValidator::class)->validate($out));
    }

    public function test_month_flash_puts_the_single_column_in_month_to_date(): void
    {
        $out = $this->fixture('manager-flash-month.txt');

        $this->assertSame('2026-04-01', $out['period_start']);
        $this->assertSame('2026-04-26', $out['business_date'], 'printed on 27 April: the day before');
        $metrics = collect($out['manager_flash']['metrics'])->keyBy('metric');
        $this->assertSame(1164.0, $metrics['rooms_occupied']['mtd']);
        $this->assertSame(493844.12, $metrics['room_revenue']['mtd']);
        $this->assertNull($metrics['room_revenue']['day']);
        $this->assertSame([], app(ExtractionValidator::class)->validate($out));
    }

    public function test_scans_and_unknown_layouts_are_refused_with_a_reason(): void
    {
        try {
            app(PdfReportExtractor::class)->fromText(new PdfText("\n\n  12  \n"));
            $this->fail('A scan must not be read.');
        } catch (ExtractionFailed $e) {
            $this->assertStringContainsString('no text', $e->getMessage());
        }

        $this->expectExceptionMessage('not supported yet');
        app(PdfReportExtractor::class)->fromText(new PdfText("Sample Plaza Hotel\nHousekeeping Discrepancy Report\nRoom 101 Vacant Dirty 0.00\nRoom 102 Occupied Clean 1.00\nPrinted by night audit for the whole hotel today"));
    }

    public function test_a_real_pdf_flows_through_the_pipeline_without_any_ai(): void
    {
        $this->requirePdfToText();
        Storage::fake('local');
        $group = $this->group();
        $this->property($group, ['name' => 'Address Hotel', 'name_ar' => 'فندق العنوان', 'code' => 'ADDR']);
        $message = InboundMessage::create(['source' => InboundSource::Email, 'external_id' => (string) Str::uuid(), 'from_email' => self::SENDER]);

        $document = app(IngestReportFile::class)(file_get_contents(self::DIR.'fandaqah-sample-manager-flash-20261119-ar.pdf'), 'manager_flash_20261119.pdf', $message);
        $document = ReportDocument::withoutGlobalScopes()->find($document->id);

        $this->assertSame(DocumentStatus::Processed, $document->status, (string) $document->status_reason);
        $this->assertSame(ReportType::ManagerFlash, $document->report_type);
        $this->assertSame('pdf:manager_flash', $document->extractionRuns()->sole()->extractor);
        $this->assertSame(1254, DailyPropertyStat::forGroup($group->id)->where('period', 'mtd')->sole()->total_rooms);
    }
}
