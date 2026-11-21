<?php

namespace Tests\Feature;

use App\Enums\InboundSource;
use App\Mail\SubscriptionReportMail;
use App\Models\InboundMessage;
use App\Models\ReportSubscription;
use App\Models\User;
use App\Reports\Extraction\ReportExtractor;
use App\Reports\IngestReportFile;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesGroups;
use Tests\Fakes\FakeReportExtractor;
use Tests\TestCase;

/** Email subscriptions: daily / weekly / monthly group reports as charts, tables or both. */
class SubscriptionsTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
        $this->withoutVite();
    }

    private function ingestFlash(): void
    {
        $this->app->instance(ReportExtractor::class, new FakeReportExtractor(FakeReportExtractor::flash()));   // business date 2026-11-20
        $message = InboundMessage::create(['source' => InboundSource::Email, 'external_id' => (string) Str::uuid(), 'from_email' => self::SENDER]);
        app(IngestReportFile::class)("%PDF-1.7\n", 'flash.pdf', $message);
    }

    private function subscribe(User $user, array $attributes): ReportSubscription
    {
        return ReportSubscription::forGroup($user->current_group_id)->create([
            'user_id' => $user->id, 'group_id' => $user->current_group_id, 'send_time' => '08:00', ...$attributes,
        ]);
    }

    /** Riyadh local time (Saturday 21 Nov 2026 is the day after the business date). */
    private function at(string $local): void
    {
        $this->travelTo(CarbonImmutable::parse($local, 'Asia/Riyadh'));
    }

    public function test_daily_email_goes_after_the_send_time_once_per_business_date(): void
    {
        $group = $this->group();
        $this->property($group);
        $this->ingestFlash();
        $owner = $this->member($group);
        $this->subscribe($owner, ['frequency' => 'daily']);

        $this->at('2026-11-21 07:50');
        $this->artisan('fgre:send-subscriptions')->assertSuccessful();
        Mail::assertNothingSent();

        $this->at('2026-11-21 08:05');
        $this->artisan('fgre:send-subscriptions')->assertSuccessful();
        $this->artisan('fgre:send-subscriptions')->assertSuccessful();

        Mail::assertSent(SubscriptionReportMail::class, 1);
        Mail::assertSent(SubscriptionReportMail::class, fn ($m) => $m->hasTo($owner->email)
            && $m->report['to'] === '2026-11-20' && array_keys($m->report['sections']) === ['day', 'mtd']);
    }

    public function test_weekly_email_goes_on_its_weekday_and_covers_the_last_seven_days(): void
    {
        $group = $this->group();
        $this->property($group);
        $this->ingestFlash();
        $owner = $this->member($group);
        $sunday = $this->subscribe($owner, ['frequency' => 'weekly', 'weekday' => 0]);
        $saturday = $this->subscribe($owner, ['frequency' => 'weekly', 'weekday' => 6, 'content' => 'charts']);

        $this->at('2026-11-21 09:00');   // a Saturday
        $this->artisan('fgre:send-subscriptions')->assertSuccessful();

        Mail::assertSent(SubscriptionReportMail::class, 1);
        Mail::assertSent(SubscriptionReportMail::class, fn ($m) => $m->subscription->is($saturday)
            && $m->report['from'] === '2026-11-14' && $m->report['to'] === '2026-11-20'
            && $m->attachments() === []);   // charts only: no CSV
        $this->assertNull($sunday->fresh()->last_period_key);
    }

    public function test_tables_subscribers_get_a_csv_and_the_email_renders(): void
    {
        $group = $this->group();
        $this->property($group);
        $this->ingestFlash();
        $owner = $this->member($group);
        $subscription = $this->subscribe($owner, ['frequency' => 'daily', 'content' => 'tables', 'locale' => 'en']);

        $this->actingAs($owner)->post("/subscriptions/{$subscription->id}/send")->assertSessionHas('status');

        Mail::assertSent(SubscriptionReportMail::class, function ($m) {
            $html = $m->render();

            return count($m->attachments()) === 1
                && str_contains($html, 'Fandaqah Riyadh') && str_contains($html, '78.0%')   // USALI: (80 occupied − 2 comp) / 100
                && ! str_contains($html, 'Occupancy by hotel');                             // tables only
        });
        $this->actingAs($owner)->get("/subscriptions/{$subscription->id}/preview")->assertOk()->assertSee('Fandaqah Riyadh');
    }

    public function test_users_subscribe_only_to_their_hotels_and_manage_only_their_own(): void
    {
        $group = $this->group();
        $hotel = $this->property($group);
        $owner = $this->member($group);
        $other = $this->member($group, 'finance');
        $form = ['frequency' => 'monthly', 'month_day' => 1, 'send_time' => '07:30', 'content' => 'both', 'locale' => 'ar', 'include_attention' => true];

        $this->actingAs($owner)->post('/subscriptions', [...$form, 'hotels' => [999999]])->assertSessionHasErrors('hotels.0');
        $this->actingAs($owner)->post('/subscriptions', [...$form, 'hotels' => [$hotel->id]])->assertSessionHas('status');

        $subscription = ReportSubscription::forGroup($group->id)->sole();
        $this->assertNull($subscription->hotels, 'every hotel ticked = all hotels, including future ones');
        $this->assertSame(1, $subscription->month_day);

        $this->actingAs($other)->delete("/subscriptions/{$subscription->id}")->assertNotFound();
        $this->actingAs($owner)->put("/subscriptions/{$subscription->id}", ['is_active' => false])->assertSessionHas('status');
        $this->assertFalse($subscription->fresh()->is_active);
    }

    public function test_no_email_after_the_user_loses_access(): void
    {
        $group = $this->group();
        $this->property($group);
        $this->ingestFlash();
        $owner = $this->member($group);
        $this->subscribe($owner, ['frequency' => 'daily']);
        $owner->groups()->detach($group->id);

        $this->at('2026-11-21 09:00');
        $this->artisan('fgre:send-subscriptions')->assertSuccessful();

        Mail::assertNothingSent();
    }
}
