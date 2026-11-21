<?php

namespace Database\Seeders;

use App\Enums\DocumentStatus;
use App\Enums\InboundSource;
use App\Models\HotelGroup;
use App\Models\PmsProvider;
use App\Models\Property;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Realistic demo data for a fictional 5-hotel group on three PMSs, so the
 * group dashboard, hotel pages, financials and review queue can be seen
 * locally. Deterministic (fixed random seed). Local only.
 *
 * Logins (password "password"):
 *   owner@demo.example    owner, both demo groups
 *   finance@demo.example  finance role
 *   viewer@demo.example   viewer, limited to two hotels
 */
class DemoDataSeeder extends Seeder
{
    private const FIRST_DAY = '2025-01-01';

    private const LAST_DAY = '2026-11-20';

    private const SENDERS = [
        'fandaqah' => 'reports@fandaqah.example',
        'opera' => 'opera-reports@hotelmail.example',
        'nazeel' => 'noreply@nazeel.example',
    ];

    /** name, name_ar, code, pms, rooms, base occupancy, base ADR, F&B ratio, season profile */
    private const HOTELS = [
        ['Al Waha Hotel Riyadh', 'فندق الواحة الرياض', 'RUH-WAH', 'fandaqah', 120, 0.71, 540, 0.22, 'business'],
        ['Corniche Suites Jeddah', 'أجنحة الكورنيش جدة', 'JED-COR', 'opera', 210, 0.68, 610, 0.30, 'coastal'],
        ['Madinah Gate Hotel', 'فندق بوابة المدينة', 'MED-GAT', 'nazeel', 160, 0.74, 480, 0.12, 'pilgrim'],
        ['Al Khobar Bay Residences', 'مساكن خليج الخبر', 'KHB-BAY', 'fandaqah', 80, 0.66, 430, 0.10, 'business'],
        ['Abha Heights Hotel', 'فندق مرتفعات أبها', 'AHB-HGT', 'nazeel', 95, 0.58, 390, 0.18, 'summer'],
    ];

    public function run(): void
    {
        mt_srand(20261121);

        $group = HotelGroup::updateOrCreate(['slug' => 'al-noor-hospitality'], [
            'name' => 'Al Noor Hospitality Group',
            'name_ar' => 'مجموعة النور للضيافة',
        ]);

        $properties = [];
        foreach (self::HOTELS as [$name, $nameAr, $code, $pms, $rooms]) {
            $properties[] = Property::forGroup($group->id)->updateOrCreate(['group_id' => $group->id, 'code' => $code], [
                'pms_provider_id' => PmsProvider::where('code', $pms)->value('id'),
                'name' => $name,
                'name_ar' => $nameAr,
                'total_rooms' => $rooms,
                'allowed_sender_emails' => [self::SENDERS[$pms]],
            ]);
        }

        $this->users($group, $properties);

        foreach ($properties as $i => $property) {
            $this->wipe($property);
            $days = $this->simulate(self::HOTELS[$i]);
            $this->writeStats($property, $days, $i);
            $this->writeTrialBalances($property, $days);
            $this->writeLedgers($property);
        }

        $this->writeReviewQueue($properties);
    }

    /** @param list<Property> $properties */
    private function users(HotelGroup $group, array $properties): void
    {
        $demoGroup = HotelGroup::where('slug', 'demo-group')->first();

        $owner = User::updateOrCreate(['email' => 'owner@demo.example'], ['name' => 'Demo Owner', 'password' => 'password']);
        $owner->groups()->syncWithoutDetaching([$group->id => ['role' => 'owner', 'all_properties' => true]]);
        if ($demoGroup) {
            $owner->groups()->syncWithoutDetaching([$demoGroup->id => ['role' => 'owner', 'all_properties' => true]]);
        }
        $owner->forceFill(['current_group_id' => $group->id])->save();

        $finance = User::updateOrCreate(['email' => 'finance@demo.example'], ['name' => 'Group Finance', 'password' => 'password']);
        $finance->groups()->syncWithoutDetaching([$group->id => ['role' => 'finance', 'all_properties' => true]]);
        $finance->forceFill(['current_group_id' => $group->id])->save();

        $viewer = User::updateOrCreate(['email' => 'viewer@demo.example'], ['name' => 'Western Region Manager', 'password' => 'password']);
        $viewer->groups()->syncWithoutDetaching([$group->id => ['role' => 'viewer', 'all_properties' => false]]);
        $viewer->restrictedProperties()->sync([$properties[1]->id, $properties[2]->id]);
        $viewer->forceFill(['current_group_id' => $group->id])->save();
    }

