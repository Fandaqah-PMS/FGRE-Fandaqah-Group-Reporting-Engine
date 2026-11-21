<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PmsProvider;
use App\Models\Property;
use App\Models\ReportDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Hotels of the current group. Rooms feed the KPIs (rooms available); sender addresses and
 * other names decide which emailed reports are accepted and matched to the hotel.
 * Hotels are deactivated, never deleted, so their history stays.
 */
class HotelsController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('manage properties');

        $lastReport = ReportDocument::selectRaw('property_id, max(created_at) as at')->groupBy('property_id')->pluck('at', 'property_id');

        return Inertia::render('Settings/Hotels', [
            'hotels' => Property::with('pmsProvider:id,name')->orderBy('name')->get()->map(fn (Property $p) => [
                ...$p->only(['id', 'name', 'name_ar', 'code', 'region', 'total_rooms', 'pms_provider_id', 'timezone', 'is_active']),
                'pms' => $p->pmsProvider?->name,
                'allowed_sender_emails' => $p->allowed_sender_emails ?? [],
                'aliases' => $p->aliases ?? [],
                'last_report_at' => isset($lastReport[$p->id]) ? substr((string) $lastReport[$p->id], 0, 16) : null,
            ]),
            'providers' => PmsProvider::orderBy('name')->get(['id', 'name']),
            'regions' => Property::whereNotNull('region')->distinct()->orderBy('region')->pluck('region'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage properties');
        $property = Property::create([...$this->validated($request), 'group_id' => $request->user()->current_group_id]);
        AuditLog::record('hotel.added', $property->name);

        return back()->with('status', __(':name added.', ['name' => $property->name]));
    }

    public function update(Request $request, Property $property): RedirectResponse
    {
        Gate::authorize('manage properties');
        $property->fill($this->validated($request, $property));
        $changed = array_keys($property->getDirty());
        $property->save();
        if ($changed) {
            AuditLog::record('hotel.updated', $property->name, ['fields' => $changed]);
        }

        return back()->with('status', __(':name saved.', ['name' => $property->name]));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Property $property = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'name_ar' => ['nullable', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('properties', 'code')->where('group_id', $request->user()->current_group_id)->ignore($property?->id)],
            'pms_provider_id' => ['required', Rule::exists('pms_providers', 'id')],
            'total_rooms' => ['required', 'integer', 'between:1,10000'],
            'region' => ['nullable', 'string', 'max:60'],
            'timezone' => ['required', Rule::in(timezone_identifiers_list())],
            'allowed_sender_emails' => ['array', 'max:20'],
            'allowed_sender_emails.*' => ['email', 'max:190'],
            'aliases' => ['array', 'max:20'],
            'aliases.*' => ['string', 'max:150'],
            'is_active' => ['boolean'],
        ]);

        return [
            ...$data,
            'code' => strtoupper($data['code']),
            'allowed_sender_emails' => array_values(array_unique(array_map('mb_strtolower', $data['allowed_sender_emails'] ?? []))),
            'aliases' => array_values(array_unique(array_filter(array_map('trim', $data['aliases'] ?? [])))),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }
}
