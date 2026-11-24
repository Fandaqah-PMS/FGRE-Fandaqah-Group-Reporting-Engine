<?php

namespace App\Console\Commands;

use App\Models\HotelGroup;
use App\Models\User;
use App\Notifications\SetPasswordNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

/**
 * Go-live step: create a hotel group (a customer) and its first owner. The owner gets an
 * invitation email to choose a password, then adds hotels and users in Settings.
 */
#[Signature('fgre:create-group
    {name : Group name in English}
    {--name-ar= : Group name in Arabic}
    {--owner-email= : Email of the group owner}
    {--owner-name= : Full name of the group owner}
    {--currency=SAR : Reporting currency (ISO code)}
    {--timezone=Asia/Riyadh : Timezone for email subscriptions}
    {--locale=ar : Language of the owner (ar or en)}')]
#[Description('Create a hotel group and its owner (the owner receives an invitation email to set a password)')]
class CreateGroup extends Command
{
    public function handle(): int
    {
        $email = mb_strtolower((string) $this->option('owner-email'));
        $ownerName = (string) $this->option('owner-name');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || $ownerName === '') {
            $this->components->error('Give --owner-email and --owner-name.');

            return self::FAILURE;
        }
        if (! in_array($this->option('timezone'), timezone_identifiers_list(), true)) {
            $this->components->error('Unknown timezone.');

            return self::FAILURE;
        }

        $name = (string) $this->argument('name');
        $slug = Str::slug($name);
        if (HotelGroup::where('slug', $slug)->exists()) {
            $this->components->error("A group with the slug [{$slug}] already exists.");

            return self::FAILURE;
        }

        [$group, $owner, $isNew] = DB::transaction(function () use ($name, $slug, $email, $ownerName) {
            $group = HotelGroup::create([
                'name' => $name,
                'name_ar' => $this->option('name-ar') ?: null,
                'slug' => $slug,
                'currency' => strtoupper((string) $this->option('currency')),
                'timezone' => $this->option('timezone'),
            ]);

            $owner = User::where('email', $email)->first();
            $isNew = $owner === null;
            $owner ??= User::create(['name' => $ownerName, 'email' => $email, 'password' => Str::random(48), 'locale' => $this->option('locale') === 'en' ? 'en' : 'ar']);
            $owner->groups()->attach($group->id, ['role' => 'owner', 'all_properties' => true]);
            if ($owner->current_group_id === null) {
                $owner->forceFill(['current_group_id' => $group->id])->save();
            }

            return [$group, $owner, $isNew];
        });

        $this->components->info("Group [{$group->name}] created; owner {$owner->email}.");

        if ($isNew) {
            $token = Password::broker('invites')->createToken($owner);
            $link = url(route('password.reset', ['token' => $token, 'email' => $owner->email, 'invite' => 1], false));
            try {
                $owner->notify(new SetPasswordNotification($token, invitedTo: $owner->locale === 'en' ? $group->name : ($group->name_ar ?: $group->name)));
                $this->components->info('Invitation email sent (link valid for 7 days).');
            } catch (Throwable $e) {
                $this->components->warn('The invitation email could not be sent: '.$e->getMessage());
            }
            $this->line("Set-password link (share only with the owner): {$link}");
        } else {
            $this->components->info('The owner already had an account and can sign in with it.');
        }

        return self::SUCCESS;
    }
}