    private function wipe(Property $property): void
    {
        foreach (['daily_property_stats', 'revenue_lines', 'trial_balance_lines', 'ledger_balances', 'transaction_code_lines'] as $table) {
            DB::table($table)->where('property_id', $property->id)->delete();
        }
        $documentIds = DB::table('report_documents')->where('property_id', $property->id)->pluck('id');
        DB::table('extraction_runs')->whereIn('report_document_id', $documentIds)->delete();
        DB::table('report_documents')->whereIn('id', $documentIds)->delete();
    }

    /**
     * One simulated night per calendar day.
     *
     * @return array<string, array<string, int|float>>
     */
    private function simulate(array $hotel): array
    {
        [, , , , $rooms, $baseOcc, $baseAdr, $fnbRatio, $profile] = $hotel;
        $days = [];

        for ($d = CarbonImmutable::parse(self::FIRST_DAY); $d->lte(self::LAST_DAY); $d = $d->addDay()) {
            $season = $this->season($profile, $d);
            $weekend = in_array($d->dayOfWeek, [4, 5], true);   // Thu, Fri nights
            $weekday = match ($profile) {
                'business' => $weekend ? -0.10 : 0.04,
                default => $weekend ? 0.09 : -0.03,
            };
            $growth = $d->year === 2026 ? 0.03 : 0.0;           // this year runs a little ahead

            $ooo = mt_rand(0, 100) < 85 ? mt_rand(0, 2) : mt_rand(3, (int) ($rooms * 0.06));
            $sellable = $rooms - $ooo;
            $occ = min(0.985, max(0.18, $baseOcc + $season + $weekday + $growth + $this->noise(0.05)));
            $occupied = (int) round($sellable * $occ);
            $comp = mt_rand(0, 100) < 30 ? mt_rand(1, 2) : 0;
            $house = mt_rand(0, 100) < 15 ? 1 : 0;
            $paid = max(0, $occupied - $comp - $house);

            $adr = $baseAdr * (1 + $season * 1.4 + ($weekend && $profile !== 'business' ? 0.08 : 0) + $growth + $this->noise(0.04));
            $roomRevenue = round($paid * $adr, 2);
            $fnb = round($roomRevenue * $fnbRatio * (1 + $this->noise(0.15)), 2);
            $other = round($roomRevenue * 0.035 * (1 + $this->noise(0.3)), 2);

            $days[$d->toDateString()] = [
                'total_rooms' => $rooms,
                'rooms_occupied' => $occupied,
                'ooo_rooms' => $ooo,
                'oos_rooms' => mt_rand(0, 100) < 10 ? 1 : 0,
                'comp_rooms' => $comp,
                'house_use_rooms' => $house,
                'day_use_rooms' => mt_rand(0, 3),
                'room_revenue' => $roomRevenue,
                'fnb_revenue' => $fnb,
                'other_revenue' => $other,
                'total_revenue' => round($roomRevenue + $fnb + $other, 2),
                'persons_in_house' => (int) round($occupied * (1.45 + $this->noise(0.1))),
                'arrival_rooms' => (int) round($occupied * (0.38 + $this->noise(0.08))),
                'departure_rooms' => (int) round($occupied * (0.37 + $this->noise(0.08))),
                'no_show_rooms' => mt_rand(0, 3),
                'cancellations' => mt_rand(0, 6),
            ];
        }

        return $days;
    }

    /** Seasonal occupancy shift by hotel type (Hijri peaks approximated by Gregorian dates). */
    private function season(string $profile, CarbonImmutable $d): float
    {
        $md = (int) $d->format('nd');
        $inRange = fn (string $from, string $to) => $d->betweenIncluded($from, $to);

        return match ($profile) {
            'pilgrim' => ($inRange('2025-03-01', '2025-03-30') || $inRange('2026-02-18', '2026-03-19')) ? 0.20     // Ramadan
                : (($inRange('2025-05-25', '2025-06-12') || $inRange('2026-05-15', '2026-06-02')) ? 0.22 : -0.02), // Hajj
            'summer' => ($md >= 615 && $md <= 905) ? 0.24 : (($md >= 1201 || $md <= 115) ? 0.05 : -0.06),
            'coastal' => ($md >= 701 && $md <= 831) ? 0.08 : (($md >= 1215 || $md <= 110) ? 0.10 : 0.0),
            default => ($md >= 701 && $md <= 831) ? -0.12 : (($md >= 1015 && $md <= 1215) ? 0.06 : 0.0),
        };
    }

    private function noise(float $spread): float
    {
        return (mt_rand(-1000, 1000) / 1000) * $spread;
    }

