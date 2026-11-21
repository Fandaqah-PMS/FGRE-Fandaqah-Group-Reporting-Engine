<?php

namespace App\Console\Commands;

use App\Mail\MorningFlashMail;
use App\Models\HotelGroup;
use App\Reports\MorningFlashBuilder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

#[Signature('fgre:send-morning-flash {--group= : Only this group id} {--force : Send even if already sent for this date}')]
#[Description('Email each owner / finance user the group Manager Flash for the last business date')]
class SendMorningFlash extends Command
{
    public function handle(MorningFlashBuilder $builder): int
    {
        $groups = HotelGroup::where('is_active', true)->when($this->option('group'), fn ($q, $id) => $q->whereKey($id))->get();
        $sent = 0;

        foreach ($groups as $group) {
            $users = $group->users()->wherePivotIn('role', ['owner', 'finance'])->where('morning_email', true)->get();

            foreach ($users as $user) {
                $user->setRelation('groups', $user->groups()->get());
                $report = $builder->build($group->id, $user->accessiblePropertyIds($group->id));
                if ($report === null) {
                    continue;
                }

                // One email per user, group and business date.
                $key = "fgre:morning-flash:{$group->id}:{$user->id}:{$report['date']}";
                if (! $this->option('force') && Cache::has($key)) {
                    continue;
                }

                Mail::to($user->email)->send(new MorningFlashMail($group, $user, $report));
                Cache::put($key, true, now()->addDays(3));
                $sent++;
            }
        }

        $this->components->info("Morning flash sent to {$sent} user(s).");

        return self::SUCCESS;
    }
}
