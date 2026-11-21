<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Jobs\ProcessReportDocument;
use App\Models\ReportDocument;
use App\Support\HotelScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Received reports and the review queue for the current group. */
class DocumentsController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('view reports');

        $status = DocumentStatus::tryFrom((string) $request->query('status'));
        $propertyIds = HotelScope::propertyIds($request);

        $documents = ReportDocument::with(['property:id,name,name_ar', 'inboundMessage:id,source,from_email,subject'])
            ->whereIn('property_id', $propertyIds)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (ReportDocument $d) => [
                'id' => $d->id,
                'hotel' => $d->property ? ['name' => $d->property->name, 'name_ar' => $d->property->name_ar] : null,
                'type' => $d->report_type->value,
                'period_start' => $d->period_start?->toDateString(),
                'date' => $d->business_date?->toDateString(),
                'provisional' => $d->is_provisional,
                'status' => $d->status->value,
                'reason' => $d->status_reason,
                'source' => $d->inboundMessage?->source->value,
                'from' => $d->inboundMessage?->from_email,
                'file' => $d->original_filename,
                'received' => $d->created_at->toDateTimeString(),
            ]);

        $counts = ReportDocument::whereIn('property_id', $propertyIds)
            ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return Inertia::render('Documents', [
            'documents' => $documents,
            'status' => $status?->value,
            'counts' => $counts,
        ]);
    }

    public function reprocess(Request $request, ReportDocument $document): RedirectResponse
    {
        Gate::authorize('review documents');
        abort_unless($request->user()->accessiblePropertyIds()->contains($document->property_id), 404);

        $document->markStatus(DocumentStatus::Received);
        ProcessReportDocument::dispatch($document->id);

        return back()->with('status', __('Document queued for processing again.'));
    }
}
