<?php

namespace App\Console\Commands;

use App\Models\ReportDocument;
use App\Reports\Extraction\ClaudeReportExtractor;
use App\Reports\Extraction\ExtractionFailed;
use App\Reports\Extraction\ExtractionSchema;
use App\Reports\Extraction\FandaqahReportsPlusExtractor;
use App\Reports\Validation\ExtractionValidator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('fgre:eval-samples {dir=docs/samples/fandaqah-pms : Folder with sample PDFs (and ground-truth JSON)} {--only= : Only files whose name contains this text} {--force : Do not ask before calling the API}')]
#[Description('Run AI extraction on sample PDFs and compare with ground-truth JSON (calls the Claude API: costs money)')]
class EvalSamples extends Command
{
    public function handle(ClaudeReportExtractor $claude, FandaqahReportsPlusExtractor $mapper, ExtractionValidator $validator): int
    {
        $dir = rtrim($this->argument('dir'), '/\\');
        $pdfs = collect(glob("{$dir}/*.pdf") ?: [])
            ->filter(fn (string $f) => ! $this->option('only') || str_contains(basename($f), $this->option('only')))
            ->values();

        if ($pdfs->isEmpty()) {
            $this->components->error("No PDFs found in {$dir}.");

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Send {$pdfs->count()} PDF(s) to the Claude API?", true)) {
            return self::SUCCESS;
        }

        $failures = 0;
        foreach ($pdfs as $pdf) {
            $this->newLine();
            $this->components->info(basename($pdf));

            $path = 'eval/'.basename($pdf);
            Storage::disk('local')->put($path, file_get_contents($pdf));
            $document = new ReportDocument(['storage_disk' => 'local', 'storage_path' => $path]);

            try {
                $result = $claude->extract($document);
            } catch (ExtractionFailed $e) {
                $this->components->error($e->getMessage());
                $failures++;

                continue;
            } finally {
                Storage::disk('local')->delete($path);
            }

            $output = $result->output;
            $this->components->twoColumnDetail('Type / date / provisional', sprintf(
                '%s / %s%s / %s',
                $output['report_type'],
                $output['period_start'] ? $output['period_start'].' → ' : '',
                $output['business_date'] ?? 'null',
                $output['provisional'] ? 'yes' : 'no',
            ));
            $this->components->twoColumnDetail('Hotel', (string) $output['property_name']);
            $this->components->twoColumnDetail('Tokens in / out', "{$result->inputTokens} / {$result->outputTokens}");

            $errors = $validator->validate($output);
            foreach ($errors as $error) {
                $this->components->warn("Check failed: {$error}");
            }

            $diffs = [];
            if ($truth = $this->groundTruth($pdf)) {
                $diffs = $this->compare($mapper->map($mapper->decode($truth)), $output);
                foreach (array_slice($diffs, 0, 25) as $diff) {
                    $this->components->warn($diff);
                }
                $this->components->twoColumnDetail('Differences from ground truth', (string) count($diffs));
            } else {
                $this->components->twoColumnDetail('Ground truth', 'none (checks only)');
            }

            foreach ($output['issues'] as $issue) {
                $this->line("  <fg=gray>AI note: {$issue}</>");
            }

            if ($errors !== [] || $diffs !== []) {
                $failures++;
            }

            Storage::disk('local')->put('eval/results/'.pathinfo($pdf, PATHINFO_FILENAME).'.json', json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        $this->newLine();
        $failures === 0
            ? $this->components->info("All {$pdfs->count()} sample(s) passed.")
            : $this->components->error("{$failures} of {$pdfs->count()} sample(s) need attention. Outputs: storage/app/private/eval/results/");

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** X-ar.pdf and X-en.pdf are both compared with X-en.json (same figures). */
    private function groundTruth(string $pdf): ?string
    {
        $base = preg_replace('/-(ar|en)\.pdf$/', '', $pdf);
        $json = "{$base}-en.json";

        return is_file($json) ? file_get_contents($json) : null;
    }

    /**
     * @param  array<string, mixed>  $truth
     * @param  array<string, mixed>  $actual
     * @return list<string>
     */
    private function compare(array $truth, array $actual): array
    {
        $diffs = [];
        foreach (['report_type', 'business_date', 'period_start', 'provisional'] as $field) {
            if (($truth[$field] ?? null) !== ($actual[$field] ?? null)) {
                $diffs[] = sprintf('%s: expected %s, got %s', $field, json_encode($truth[$field] ?? null), json_encode($actual[$field] ?? null));
            }
        }

        if ($truth['manager_flash'] !== null) {
            $got = collect($actual['manager_flash']['metrics'] ?? [])->keyBy('metric');
            foreach ($truth['manager_flash']['metrics'] as $row) {
                $metric = $row['metric'];
                if (! $got->has($metric)) {
                    $diffs[] = "{$metric}: missing";

                    continue;
                }
                foreach (ExtractionSchema::PERIODS as $period) {
                    if (! $this->same($row[$period], $got[$metric][$period] ?? null)) {
                        $diffs[] = sprintf('%s.%s: expected %s, got %s', $metric, $period, json_encode($row[$period]), json_encode($got[$metric][$period] ?? null));
                    }
                }
            }
        }

        if ($truth['trial_balance'] !== null) {
            $got = collect($actual['trial_balance']['lines'] ?? [])->keyBy(fn ($l) => $l['account_code'].'|'.$l['local_account_code']);
            foreach ($truth['trial_balance']['lines'] as $line) {
                $key = $line['account_code'].'|'.$line['local_account_code'];
                if (! $got->has($key)) {
                    $diffs[] = "account {$line['account_code']}: missing";

                    continue;
                }
                foreach (['debit', 'credit', 'closing_balance'] as $field) {
                    if (! $this->same($line[$field], $got[$key][$field])) {
                        $diffs[] = sprintf('account %s.%s: expected %s, got %s', $line['account_code'], $field, json_encode($line[$field]), json_encode($got[$key][$field]));
                    }
                }
            }
            if ($got->count() !== count($truth['trial_balance']['lines'])) {
                $diffs[] = sprintf('account lines: expected %d, got %d', count($truth['trial_balance']['lines']), $got->count());
            }
        }

        return $diffs;
    }

    private function same(mixed $a, mixed $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }

        return abs((float) $a - (float) $b) < 0.005;
    }
}
