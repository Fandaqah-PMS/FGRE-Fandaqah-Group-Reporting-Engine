<?php

namespace App\Http\Controllers;

use App\Models\ReportSubscription;
use App\Reports\Subscriptions\SubscriptionMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Email subscriptions: each user chooses which group report they get by email,
 * how often, and as charts, tables or both. Users manage only their own.
 */
class SubscriptionController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('view reports');

        return Inertia::render('Subscriptions', [
            'subscriptions' => ReportSubscription::where('user_id', $request->user()->id)->latest()->get()
                ->map(fn (ReportSubscription $s) => $this->present($s)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('view reports');

        ReportSubscription::create([
            ...$this->validated($request),
            'user_id' => $request->user()->id,
            'group_id' => $request->user()->current_group_id,
        ]);

        return back()->with('status', __('Subscribed. The first email goes out at the next scheduled time.'));
    }

    public function update(Request $request, ReportSubscription $subscription): RedirectResponse
    {
        $this->authorizeOwn($request, $subscription);

        $subscription->update($request->has('frequency')
            ? [...$this->validated($request), 'last_period_key' => null]
            : $request->validate(['is_active' => ['required', 'boolean']]));

        return back()->with('status', __('Subscription saved.'));
    }

    public function destroy(Request $request, ReportSubscription $subscription): RedirectResponse
    {
        $this->authorizeOwn($request, $subscription);
        $subscription->delete();

        return back()->with('status', __('Subscription removed.'));
    }

    /** "Send me one now": the latest period, to the user's own address. */
    public function send(Request $request, ReportSubscription $subscription, SubscriptionMailer $mailer): RedirectResponse
    {
        $this->authorizeOwn($request, $subscription);

        return back()->with('status', $mailer->sendNow($subscription)
            ? __('Sent to :email.', ['email' => $request->user()->email])
            : __('Nothing to send yet: no reports for this period.'));
    }

    /** The email as it would look now, in the browser. */
    public function preview(Request $request, ReportSubscription $subscription, SubscriptionMailer $mailer)
    {
        $this->authorizeOwn($request, $subscription);

        return $mailer->mailable($subscription) ?? response(__('Nothing to send yet: no reports for this period.'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $allowed = $request->user()->accessiblePropertyIds()->all();

        $data = $request->validate([
            'frequency' => ['required', Rule::in(ReportSubscription::FREQUENCIES)],
            'send_time' => ['required', 'date_format:H:i'],
            'weekday' => ['nullable', 'required_if:frequency,weekly', 'integer', 'between:0,6'],
            'month_day' => ['nullable', 'required_if:frequency,monthly', 'integer', 'between:1,28'],
            'content' => ['required', Rule::in(ReportSubscription::CONTENTS)],
            'hotels' => ['nullable', 'array'],
            'hotels.*' => ['integer', Rule::in($allowed)],
            'include_attention' => ['boolean'],
            'locale' => ['required', Rule::in(['ar', 'en'])],
        ]);

        return [
            ...$data,
            'weekday' => $data['frequency'] === 'weekly' ? (int) $data['weekday'] : null,
            'month_day' => $data['frequency'] === 'monthly' ? (int) $data['month_day'] : null,
            // Every hotel ticked = "all my hotels", so hotels added later are included too.
            'hotels' => empty($data['hotels']) || count(array_unique($data['hotels'])) >= count($allowed) ? null : array_values(array_map('intval', array_unique($data['hotels']))),
            'include_attention' => (bool) ($data['include_attention'] ?? true),
        ];
    }

    private function authorizeOwn(Request $request, ReportSubscription $subscription): void
    {
        abort_unless($subscription->user_id === $request->user()->id, 404);
    }

    /** @return array<string, mixed> */
    private function present(ReportSubscription $s): array
    {
        return [
            ...$s->only(['id', 'frequency', 'send_time', 'weekday', 'month_day', 'content', 'hotels', 'include_attention', 'locale', 'is_active']),
            'last_sent_at' => $s->last_sent_at?->toIso8601String(),
        ];
    }
}