    /** Day / MTD / YTD for this year and last year, one flash document per business day of 2026. */
    private function writeStats(Property $property, array $days, int $hotelIndex): void
    {
        $now = now();
        $rows = [];
        $sender = self::SENDERS[self::HOTELS[$hotelIndex][3]];

        for ($d = CarbonImmutable::parse('2026-01-01'); $d->lte(self::LAST_DAY); $d = $d->addDay()) {
            // One hotel has not reported the latest day yet; another sent it provisional.
            if ($hotelIndex === 4 && $d->toDateString() === self::LAST_DAY) {
                continue;
            }
            $provisional = $hotelIndex === 1 && $d->toDateString() === self::LAST_DAY;

            $documentId = $this->document($property, 'manager_flash', $d->toDateString(), null, $provisional, $sender, "manager_flash_{$d->format('Ymd')}.pdf");
            $ly = $d->subYear();

            $periods = [
                'day' => [$d, $d],
                'mtd' => [$d->startOfMonth(), $d],
                'ytd' => [$d->startOfYear(), $d],
                'day_ly' => [$ly, $ly],
                'mtd_ly' => [$ly->startOfMonth(), $ly],
                'ytd_ly' => [$ly->startOfYear(), $ly],
            ];

            foreach ($periods as $period => [$from, $to]) {
                $sum = $this->sumDays($days, $from, $to);
                $occupied = $sum['rooms_occupied'];
                $rows[] = [
                    'group_id' => $property->group_id,
                    'property_id' => $property->id,
                    'report_document_id' => $documentId,
                    'business_date' => $d->toDateString(),
                    'period' => $period,
                    'is_provisional' => $provisional,
                    'currency' => 'SAR',
                    ...$sum,
                    // As a PMS would print them (plain definitions).
                    'printed_occupancy_pct' => round($occupied / max(1, $sum['total_rooms']) * 100, 2),
                    'printed_adr' => $occupied > 0 ? round($sum['room_revenue'] / $occupied, 2) : 0,
                    'printed_revpar' => round($sum['room_revenue'] / max(1, $sum['total_rooms']), 2),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (count($rows) >= 600) {
                DB::table('daily_property_stats')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('daily_property_stats')->insert($rows);
        }
    }

    /** @return array<string, int|float> */
    private function sumDays(array $days, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $sum = array_fill_keys(array_keys(reset($days)), 0);
        for ($d = $from; $d->lte($to); $d = $d->addDay()) {
            foreach ($days[$d->toDateString()] ?? [] as $k => $v) {
                $sum[$k] += $v;
            }
        }
        foreach (['room_revenue', 'fnb_revenue', 'other_revenue', 'total_revenue'] as $k) {
            $sum[$k] = round($sum[$k], 2);
        }

        return $sum;
    }

    /** Monthly general-ledger trial balances (NTMP-style accounts), always balanced. */
    private function writeTrialBalances(Property $property, array $days): void
    {
        $now = now();
        $months = [['2026-08-01', '2026-08-31'], ['2026-09-01', '2026-09-30'], ['2026-10-01', '2026-10-31'], ['2026-11-01', '2026-11-19']];

        foreach ($months as [$from, $to]) {
            $sum = $this->sumDays($days, CarbonImmutable::parse($from), CarbonImmutable::parse($to));
            $rooms = $sum['room_revenue'];
            $vat = round($sum['total_revenue'] * 0.15, 2);
            $payroll = round($property->total_rooms * 1150 * (CarbonImmutable::parse($to)->day / 30), 2);

            // [code, name, debit, credit]
            $lines = [
                ['410101', 'Room Revenue - Individual / Transient', 0, round($rooms * 0.62, 2)],
                ['410103', 'Room Revenue - Corporate', 0, round($rooms * 0.23, 2)],
                ['410104', 'Room Revenue - OTA', 0, round($rooms - round($rooms * 0.62, 2) - round($rooms * 0.23, 2), 2)],
                ['420101', 'Restaurant Revenue', 0, $sum['fnb_revenue']],
                ['430102', 'Laundry Revenue', 0, $sum['other_revenue']],
                ['213101', 'VAT Output Payable', 0, $vat],
                ['510101', 'Rooms Payroll - Salaries and Wages', $payroll, 0],
                ['510201', 'Guest Supplies Expense', round($rooms * 0.045, 2), 0],
                ['520101', 'F&B Cost of Sales', round($sum['fnb_revenue'] * 0.34, 2), 0],
                ['610101', 'Utilities - Electricity', round($property->total_rooms * 210, 2), 0],
                ['112101', 'Guest Ledger Receivable', round($sum['total_revenue'] * 0.08, 2), 0],
                ['112103', 'OTA Receivable', round($rooms * 0.09, 2), 0],
            ];
            $debits = array_sum(array_column($lines, 2));
            $credits = array_sum(array_column($lines, 3));
            $lines[] = ['111201', 'Bank Account - SAR', round($credits - $debits, 2), 0];   // balancing cash receipts

            $documentId = $this->document($property, 'trial_balance', $to, $from, false, 'finance@'.Str::slug($property->code).'.example', "trial_balance_{$from}_{$to}.pdf");

            DB::table('trial_balance_lines')->insert(array_map(fn (array $l) => [
                'group_id' => $property->group_id,
                'property_id' => $property->id,
                'report_document_id' => $documentId,
                'period_start' => $from,
                'business_date' => $to,
                'account_code' => $l[0],
                'local_account_code' => $l[0],
                'account_name' => $l[1],
                'account_class' => null,
                'opening_balance' => null,
                'debit' => $l[2],
                'credit' => $l[3],
                'closing_balance' => round($l[2] - $l[3], 2),
                'currency' => 'SAR',
                'created_at' => $now,
                'updated_at' => $now,
            ], $lines));
        }
    }

    private function writeLedgers(Property $property): void
    {
        $date = '2026-11-19';
        $documentId = $this->document($property, 'front_office_trial_balance', $date, null, false, 'nightaudit@'.Str::slug($property->code).'.example', 'trial_balance_20261119.pdf');
        $scale = $property->total_rooms * 100;

        foreach (['guest' => -0.9, 'ar' => 1.6, 'deposit' => -1.2, 'package' => 0.0] as $ledger => $factor) {
            DB::table('ledger_balances')->insert([
                'group_id' => $property->group_id,
                'property_id' => $property->id,
                'report_document_id' => $documentId,
                'business_date' => $date,
                'ledger' => $ledger,
                'opening_balance' => round($scale * $factor * (1 + $this->noise(0.2)), 2),
                'closing_balance' => round($scale * $factor * (1 + $this->noise(0.2)), 2),
                'currency' => 'SAR',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** A few documents that failed checks, so the review queue has something to show. */
    private function writeReviewQueue(array $properties): void
    {
        $cases = [
            [$properties[1], 'manager_flash', '2026-11-20', DocumentStatus::NeedsReview, "Day: occupancy 62.40% does not match rooms occupied / total rooms (71.43 or 72.12).\nAI note: the occupancy line was partly covered by a stamp."],
            [$properties[2], 'trial_balance', '2026-11-19', DocumentStatus::NeedsReview, 'Total debit 1,284,410.50 does not equal total credit 1,284,401.50.'],
            [$properties[4], 'manager_flash', '2026-11-19', DocumentStatus::NeedsReview, 'Report is for hotel [Abha Heights Resort], which is not a known name of any hotel allowed for sender [noreply@nazeel.example]. Add it as an alias if correct.'],
            [$properties[3], 'unknown', null, DocumentStatus::Failed, 'Report type could not be identified.'],
        ];

        foreach ($cases as [$property, $type, $date, $status, $reason]) {
            $id = $this->document($property, $type, $date, null, false, self::SENDERS[array_values(array_filter(self::HOTELS, fn ($h) => $h[2] === $property->code))[0][3]], 'scan_'.Str::random(6).'.pdf');
            DB::table('report_documents')->where('id', $id)->update(['status' => $status->value, 'status_reason' => $reason, 'processed_at' => null]);
        }
    }

    private function document(Property $property, string $type, ?string $date, ?string $periodStart, bool $provisional, string $sender, string $filename): int
    {
        $messageId = DB::table('inbound_messages')->insertGetId([
            'source' => InboundSource::Email->value,
            'external_id' => 'demo-'.Str::uuid(),
            'from_email' => $sender,
            'subject' => match ($type) {
                'manager_flash' => "Manager Flash – {$property->name_ar} – {$date}",
                default => "{$property->name} – {$type} – {$date}",
            },
            'received_at' => $date ? CarbonImmutable::parse($date)->addDay()->setTime(4, 20) : now(),
            'attachment_count' => 1,
            'status' => 'processed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sha = hash('sha256', $property->id.$type.$date.$periodStart.Str::random(8));

        return DB::table('report_documents')->insertGetId([
            'inbound_message_id' => $messageId,
            'group_id' => $property->group_id,
            'property_id' => $property->id,
            'report_type' => $type,
            'period_start' => $periodStart,
            'business_date' => $date,
            'is_provisional' => $provisional,
            'original_filename' => $filename,
            'storage_disk' => 'local',
            'storage_path' => "reports/demo/{$sha}.pdf",
            'mime_type' => 'application/pdf',
            'size_bytes' => mt_rand(40000, 160000),
            'sha256' => $sha,
            'status' => DocumentStatus::Processed->value,
            'processed_at' => $date ? CarbonImmutable::parse($date)->addDay()->setTime(4, 22) : now(),
            'created_at' => $date ? CarbonImmutable::parse($date)->addDay()->setTime(4, 21) : now(),
            'updated_at' => now(),
        ]);
    }
}
