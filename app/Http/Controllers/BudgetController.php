<?php

namespace App\Http\Controllers;

use App\Models\BudgetMonth;
use App\Models\Property;
use App\Support\HotelScope;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Monthly budgets per hotel: download a CSV template, fill it, upload it.
 * Every row is checked; nothing is saved if any row is wrong, and the errors are listed
 * by row number (import with template, row-level validation, error report).
 */
class BudgetController extends Controller
{
    public const COLUMNS = ['hotel_code', 'month', 'rooms_sold', 'room_revenue', 'fnb_revenue', 'other_revenue', 'total_revenue'];

    public function index(Request $request): Response
    {
        Gate::authorize('view reports');

        $year = (int) ($request->query('year') ?: now()->year);
        $ids = HotelScope::propertyIds($request);
        $hotels = Property::whereIn('id', $ids)->orderBy('name')->get(['id', 'name', 'name_ar', 'code', 'total_rooms']);
        $budgets = BudgetMonth::whereIn('property_id', $ids)->whereYear('month', $year)->get();

        return Inertia::render('Budgets', [
            'year' => $year,
            'hotels' => $hotels,
            'cells' => $budgets->map(fn (BudgetMonth $b) => [
                'property_id' => $b->property_id, 'month' => $b->month->format('Y-m'),
                'rooms_sold' => $b->rooms_sold, 'room_revenue' => (float) $b->room_revenue, 'total_revenue' => (float) $b->total_revenue,
            ])->values(),
            'canManage' => Gate::allows('manage budgets'),
            'importErrors' => $request->session()->get('budget_errors', []),
        ]);
    }

    public function template(Request $request): StreamedResponse
    {
        Gate::authorize('manage budgets');

        $year = (int) ($request->query('year') ?: now()->year);
        $hotels = Property::whereIn('id', HotelScope::propertyIds($request))->orderBy('name')->get(['id', 'code']);
        $existing = BudgetMonth::whereIn('property_id', $hotels->pluck('id'))->whereYear('month', $year)->get()
            ->keyBy(fn ($b) => $b->property_id.'|'.$b->month->format('Y-m'));

        return response()->streamDownload(function () use ($hotels, $year, $existing) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::COLUMNS);
            foreach ($hotels as $h) {
                for ($m = 1; $m <= 12; $m++) {
                    $month = sprintf('%d-%02d', $year, $m);
                    $b = $existing->get($h->id.'|'.$month);
                    fputcsv($out, [$h->code, $month, $b?->rooms_sold ?? '', $b?->room_revenue ?? '', $b?->fnb_revenue ?? '', $b?->other_revenue ?? '', $b?->total_revenue ?? '']);
                }
            }
            fclose($out);
        }, "budget-template-{$year}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(Request $request): RedirectResponse
    {
        Gate::authorize('manage budgets');
        $request->validate(['file' => ['required', 'file', 'max:2048']]);

        $hotels = Property::whereIn('id', $request->user()->accessiblePropertyIds())->get(['id', 'group_id', 'code', 'total_rooms'])
            ->keyBy(fn ($p) => mb_strtolower($p->code));

        $lines = array_map('str_getcsv', preg_split('/\r\n|\n|\r/', ltrim(file_get_contents($request->file('file')->getRealPath()), "\xEF\xBB\xBF")));
        $header = array_map(fn ($h) => mb_strtolower(trim($h)), array_shift($lines) ?? []);
        if (array_diff(self::COLUMNS, $header) !== []) {
            return back()->with('budget_errors', [__('The file must have these columns: :cols', ['cols' => implode(', ', self::COLUMNS)])]);
        }

        $errors = [];
        $rows = [];
        foreach ($lines as $i => $line) {
            $n = $i + 2;
            if (count(array_filter($line, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $r = array_combine($header, array_pad($line, count($header), ''));
            if (trim($r['rooms_sold']) === '' && trim($r['total_revenue']) === '') {
                continue;   // template row left empty
            }
            $hotel = $hotels->get(mb_strtolower(trim($r['hotel_code'])));
            $month = trim($r['month']);
            $num = fn ($k) => is_numeric(str_replace(',', '', trim($r[$k] ?: '0'))) ? (float) str_replace(',', '', trim($r[$k] ?: '0')) : null;
            $vals = array_combine(array_slice(self::COLUMNS, 2), array_map($num, array_slice(self::COLUMNS, 2)));

            if ($hotel === null) {
                $errors[] = __('Row :n: unknown hotel code [:code].', ['n' => $n, 'code' => $r['hotel_code']]);
            } elseif (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
                $errors[] = __('Row :n: month must look like 2026-01.', ['n' => $n]);
            } elseif (in_array(null, $vals, true) || min($vals) < 0) {
                $errors[] = __('Row :n: figures must be numbers of zero or more.', ['n' => $n]);
            } elseif ($hotel->total_rooms && $vals['rooms_sold'] > $hotel->total_rooms * CarbonImmutable::parse("{$month}-01")->daysInMonth) {
                $errors[] = __('Row :n: rooms sold are more than the hotel has in that month.', ['n' => $n]);
            } elseif ($vals['total_revenue'] + 0.01 < $vals['room_revenue'] + $vals['fnb_revenue'] + $vals['other_revenue']) {
                $errors[] = __('Row :n: total revenue is less than rooms + F&B + other.', ['n' => $n]);
            } else {
                $rows[] = ['group_id' => $hotel->group_id, 'property_id' => $hotel->id, 'month' => "{$month}-01", ...$vals, 'rooms_sold' => (int) $vals['rooms_sold']];
            }
        }

        if ($errors !== []) {
            return back()->with('budget_errors', $errors);
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                BudgetMonth::forGroup($row['group_id'])->updateOrCreate(['property_id' => $row['property_id'], 'month' => $row['month']], $row);
            }
        });

        return back()->with('status', __(':n budget months saved.', ['n' => count($rows)]));
    }
}
