<?php

namespace App\Http\Controllers;

use App\Enums\StatPeriod;
use App\Models\DailyPropertyStat;
use App\Models\Property;
use App\Models\ReportDocument;
use App\Reports\Kpi\GroupKpiService;
use App\Reports\Kpi\KpiCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class HotelController extends Controller
{
    public function show(Request $request, Property $property, GroupKpiService $kpis): Response
    {
        Gate::authorize('view reports');
        abort_unless($request->user()->accessiblePropertyIds()->contains($property->id), 404);

        $date = $request->query('date');
        $date = is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            ? $date
            : $kpis->latestDate($property->group_id, [$property->id]);

        $columns = [];
        if ($date !== null) {
            $stats = DailyPropertyStat::where('property_id', $property->id)->whereDate('business_date', $date)->get()->keyBy(fn ($s) => $s->period->value);
            foreach (StatPeriod::cases() as $period) {
                $stat = $stats->get($period->value);
                $parts = $stat ? KpiCalculator::sumParts([$stat]) : null;
                $columns[$period->value] = $parts === null ? null : [...$parts, ...KpiCalculator::compute($parts), 'is_provisional' => $stat->is_provisional];
            }
        }

        $property->load('pmsProvider');

        return Inertia::render('Hotel', [
            'hotel' => [
                'id' => $property->id, 'name' => $property->name, 'name_ar' => $property->name_ar,
                'code' => $property->code, 'pms' => $property->pmsProvider?->name,
                'total_rooms' => $property->total_rooms, 'currency' => $property->currency,
            ],
            'date' => $date,
            'columns' => $columns,
            'trend' => $date ? $kpis->trend($property->group_id, CarbonImmutable::parse($date)->subDays(59)->toDateString(), $date, [$property->id]) : [],
            'documents' => ReportDocument::where('property_id', $property->id)->latest()->limit(8)->get()
                ->map(fn (ReportDocument $d) => [
                    'id' => $d->id, 'type' => $d->report_type->value, 'date' => $d->business_date?->toDateString(),
                    'status' => $d->status->value, 'received' => $d->created_at->toDateTimeString(),
                ]),
        ]);
    }
}
