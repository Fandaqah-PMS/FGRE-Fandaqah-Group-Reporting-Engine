<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Group settings (names, currency, timezone) and the activity log of settings changes. */
class GroupController extends Controller
{
    public function edit(Request $request): Response
    {
        Gate::authorize('manage settings');
        $group = $request->user()->currentGroup;

        return Inertia::render('Settings/Group', [
            'group' => $group->only(['name', 'name_ar', 'currency', 'timezone']),
            'timezones' => array_values(array_filter(timezone_identifiers_list(), fn ($z) => str_starts_with($z, 'Asia/') || str_starts_with($z, 'Africa/') || str_starts_with($z, 'Europe/') || $z === 'UTC')),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('manage settings');
        $group = $request->user()->currentGroup;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'name_ar' => ['nullable', 'string', 'max:150'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'timezone' => ['required', Rule::in(timezone_identifiers_list())],
        ]);

        $group->fill([...$data, 'currency' => strtoupper($data['currency'])]);
        $changed = array_keys($group->getDirty());
        $group->save();
        if ($changed) {
            AuditLog::record('group.updated', $group->name, ['fields' => $changed]);
        }

        return back()->with('status', __('Group settings saved.'));
    }

    public function activity(Request $request): Response
    {
        Gate::authorize('manage settings');

        $logs = AuditLog::with('user:id,name')->latest('created_at')->latest('id')
            ->when($request->query('action'), fn ($q, $a) => $q->where('action', 'like', $a.'.%'))
            ->paginate(30)->withQueryString();

        return Inertia::render('Settings/Activity', [
            'logs' => $logs->through(fn (AuditLog $l) => [
                'id' => $l->id,
                'action' => $l->action,
                'subject' => $l->subject,
                'details' => $l->details,
                'user' => $l->user?->name,
                'ip' => $l->ip,
                'at' => $l->created_at?->toIso8601String(),
            ]),
            'filter' => $request->query('action'),
        ]);
    }
}
