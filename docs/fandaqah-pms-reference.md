# Fandaqah PMS reference for FGRE

Status: v1, 2026-11-21. Written from the Fandaqah PMS source (branch `feature/reports-dashboards`, commit `a5bb276912`) in answer to [prompts/fandaqah-pms-discovery-prompt.md](prompts/fandaqah-pms-discovery-prompt.md).

How to read it:

- Every path is relative to the **Fandaqah PMS repository root**, unless it starts with `docs/samples/` (this repo).
- "Not present" means the code was searched and the thing does not exist.
- Where the PMS does something in more than one way, the dominant way is described first and the exceptions after it.
- No secrets, `.env` values or real guest data are included. The sample reports in `docs/samples/fandaqah-pms/` were rendered from the PMS's local demo hotel (§9.2).

## Summary for FGRE

| Question | Short answer |
|---|---|
| Stack | PHP 7.2, Laravel **5.8.33**, Laravel Spark Aurelius (teams, billing), Laravel Nova 2 as the main UI, Vue 2 Options API, MySQL 8.4, Redis, SQS, S3. |
| Tenancy | Custom. **One database, one schema, a `team_id` column on every tenant table.** A Spark *Team* is **one hotel**. No tenancy package. |
| Hotel groups | **No group or company entity.** A "group" is the set of hotels a user belongs to (`team_users`). The PMS's own group reports and group dashboard work this way. |
| Current hotel | `users.current_team_id` of the logged-in user. Switched with `GET /settings/hotel/{id}/switch`. No subdomain, header or path. |
| Roles | `pktharindu/nova-permissions`. Roles are created per hotel. Permission slugs are lowercase words with spaces, e.g. `rpt report manager-flash`. 849 permissions in 72 groups. |
| Property code | **Not present.** Use `teams.id`; also available are `teams.name` (Arabic), `teams.en_name`, `teams.slug`, `teams.mot_license_number`. |
| Manager Flash | Report `manager-flash` (EOD-016), Opera-style, Day / Month / Year for this year and last year, 100 rows. Emailed as a PDF after each Night Audit close, but **only for hotels on Night Audit v2 that switched the mail on**. |
| Trial Balance | Three reports carry that name: `trial-balance` (FIN-040, front-office ledger roll-forward), `fin-trial-balance` (FST-001, general-ledger TB), `fin-acc-period-trial-balance` (FAC-001, accounting period TB). **None of them is emailed.** |
| Structured data | Yes, every report runs as JSON through `POST /nova-vendor/pms-reports/reports/{key}/run`, but that API needs a **Nova browser session**; there is no token API for it. |
| Email subject / file | `Manager Flash – {hotel name} – {Y-m-d}`, attachment `manager_flash_{Ymd}.pdf`. Business date: yes. Property code: no. |
| MCP server | **Not present in this repository.** |
| Brand | Navy `#262E43`, coral `#F15F5A`, off-white `#F8F7F4`; fonts El Messiri / Montserrat / Almarai. The new dashboards use them; the rest of the PMS still uses the older blue Nova theme. No dark mode. |

---

## 1. Stack and versions

### 1.1 Backend

Versions are what `composer.lock` installs; constraints are from `composer.json`.

| Package | Installed | Notes |
|---|---|---|
| PHP | 7.2 (`"php": "^7.1.3"`, no `config.platform`) | `CLAUDE.md`: "PHP 7.2 — no PHP 8+ syntax". The lock pins PHP < 7.4 through `pusher-php-server` and `mpdf`. |
| laravel/framework | **v5.8.33** (pinned) | |
| laravel/spark-aurelius | dev-master from path repo `./spark`, `Spark::$version = '8.1.2'` | Teams, billing, API tokens. Teams are renamed "hotel" (`Spark::prefixTeamsAs('hotel')`). |
| laravel/nova | dev-master from path repo `./nova` (`nova/composer.json` says 2.9.4) | Main UI, served at `/home` (`config/nova.php` `path`). |
| laravel/passport | v7.4.0 | OAuth server (§3.6). |
| laravel/cashier | v9.3.5 | Stripe, through Spark. |
| laravel/telescope | v2.1 | |
| spatie/laravel-activitylog | 3.7.1 | Audit log (§4.11). |
| pktharindu/nova-permissions | v1.1.0 | Roles and permissions (§3.3). |
| bavix/laravel-wallet | 3.2.0 | Reservation and team wallets. |
| maatwebsite/excel | 3.1.15 | Import / export. |
| mpdf/mpdf | v7.1.9 | Report PDFs (§5.6). |
| barryvdh/laravel-dompdf, niklasravnsborg/laravel-pdf | v0.8.5, v3.1.0 | Older PDFs (invoices, prints). |
| predis/predis, pusher/pusher-php-server | v1.1.1, v4.0.0 | |
| vinkla/hashids | 6.0.0 | Public ids. |
| phpunit/phpunit | 7.5.15 | Tests (§4.13). |

**Not present:** Sanctum, Horizon, Octane, Livewire, Inertia, Filament, Socialite, Scout.

Internal code: about 37 local Composer path packages under `nova-components/*` (namespace `Surelab\<Module>`, one per Nova module), plus `packages/shomoos-transaction-wallet`, `packages/fandaqah/queuehealthchecker` and `packages/php-mqtt/client`.

### 1.2 Frontend

- **Main UI is Laravel Nova (a Vue 2 single-page app) inside a Blade shell**, `resources/views/vendor/nova/layout.blade.php`. Each feature area is a Nova tool package in `nova-components/<Module>` with its own Vue bundle in `dist/` (committed).
- Spark's own pages (billing, profile, API tokens) are Blade + Vue with **Bootstrap 4.3.1**.
- Printed documents, PDFs and e-mails are plain Blade (`resources/views/print`, `pdf`, `email`).
- A separate Nuxt 2 SPA ("corneer", `nuxt.config.js`, `srcDir: 'corneer/'`) is the public hotel booking website.
- Versions: Vue **2.6.10**, vue-router 3.0.7, vuex 4.1.0, axios 0.18.1, laravel-echo 1.5.4, pusher-js 5.0.0, jQuery 3.4.1, vue-i18n 7.8.1.
- CSS: Nova's own Tailwind (**0.6**, `nova/tailwind.js`, colours bound to CSS variables), a prebuilt **Tailwind 2.2.19** file (`public/tailwind.min.css`, loaded by the layout), Bootstrap 4 for Spark. There is no project `tailwind.config.js`.
- Icons: Font Awesome 4.7 / 4.4 (CDN in the layout) and Font Awesome 5, Google Material Icons, inline SVG.
- Charts: **hand-written SVG Vue components** on the dashboards (§8.4). `chart.js` 2.8 / `vue-chartjs` are declared but not imported anywhere. No ApexCharts, ECharts or Highcharts.
- Build: **Laravel Mix 4.0.16 / webpack 4** (`webpack.mix.js`), Sass (node-sass 4.12) and `rtlcss` for the RTL bundle. Not Vite.
- Node: no `.nvmrc` or `engines`. Module bundles are built with **Node 10.24.1** (`scripts/rnr/build-nova-module.sh`: `NODE_BIN="${RNR_NODE:-$HOME/.nvm/versions/node/v10.24.1/bin/node}"`); local development uses Node 12.

### 1.3 Data and infrastructure

| Item | Value | Source |
|---|---|---|
| Database | **MySQL 8.4** (`docker-compose.yml`: `image: mysql:8.4.3`, utf8mb4_unicode_ci, `TZ: "Asia/Riyadh"`) | `docker-compose.yml`, `docker/mysql/conf.d/my.cnf` |
| Connections | `mysql` (read/write split: `DB_HOST_READ` for reads), **`mysql_readonly`** (read replica, used by every report in production), plus stock `sqlite`, `pgsql`, `sqlsrv` | `config/database.php` |
| Report reads | `'connection' => env('PMS_REPORTS_CONNECTION', env('APP_ENV') === 'production' \|\| env('READONLY_DB_HOST') ? 'mysql_readonly' : null)` | `config/pms_reports.php:18` |
| Cache | Redis (`CACHE_DRIVER` default `redis`, predis client) | `config/cache.php:21` |
| Queue | **SQS** by default (`QUEUE_CONNECTION` default `sqs`); named queues such as `night-audit`, `reports`. Workers run under Supervisor (`supervisor:restart` command). | `config/queue.php:16` |
| Session | `database`, lifetime 7 days | `config/session.php` |
| Files | **S3** by default (`FILESYSTEM_DRIVER` default `s3`), plus `local` / `public` | `config/filesystems.php` |
| Mail | `MAIL_DRIVER` (default `log`), one global `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` | `config/mail.php` |
| Broadcast | Pusher (default `null`) | `config/broadcasting.php` |
| Logs | `stack` → `daily`; a Monolog processor adds `team_id` (§2.5) | `config/logging.php` |

### 1.4 Hosting and deployment

- **Laravel Forge** is in use: `app/Console/Commands/WebsiteCreate.php` creates hotel website sites through `themsaid/forge-sdk`. Vapor and Kubernetes: not present.
- Docker is only used for a local MySQL (`docker-compose.yml`: "The app itself runs natively").
- Deploy scripts (`deploy_development.sh`, `deploy_production.sh`) only build the Nova and module front-end bundles; they do not run Composer or migrations.
- CI (`.github/workflows/`): no test or lint pipeline. `pull-request.yml` force-resets a staging branch on a self-hosted runner; `sync-knowledge-openai.yml` uploads AI knowledge files on push to `staging` / `master`.
- Environments: **local**, **staging** (branch `staging`) and **production** (branch `master`); code also checks `development`. The owner mobile app points at `api-dev.fandaqah.com` (staging) and `api.fandaqah.com` (production) (`mobile/owner-app/eas.json`).
- A sister service, **fandaqah_api** (separate repository), serves the Owner App API and some operational reports from the same MySQL schema (read-only DB user `owner_ro`, `docs/owner-app/owner_ro_grants.sql`).

---

## 2. Multi-tenancy

### 2.1 Approach

- **Custom, built on Spark Teams. One Team = one hotel (property).** No tenancy package: `stancl/tenancy`, `spatie/laravel-multitenancy`, `tenancy/tenancy` and `hyn/multi-tenant` are all absent from `composer.lock`.
- **There is no `config/tenancy.php` or `config/spark.php`.** The equivalent configuration is code in `app/Providers/SparkServiceProvider.php`:

```php
// boot()
Spark::useStripe()->noCardUpFront()->teamTrialDays(15);
Spark::teamPlan('Basic', 'team-basic')->price(1)->yearly();
Spark::teamPlan('Fre', 'team-free');
Spark::useDefaultRole('member');
// register()
Spark::prefixTeamsAs('hotel');
Spark::ensureEmailIsVerified();
```

- `App\Team` and `App\Hotel` are two models over the same `teams` table.

### 2.2 Isolation model

- **Single database, single schema, `team_id` column** on every tenant table (about 230 migrations add one). There is no schema-per-tenant and no database-per-tenant; `config/database.php` has no per-tenant connection.
- Central (landlord) tables, with no `team_id` or not team-scoped:
  - identity: `users`, `teams`, `team_users` (`team_id`, `user_id`, `role` = `owner` | `member`, unique pair), `roles`, `role_user`, `role_permission`, `password_resets`, `device_logins`, the Passport `oauth_*` tables, `api_tokens`, `public_api_consumers`;
  - catalogues: `countries`, `cities`, `id_types`, `integration_settings`, `plans`, `feature_announcements`.
- Tenant tables carry `team_id`: reservations, units, customers, transactions, settings, every module table (`pur_*`, `acc_*`, `na_*`, `rpt_*`, `hr_*`, `crm_*`, …).
- `teams`-owned but managed centrally: `team_subscriptions`, `team_counters`, `settings` (per-hotel key/value rows), `integrations`.
- Rows with `team_id IS NULL` are **shared system rows** that every hotel sees (e.g. the system "Room" services category), because `TeamScope` adds `orWhereNull('team_id')` (§2.7).

### 2.3 How the current tenant is identified

**By the logged-in user only.** The current hotel is the column `users.current_team_id`, read as `auth()->user()->current_team_id` in every scope and trait.

- No subdomain, custom domain, path or header identifies the tenant in the PMS UI. The `{account}.MAIN_DOMAIN` route domain is commented out (`routes/api.php` ~136, ~179); `teams.slug` and `teams.private_domain` only serve the public booking website.
- Switching hotels: Spark route `GET /settings/hotel/{team}/switch` → `TeamController@switchCurrentTeam`:

```php
abort_unless($request->user()->onTeam($team), 404);
$request->user()->switchToTeam($team);   // CanJoinTeams: $this->current_team_id = $team->id; $this->save();
return back();
```

  The choice is **stored on the user row**, so it applies to every session and tab of that user. The switcher is the "Hotels" list in the Nova user menu (`resources/views/vendor/nova/partials/user.blade.php:33-63`).
- Middleware (`app/Http/Kernel.php`):
  - `hasTeam` = Spark `VerifyUserHasTeam`: applied to all of `routes/web.php` (`RouteServiceProvider.php:59`) and to Nova (`config/nova.php` middleware). Redirects to `/settings/hotel/missing` when the user has no team.
  - `hasManageTeam` (`app/Http/Middleware/HasManageTeam.php`): on the mobile API, checks a `current_team_id` request parameter against the user's teams:

    ```php
    if (!auth()->user()->teams->contains($request->current_team_id)) {
        throw new ValidationException("this user cant manage this team", 490);
    }
    ```

  - `fetchTeam` (`FetchCurrentTeamFromUrl`): public booking website only; reads an `x-team` header (id or slug).
  - **There is no general tenant-finder middleware.** The scope reads the user directly.

### 2.4 Hierarchy: groups of hotels

- **Team = one hotel.** There is **no group, chain, company or organisation entity** above Team, no `groups` table and no parent column on `teams`.
- **A group is the set of hotels a user is a member of** (`team_users`). This is exactly how the PMS's own multi-hotel features work:
  - `app/Services/Reporting/Hub/TeamPermissions.php::teamsWith()`:

    ```php
    $teamIds = DB::table('team_users')->where('user_id', (int) $user->id)->distinct()->pluck('team_id')->all();
    return DB::table('teams')->whereIn('id', $teamIds)->whereNull('deleted_at')->get(['id', 'name', 'en_name'])
    ```

  - Group reports (`app/Services/Reporting/Group/GroupReportService.php`): Manager Flash, Housekeeping, Purchasing and Maintenance with one row per hotel. Comparison charts appear from 2 hotels (`COMPARE_HOTELS = 2`).
  - Group Performance Dashboard (`app/Services/Reporting/Group/GroupDashboardService.php`): "the head office's comparison of its hotels".
- `teams.owner_id` is the hotel's owner (one user). The per-hotel `owner_app` role controls what the owner sees in the mobile Owner App (§3.4). It is not a level above Team.
- `related_teams` (`team_id`, `related_id`) links a main hotel to "attached hotels" **for the public booking website only** (`Team::attachedHotels()`, used by `Api/Corneer/UnitController.php`). It is managed from the control panel and is not used for reporting or permissions.
- `CompanyGroup` (`company_groups`) is a group of corporate *customers*, not of hotels.

**Hotel identity fields** (all on `teams` unless noted):

| Field | Column / source | Notes |
|---|---|---|
| Property id | `teams.id` (unsigned int, auto-increment) | Shown in the PMS header as "(ID: n)". The only stable unique key. |
| Name | `teams.name` (usually Arabic), `teams.en_name` | Reports print `en_name` in English and `name` in Arabic. |
| Code | **not present** | `teams.slug` (unique, website use) is the nearest. `hotel_code` exists only inside a channel-manager payload. |
| Tourism licence | `teams.mot_license_number` | |
| VAT / CR numbers | per-hotel `settings` rows (`tax_number`, `commercial_register`) and `teams.vat_id`; read by `App\Services\Inventory\HotelHeader` | |
| Address | `teams.building_number`, `street(_ar)`, `district(_ar)`, `city(_ar)`, `postal_code`, `national_address`, plus settings rows | |
| Currency | `teams.currency` (default `SAR`) | |
| Logo | `teams.photo_url` | |
| Timezone | **not a column**; settings row `night_audit_timezone`, fallback `Asia/Riyadh` (§4.6) | |
| Business date | `teams.business_date` (Night Audit hotels), `teams.night_audit_cutoff_time`, `night_audit_auto_run_time`, `night_audit_auto_enabled`, `last_night_audit_at` | §4.6 |

### 2.5 Tenant context outside an HTTP request

No context is propagated automatically. When nobody is logged in, **`TeamScope` adds no filter** and the creating hooks do nothing, so background code always passes the team explicitly.

- **Queued jobs** take the team id (or a model carrying it) in the constructor, e.g. `SeedTeamDefaultSettings(Team $team)`, `Crm/BuildCampaignRecipientsJob($teamId, $campaignId)`, `RateManagement/RecomputeRatesJob`, `Reporting/Etl/Jobs/LoadDayJob`. Inside, they bypass the scope deliberately:

```php
// app/Jobs/Crm/BuildCampaignRecipientsJob.php
// No logged-in user here: everything is looked up with the explicit team id (documented team scope bypass).
$campaign = Campaign::withoutGlobalScope(TeamScope::class)->where('team_id', $this->teamId)->find($this->campaignId);
```

  Module base models expose the same as a helper (`app/Hrm/HrModel.php:37`, `app/Accounting/SubledgerModel.php:66`):

```php
return $query->withoutGlobalScope(TeamScope::class)->where($this->getTable() . '.team_id', $teamId);
```

  Rule (`docs/crm-hrm/ARCHITECTURE.md` §2): the scope is bypassed in exactly three documented situations (jobs and console commands, public token webhooks, the BioTime sync), always with `withoutGlobalScope(TeamScope::class)` plus an explicit `where('team_id', …)`.
- **Scheduled commands** (`app/Console/Kernel.php`) loop over teams and dispatch one job per team, e.g. `fandaqah:occupancy-update` (07:00, every non-deleted team), `housekeeping:generate-tasks`, `hrm:close-attendance-days` ("TeamScope bypass (no user on the scheduler), explicit team_id"), `night-audit:dispatch` (every 5 minutes, teams with `night_audit_v2_enabled = 1`).
- **Broadcast channels** (`routes/channels.php`): `team.{teamId}.rooms` is authorised when `(int) $user->current_team_id === (int) $teamId`; `team.{teamId}.attendance` also needs `hr view attendance`. New modules do not use broadcasting (Nova pages do not boot Echo); they poll.
- **Cache keys** include the team id, e.g. `"rpt:opt:{$teamId}:{$name}:{$locale}"`, `'dash:main:gen:' . $teamId`, `'control-flag:' . $teamId . ':' . $key`.
- **File paths** include the team id in new code: `'purchasing/' . $teamId . '/invoices/…'`, `'hrm/' . $teamId . '/employees/…'`, `'accounting/' . $teamId . '/expenses/…'`, `storage/app/rpt-exports/{team}/{uuid}.{ext}`, `storage/app/na-report-mails/{team}/`.
- **Logs:** `activity_log.team_id` is filled from the user or, in module models, from the model (`tapActivity`). `app/Logging/UserIdProcessor.php` adds `extra.team_id` to every Monolog record when a user is logged in.

### 2.6 Creating, migrating, suspending and deleting a hotel

**Creation.** Hotels are created from the **control panel** (a separate Laravel/Nova admin application, "control") or Spark's `POST /settings/hotel`. Creating a `Team` fires:

1. `Team::boot()` → `static::created` (`app/Team.php:116-186`): creates the hotel's roles and grants their permissions (§9.3), sets a 15-day trial.
2. `App\Observers\TeamObserver::created` (registered in `AppServiceProvider`):

   ```php
   public function created(Team $team) {
       SeedTeamDefaultSettings::dispatch($team);
       DefaultNotificationControlSettings::dispatch($team);
   }
   ```

   `App\Jobs\SeedTeamDefaultSettings` writes everything with an explicit `team_id`: module switches (`createModuleSwitches`), a first room category and unit 101, features, default settings (`day_start` 13:00, `day_end` 12:00), terms, sources, highlights, a `team_counters` row, a 15-day trial subscription, website settings, default services.

Control can re-run the seeding through `PUT /api/seed-team/{teamId}` (bearer key, `control.authenticate` middleware).

**Migrations and seeders.** One shared migration set (`database/migrations`, about 694 files), run once with `php artisan migrate`. No per-tenant migrations. New permissions reach existing hotels through **idempotent grant migrations** that insert into `role_permission` for every hotel's `admin` role (e.g. `2026_10_22_100020_grant_pms_reports_permissions.php`). Per-hotel defaults come from the seeding job above, not from database seeders.

**Suspension.** No status / active / suspended column on `teams` and no suspension middleware. `check:team-subscriptions` (every 5 minutes) sets `team_subscriptions.is_active = 0` seven days after expiry, but the checks that would block access are commented out (e.g. `nova-components/Calender/src/Http/Middleware/Authorize.php:23`). Users have `users.is_suspended`, which blocks login. Modules are switched off per hotel by control flags (§2.8).

**Deletion.** `Team` uses `SoftDeletes`. `static::deleted` dispatches `App\Jobs\TeamDeleted`, which soft-deletes the hotel's main relations (reservations, units, customers, …) and the owner if they have no other hotel; `TeamRestored` reverses it. Deleted hotels keep their `team_users` / `role_user` rows, so code that lists a user's hotels filters `teams.deleted_at IS NULL`.

### 2.7 Code that enforces isolation

Three mechanisms exist. **New code uses `BelongsToCurrentTeam`; FGRE should copy that one.**

**`App\Scopes\TeamScope`** (full file, `app/Scopes/TeamScope.php`):

```php
<?php

namespace App\Scopes;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Unit;
class TeamScope implements Scope {
    public function apply(Builder $builder, Model $model) {
        if (\Auth::check()) {
            $builder->where(function ($q) {
                $q->where('team_id', auth()->user()->current_team_id)
                  ->orWhereNull('team_id');
            });
        }
    }
}
```

**`App\Traits\BelongsToCurrentTeam`** (full file; used by about 50 classes, including the base models of POS Pro, Purchasing, HRM, Events, Rate Management and the accounting subledgers, which have about 106 subclasses):

```php
<?php

namespace App\Traits;

use App\Scopes\TeamScope;

/**
 * TeamScope plus team_id / created_by defaults on create.
 *
 * Unlike HasTeam, the team always comes from the authenticated user (never from a request
 * parameter), and the hooks are registered through the boot{Trait} convention so the model
 * keeps its own boot() free. Models whose table has a created_by column set
 * `protected $tracksCreator = true;`.
 */
trait BelongsToCurrentTeam
{
    public static function bootBelongsToCurrentTeam()
    {
        static::addGlobalScope(new TeamScope());

        static::creating(function ($model) {
            if (!auth()->check()) {
                return;
            }

            if (empty($model->team_id)) {
                $model->team_id = auth()->user()->current_team_id;
            }

            if (!empty($model->tracksCreator) && empty($model->created_by)) {
                $model->created_by = auth()->id();
            }
        });
    }
}
```

A module base model then looks like this (`app/Purchasing/PurchasingModel.php`):

```php
abstract class PurchasingModel extends Model
{
    use BelongsToCurrentTeam, LogsActivity;
    protected $guarded = ['id', 'team_id', 'created_by'];
    protected static $logName = 'purchasing';
    // ...
}
```

**`App\Traits\HasTeam`** (legacy, **do not copy**):

```php
trait HasTeam
{
    protected static function boot()
    {
        if (\Auth::check()) {
            $team_id = (auth()->user()->current_team_id) ? auth()->user()->current_team_id : 0;
            $team_id = request()->get('current_team_id', $team_id);
            static::creating(function ($query) use($team_id ){
                if (empty($query->team_id))
                    $query->team_id = $team_id;
            });
            static::addGlobalScope('team_id', function (Builder $builder) use($team_id ){
                $builder->where('team_id', '=', $team_id);
            });
        }
        parent::boot();
    }
}
```

Why not to copy it: it captures the team once when the model class boots, it lets a request parameter choose the team, and because it defines `boot()`, any model with its own `boot()` silently replaces it (24 of the 36 models that use it do, and most of them add `TeamScope` by hand instead).

Other legacy variants: about 46 models call `static::addGlobalScope(new TeamScope())` in their own `boot()` (e.g. `app/Reservation.php:122`); some legacy tables (customers, transactions, …) have no global scope and are filtered by hand with `where('team_id', auth()->user()->current_team_id)`.

Binding rule (`CLAUDE.md`): "Never bypass `TeamScope` without intentional cross-team queries (e.g., admin reports)."

### 2.8 Per-hotel module switches ("control flags")

The control panel turns modules on and off per hotel by writing `settings` rows (`team_id`, `key`, `value`). Code reads them through `App\Services\ControlFlags` with an explicit team id:

```php
// app/Services/ControlFlags.php
// Per-hotel module switches set from the control panel (control repo, Nova Hotel resource), each a
// `settings` row whose value is '1' when the module is on. Read with DB::table and an explicit team id.
public static function enabled($teamId, $key) { /* last row by id wins; memoised per request */ }
public static function enabledForUser($user, $key) { return static::enabled($user->current_team_id, $key); }
```

- Off unless a row says `'1'`: `night_audit_v2_enabled`, `pms_reports_enabled`, `fm_v2_enabled`, `RateManagementEnabled`.
- On unless a row says `'0'` (`DEFAULT_ON`): `housekeeping_enabled`, `maintenance_enabled`, `inventory_enabled`, `purchasing_enabled`, `crm_enabled`, `hrm_enabled`, `pos_v2_enabled`, `banquet_enabled`, `fandi_enabled`, `academy_enabled`, `pos_v1_enabled`, `reports_v1_enabled`.
- A switched-off module's Nova tool is hidden, so its `Authorize` middleware returns 403 on its routes (`NovaServiceProvider::moduleSwitch`).

---

## 3. Users, authentication and authorization

### 3.1 Users and hotels

- One user model, **`App\User extends Laravel\Spark\User`** (`app/User.php`), traits `HasRoles` (pktharindu), `CanJoinTeams` (Spark), `HasApiTokens` (Passport), `LogsActivity`, `SoftDeletes`. **Users are central** (`users` table, no `team_id`).
- **A user can belong to several hotels** through `team_users` (`role` = `owner` | `member`). `User::teams()` (Spark) and `User::hotels()` (`belongsToMany(Hotel::class, 'team_users', 'user_id', 'team_id')->withPivot('role')`).
- The current hotel is `users.current_team_id` (§2.3). Falls back to the first team when null.
- When a logged-in user creates another user, `User::boot` adds the new user to the creator's current hotel as `member` with the `Staff` role.
- The only other authenticatable model is `App\PublicApiConsumer` (table `public_api_consumers`, Passport `consumer` guard) for the public API. Guests, customers and employees do not log in.

### 3.2 Authentication

| Item | Implementation | Source |
|---|---|---|
| Guards | `web` (session, default), `api` (Spark token guard), `passport` (Passport, users), `consumer` (Passport, `public_api_consumers`) | `config/auth.php` |
| Browser login | Session. `POST /auth/login` → `Auth\LoginController::loginAuth`: Cloudflare Turnstile captcha, `is_suspended` check, password check, in production **one device per user** (`device_logins`) unless `users.is_multi_session`; Laravel `ThrottlesLogins` (5 per minute) | `app/Http/Controllers/Auth/LoginController.php` |
| Nova | `/home`, Nova's login and password-reset routes | `NovaServiceProvider.php:63-66` |
| Mobile API | Passport personal access tokens (`$user->createToken('myApp')`) on `auth:passport` routes; `POST api/login` | `routes/api.php:41-60` |
| Spark API tokens | `api_tokens` table, `/settings/api/token`, `?api_token=` or bearer on `auth:api`; plus a 5-minute signed `spark_token` cookie for the SPA | `spark/src/TokenGuard.php` |
| 2FA | Spark's Authy 2FA code is present but **not enabled** (`$usesTwoFactorAuth` is not set) | `app/Providers/SparkServiceProvider.php` |
| SSO | **Not present** (no Socialite, SAML, Google or Microsoft login) | `composer.json` |
| Password rules | Minimum 8 characters (Nova user form, registration); Spark's password change uses Spark's minimum of 6. No complexity, expiry or history rules. | `app/Nova/User.php:136-139` |
| E-mail verification | `Spark::ensureEmailIsVerified()`; non-admin users with unverified e-mail/phone are blocked after `verification_expiry_at` | `HomeController::checkUserNeedsVerification` |

### 3.3 Roles and permissions

**Package:** `pktharindu/nova-permissions` v1.1. Tables `roles(id, slug, name, team_id, deletable)`, `role_permission(role_id, permission_slug)`, `role_user(role_id, user_id)`. There is no permissions table: each permission is a Gate ability defined from config.

**Single source of truth:** `config/novapermissions.php`, key `permissions`. **849 permissions in 72 groups.** Each entry:

```php
'pur approve orders' => [
    'display_name' => 'Approve purchase orders',
    'description'  => '...',
    'group'        => 'Purchasing',
],
```

`AuthServiceProvider` registers one Gate ability per entry:

```php
Gate::define($key, function (User $user) use ($key) {
    if ($this->nobodyHasAccess($key)) { return true; }   // a permission no role holds is open to everyone
    return $user->hasPermissionTo($key);
});
```

There is no `Gate::before` (no super-user bypass).

**Naming convention:** lowercase words separated by spaces. Legacy slugs are verb-first (`view reservations`, `edit users`, `view financial`); new modules use a module prefix:

| Prefix | Count | Examples |
|---|---|---|
| `rpt report <report-key>` | 409 | `rpt report manager-flash`, `rpt report trial-balance` (one per report) |
| `rpt view <category>` | 16 | `rpt view end of day`, `rpt view financial` |
| `rpt dashboard <key>` | 12 | `rpt dashboard flash` |
| `rpt export`, `rpt export pii`, `rpt manage etl` | 3 | |
| `acc …` | 43 | `acc view`, `acc reports view`, `acc bank manage` |
| `pos v2 …` | 22 | `pos v2 take orders` |
| `crm …`, `hr …`, `pur …`, `banquet …`, `inv …`, `hk …`, `ma …`, `rm …` | 9–16 each | `pur manage orders`, `hk view reports`, `rm edit rates` |
| `night audit …` | 17 | `night audit run`, `night audit force run` |
| `owner app …` | 14 | `owner app finance`, `owner app reports` |

A few legacy keys break the pattern (snake_case `new_reservation_notification`, dotted `revenue.adjustment`).

**Roles are per hotel** (`roles.team_id`; `App\Role` scopes to the current team). Every role and where it is created:

| Role slug | Created by | Permissions |
|---|---|---|
| `admin` (Admin, not deletable, not editable) | `Team::boot` `created` | every permission except `owner app *`; given to `teams.owner_id` |
| `staff` (Staff, not deletable) | `Team::boot` `created` | everything except `view settings`, `view reports`, `view financial`, Shomoos purchases/invoices, the operations-only and owner-app permissions |
| `housekeeping` (not deletable) | `Team::boot` `created`, extended by `OperationsRoles::seedForTeam` | `view reservations`, `hk view board`, `hk perform tasks`, `ma create work orders` |
| `housekeeping-supervisor`, `maintenance-supervisor`, `technician`, `storekeeper` | `App\Services\Operations\OperationsRoles::seedForTeam` | per `OperationsRoles.php` |
| `owner_app` (not deletable, editable) | `App\Services\Owner\OwnerAppRole::seedForTeam` (also migration `2026_10_22_100000_create_owner_app_roles.php` for existing hotels) | the 14 `owner app *` permissions; given to `team_users.role = 'owner'` users and `teams.owner_id` |
| `reception`, `acc_br`, `acc_ho`, `hotel_manager`, `service`, `sales`, `reservation`, `hk`, `maintenance`, `pos` | `php artisan import:roles` (manual, per hotel) | hard-coded lists in `NewRolesCommand.php` |
| any custom role | the hotel's Roles & permissions screen (`RolePermissionService::create`) | chosen by the hotel |

`RolePermissionService::SYSTEM_SLUGS = ['admin', 'housekeeping']` cannot be deleted; `LOCKED_SLUGS = ['admin']` cannot be edited. There is **no super-admin role inside the PMS**; platform administration is the separate control panel.

**How a check is scoped to a hotel.** This is the most important rule to copy correctly:

- `HasRoles::hasPermissionTo()` walks **all the user's roles in every hotel**. A permission a user holds in hotel A also counts in hotel B.
- What limits access is **hotel membership** (`team_users`) and **data scoping** (`TeamScope`): a user can only act in hotels they belong to, and only sees the current hotel's rows.
- This is deliberate and written down: `app/Services/Purchasing/PurchasingPermissions.php` ("a permission is held when any role of the user grants it, whatever team the role belongs to", decision W2-27 amended 2026-11-10), `app/Services/Reporting/Engine/ReportPermissions.php` ("a role of the user in any hotel counts, as long as the user is a member of the hotel").
- Each new module funnels checks through one class (`PurchasingPermissions`, `AccPermissions`, `NightAuditPermissions`, `ReportPermissions`, …) that loads the user's slugs once per request.

**Where checks happen:**

1. Module `Authorize` middleware: the module's Nova tool `canSee` (control flag plus "may open the module").
2. FormRequest `authorize()` with a `$permission` property (§4.2).
3. Controller `authorizePermission('pur view')` → `abort(403)`.
4. Services re-check business rules (self-approval, another user's draft, …).
5. API Resources return `can` flags so the UI hides what the server would refuse. Rule (X-D12): "Servers enforce permissions; the UI only reflects them."

Policies mapped in `AuthServiceProvider`: `Team`, `Reservation`, `Role`, `User`, `Customer`, `Term`, `OnlineReservation`. New modules do not use policies.

### 3.4 Owner and group-level access

- **No group-level role.** The `owner_app` role is per hotel and only controls the mobile Owner App screens.
- **What a multi-hotel user sees:** every non-deleted hotel in their `team_users`, for each group report / dashboard whose permission they hold through any of their roles:

| Group view | Permission | Code |
|---|---|---|
| Manager Flash (one row per hotel, Day columns) | `rpt view end of day` | `ManagerFlashGroupReport` |
| Housekeeping / Purchasing / Maintenance | `hk view reports` / `pur view reports` / `ma view reports` | `GroupReportService::REPORTS` |
| Reports Plus dashboards (summed over hotels) | `rpt dashboard <key>` | `DashboardService` |
| Group Performance Dashboard (day / MTD / YTD per hotel and for the group, USALI statistics, rankings, next 7 nights on the books) | `rpt view end of day` (same hotels as the Manager Flash group report) | `GroupDashboardService`, `GET /nova-vendor/pms-reports/group-dashboard` (+ `/export` CSV) |

Ratios over several hotels are recomputed from summed parts, never averaged (`CLAUDE.md`, "Reports and dashboards").

### 3.5 Sharing a login with FGRE

What exists today:

1. **Passport OAuth 2 server, authorization-code flow already wired.** Passport's token routes are registered (`AuthServiceProvider.php:68-74`: access tokens, transient tokens, clients, personal access tokens). The authorize screen is mounted at **`/authorize`** (not `/oauth/authorize`) behind the session (`routes/web.php:327-331`). FGRE could be registered as an OAuth client (`oauth_clients`) and get a token for the logged-in PMS user. The token is accepted by the `passport` guard (`auth:passport` routes).
2. **Passport personal access tokens** and **Spark API tokens**: per-user, long-lived tokens.
3. **Shared database:** fandaqah_api already reads `users`, `teams`, `team_users`, `roles`, `role_user` and `role_permission` with a read-only DB user. FGRE could read hotel membership and permissions the same way.
4. **Not present:** SSO, SAML, a signed-login URL, or any cross-app session sharing.

Constraint: the Reports Plus API (§5.5) is behind Nova's **session** middleware, so a Passport token does **not** open it today. Pulling reports server-to-server needs a new token-protected endpoint in the PMS (see §7).

---

## 4. Coding conventions and rules

### 4.1 Folder structure under `app/`

- **Models are in the root `App\` namespace** (legacy, e.g. `App\Reservation`) or in `App\<Module>\` (new, e.g. `App\Purchasing\PurchaseOrder`). There is no `app/Models`.
- **Business logic lives in `app/Services/<Module>/*Service.php`.** Controllers stay thin (`CLAUDE.md`).
- New-module controllers live in the module package: `nova-components/<Module>/src/Http/Controllers/` (namespace `Surelab\<Module>\Http\Controllers`), routes in `nova-components/<Module>/routes/api.php`, prefix `/nova-vendor/<kebab-module>`, middleware `['nova', Authorize::class]`.
- FormRequests: `app/Http/Requests/<Module>/`. API Resources: `app/Http/Resources/<Module>/`.
- Other folders: `Observers/` (side effects), `Events/` + `Listeners/` (past-tense events, e.g. `ReservationCreated`), `Jobs/<Module>/`, `Console/Commands/<Module>/`, `Exports/` + `Imports/` (Maatwebsite), `Integration/` (government and provider clients), `Rules/` (e.g. `SaudiVatNumber`), `Scopes/`, `Traits/`, `Support/`, `Mail/`, `Notifications/`, `Nova/` (Nova resources).
- **Not present:** `Actions/` (apart from Nova actions), `Repositories/`, `DTOs/`, `Enums/`. Statuses are class constants (`PurchaseOrder::STATUS_DRAFT`) with a static `statuses()` list.

One feature end to end is in §9.4.

### 4.2 The module pattern (dominant)

Route → thin controller → FormRequest (validation + permission) → Service (business rules, transaction, throws `<Module>Exception`) → Model (team-scoped) → API Resource. Business-rule failures become HTTP 422 in one place:

```php
// nova-components/Purchasing/src/Http/Controllers/Controller.php
protected function attempt(callable $callback)
{
    try {
        return $callback();
    } catch (PurchasingException $e) {
        return response()->json(['message' => $e->getMessage()], 422);
    }
}
```

Create endpoints accept an **`idempotency_key`** (max 64 characters, generated by the browser) backed by a unique `(team_id, idempotency_key)` index; a repeated key returns the existing row (`app/Services/Purchasing/Concerns/CreatesOnce.php`).

Exception: the Financial Management module mixes in the older `{success, message, data}` envelope (`app/Helpers/ResponseHelper.php`).

### 4.3 Naming

| Thing | Convention | Example |
|---|---|---|
| Tables | snake_case plural with a **module prefix** | `pur_orders`, `acc_ap_bills`, `na_closings`, `rpt_room_nights`, `hr_employees`, `crm_contacts`, `pos_orders`, `rm_rate_plans`, `hk_tasks`, `inv_items`, `banquet_events`, `ledger_entries` |
| Columns | snake_case; `team_id`; `created_by`; `<verb>_at` / `<verb>_by` pairs (`approved_at`, `approved_by`); `status` as `string(20)` with a `->comment('draft \| …')`; heavy use of column comments | |
| Document numbers | unique per hotel, prefixed and zero-padded | `PO-000001` |
| Models | singular PascalCase, explicit `$table`, one abstract base per module | `PurchasingModel`, `HrModel`, `RmModel` |
| Routes | unnamed REST nouns, action sub-resources, `'Controller@method'` strings, numeric ids constrained | `POST orders/{id}/approve` |
| Permissions | lowercase words with spaces, module prefix | `pur approve orders` |
| Events | past tense | `ReservationCheckIn`, `RoomReadinessChanged` |
| Jobs | `…Job` suffix in a module folder (new code) | `NightAudit/SendReportMailJob` |
| Commands | `<module>:<kebab-action>` | `accounting:process-posting-queue`, `rpt:etl:sync`, `night-audit:dispatch` |
| Translation keys for enums | prefixed snake_case | `pur_po_status_approved`, `na_flash_mail_subject` |

Exceptions: Maintenance tables have no prefix (`work_orders`, `assets`); HRM uses `hr_` not `hrm_`; POS Pro uses `pos_` not `posv2_`.

### 4.4 Keys, soft deletes, timestamps, public ids

- **Primary keys: auto-increment `bigIncrements('id')`** in all new tables (legacy tables use `increments`). Never UUID primary keys.
- Night Audit adds a secondary `uuid CHAR(36) UNIQUE` and uses it as the REST id.
- **Soft deletes only for drafts and master data.** Posted documents are never deleted; they are corrected by reversal ("Receipts are posted documents: no soft deletes … reversed, never deleted"). Append-only tables (`na_postings`, `na_audit_events`, `acc_audit_events`) throw on update and delete (`app/NightAudit/Immutable.php`).
- `timestamps()` everywhere except append-only tables.
- **Hashids** (`vinkla/hashids`, one connection, length 10) for any id in a public URL; internal Nova endpoints use numeric ids. Public share links (QR menu, purchase order PDF) use random tokens instead.

### 4.5 Money, currency, tax and rounding

- **Storage: `DECIMAL`.** `decimal(12,2)` (POS, Purchasing, Events, HRM), `decimal(15,2)` (GL: `ledger_*`, `acc_*`), `decimal(14,2)` (Night Audit, reports), `decimal(14,4)` (rates, unit costs), `decimal(5,2)` (percentages), `decimal(14,3)` (quantities).
- Legacy wallets (`bavix/laravel-wallet`): `bigInteger` minor units, converted with the wallet's `decimal_places` (default 2), never a hard-coded ×100.
- **Binding rule X-D08:** "Money is never a float in new code: amounts are stored as DECIMAL(14,4) (rates) or DECIMAL(14,2) (postings), computed with bcmath strings or integer minor units, and rounded once at the boundary." Night Audit and reports use bcmath at scale 6 and round **half away from zero** to 2 decimals (`app/Services/NightAudit/Posting/Money.php`). Purchasing is the exception: floats rounded per line, totals summed in integer halalas.
- **Currency:** SAR. `teams.currency` (default `SAR`), `ledger_journals.currency` (default `SAR`), `night_audit.functional_currency = SAR`. Multi-currency is out of scope everywhere.
- **Taxes** are per-hotel `settings` rows: `tax` (VAT %, normally 15), `accommodation_tax` (EWA, municipality/accommodation fee), `tourism_tax`. Legacy formula: VAT is charged on net + EWA (`app/helpers.php:585-590`). The newer accounting tax engine (`app/Services/Accounting/Tax/TaxEngine.php`, inclusive/exclusive, ZATCA categories) is behind the per-hotel flag `acc_settings.tax_engine`.
- **Report revenue is net of tax** (no VAT, EWA or tourism tax) unless the user picks "gross" (§5.2).

### 4.6 Dates, timezone, business date and Night Audit

- `config/app.php`: `'timezone' => 'Asia/Riyadh'`.
- **Per-hotel timezone:** no column; settings row `night_audit_timezone` (IANA), falling back to `Asia/Riyadh` (`NightAuditSettings::timezone()`).
- **Business date** is owned by Night Audit and read only through `App\Services\NightAudit\BusinessDate` ("Business date read API for every module … never read teams.business_date themselves"). Two kinds of hotel:
  - **Audited hotel** (`teams.business_date` set, i.e. Night Audit v2): the column is the open business date. A date is closed when it is before `teams.business_date`. The date **rolls only when Night Audit closes it**: inside the closing transaction, `UPDATE teams SET business_date = D+1, last_night_audit_at = NOW(), last_night_audit_by = :user WHERE id = :t AND business_date = D` (`docs/night-audit/ARCHITECTURE.md`).
  - **Derived hotel** (`teams.business_date` NULL; almost all hotels today): the business date is the hotel-local calendar date, except that a moment **before the cutoff** (`teams.night_audit_cutoff_time`, default `03:00`) belongs to the previous date. It rolls automatically by the clock.
- **Who triggers Night Audit v2:**
  - **Scheduled:** `night-audit:dispatch` every 5 minutes, for hotels with `night_audit_v2_enabled = 1` **and** `teams.night_audit_auto_enabled = 1`. Date D is due at D+1 local `max(night_audit_cutoff_time, night_audit_auto_run_time)` (decision NA-D08).
  - **Manual:** a user with `night audit run` (or `night audit force run` before the due time) from the Night Audit screen, or `php artisan night-audit:run {team} --date=`.
  - The trigger is recorded in `na_executions.trigger` (`auto | manual | cli | recovery`).
- The legacy night run (`nightaudit:auto-run`) still exists, but its schedule line is commented out.

### 4.7 Localization

- Arabic is the default (`config/app.php` `locale = 'ar'`, fallback `en`; Nova `default_locale = 'ar'`). The session locale is set by `/locale/{ar|en}` and `SetNovaLocale`.
- RTL: `dir="rtl"` on the layout for Arabic, a separate compiled `app-rtl.css` / `nova-rtl.css` (Sass → `rtlcss`). Components never hard-code `direction`.
- Translation files: `resources/lang/{ar,en}/*.php`, `resources/lang/{ar,en}.json`, and **`resources/lang/vendor/nova/{ar,en}.json`** for all module strings. Night Audit, Reports and Rate Management keep their strings in `docs/rnr/i18n/<module>-<part>.{ar,en}.json`, merged append-only by `scripts/rnr/merge-i18n.php`.
- Enum and status labels use prefixed snake_case keys with entries in both `ar.json` and `en.json`.
- **Hijri dates** shown next to Gregorian: PHP `geniusts/hijri-dates` (`Hijri::convertToHijri(...)`), JS `moment-hijri` (`m.format('iYYYY/iMM/iDD')`).
- Report PDFs print dates as `d.m.y` and times as `H:i` in the hotel timezone.

### 4.8 API conventions

- **Envelope:** Laravel API Resources, `{ "data": … }`; lists are paginators `{ "data": [...], "links": {...}, "meta": {...} }`.
- **Errors:** validation 422 `{ "message", "errors": { field: [...] } }` (Laravel default); business rules 422 `{ "message" }`; permission 403; Night Audit uses coded errors `{ "message", "code": "na_err_*" }`.
- **Pagination:** `->paginate((int) $request->input('per_page', 25))`, `per_page` validated `integer|min:1|max:100` (reports allow up to 500).
- **Versioning:** module APIs are unversioned (`/nova-vendor/<module>/…`). Versioned prefixes exist only for older external APIs (`consumer/v1`, `v2` for mobile housekeeping).
- **Rate limiting:** the global API throttle is commented out. Only some endpoints throttle (Reports Plus run/export `throttle:20,1` and `throttle:30,1`).

### 4.9 Import / export

`CLAUDE.md`: every list, register, report or setup screen whose data users keep outside the PMS gets export (Excel / CSV, and PDF for reports) and, for setup data, import with a template, row-level validation and an error report. `maatwebsite/excel` 3.1, classes in `app/Exports/<Module>` and `app/Imports/<Module>`, inside `TeamScope`, permission-gated, queued for large files.

### 4.10 Side effects

Observers (`app/Observers/`) and event listeners carry side effects (notifications, logs, cache busts), not controllers. New-module alerts **never** create `pms_notifications` rows; they are polled endpoints shown inside the module (`docs/DECISION.md` X1). Purchasing is the exception.

### 4.11 Audit logging

- `spatie/laravel-activitylog` 3.7.1, model `App\Activity`, table `activity_log` with a `team_id` column, records kept 60 days. Logging can be switched off per hotel (`teams.enable_activity_logs`).
- Standard model block (custom `App\Traits\LogsActivity`):

```php
protected static $logName = 'purchasing';
protected static $logAttributes = ['*'];
protected static $logAttributesToIgnore = ['id', 'team_id', 'created_at', 'updated_at' /* + secrets */];
protected static $logOnlyDirty = true;
protected static $submitEmptyLogs = false;
```

- Business actions are logged explicitly: `activity('purchasing')->performedOn($order)->causedBy($user)->withProperties([...])->log('approve')`.
- Finance and Night Audit keep their own append-only audit tables (`acc_audit_events`, `na_audit_events`).
- Secrets (IBANs, tokens) are encrypted with `Crypt::encryptString`, never returned (masked `••••1234` plus `has_<field>`) and never logged.

### 4.12 Static analysis and formatting

- `.styleci.yml`: `preset: laravel` (StyleCI). `.editorconfig`: UTF-8, LF, 4 spaces.
- **Not present:** PHPStan / Larastan, Pint, PHP-CS-Fixer config, Rector, project ESLint / Prettier, lint or test CI.

### 4.13 Tests

- **PHPUnit 7.5**, not Pest. Suites in `phpunit.xml`: `Unit`, `Feature`, `ShomoosWallet`, `Purchasing`, `NightAudit`, `Reports`, `RateManagement`, `Accounting`, `BusinessTimestamps`, `Rnr`.
- Tests never touch the `.env` database. Module test bases repoint the `mysql` connection at a disposable database and refuse to run otherwise (`tests/Rnr/RnrTestCase.php`: name must start with `fdq_rnr_`, port `33080`; `tests/Feature/Purchasing/PurchasingTestCase.php`: name must end in `_test`). Each test runs in a rolled-back transaction.
- **Tenancy in tests:** insert a team, a user with `current_team_id`, a `team_users` row and a role with the needed permission slugs, then log in:

```php
// tests/Feature/Purchasing/PurchasingTestCase.php
list($this->teamId, $this->user) = $this->createTeamWithUser(self::ALL_PERMISSIONS);
$this->loginAs($this->user);
// createTeamWithUser(): DB::table('teams')->insertGetId([...]); DB::table('settings')->insert(['team_id' => $teamId, 'key' => 'tax', 'value' => '15']);
// users.current_team_id = $teamId, a team_users row, a role with role_permission rows, role_user
```

  Cross-hotel isolation is tested by creating a second team and running the same call as its user (`$this->runAs($other, function () { … })`).
- **Factories are not used** (only the stock `UserFactory`). Tests insert rows with `DB::table()->insertGetId()` or call the real services.
- Test names: `test_snake_case_sentence()` with a docblock.
- Browser tests: Python Selenium under `/testing/<Module>/<case>/test_<case>.py`.

### 4.14 Rules documents

- `CLAUDE.md` (root): architecture, module table, conventions, safety rules.
- `.requirements/decisions.md`: cross-module decisions X-D01..X-D15; per-area decision logs under `.requirements/*/decisions.md` (NA-D, ACC-D, RPT-D, RM-D).
- `docs/DECISION.md`, `decisions.md` (root), and `docs/<module>/ARCHITECTURE.md` per module (binding contracts).
- `CONTRIBUTING.md`: not present.

Key rules, quoted:

1. "Business logic lives in `app/Services/`. Controllers … must stay thin." (`CLAUDE.md`)
2. "Never bypass `TeamScope` without intentional cross-team queries." (`CLAUDE.md`)
3. "A figure is computed once, by the report's code, and the dashboard calls that code." Dashboards contain no SQL; this is enforced by `tests/Unit/ReportsPlus/DashboardsHaveNoSqlTest.php`. (`CLAUDE.md`)
4. "Replica only. Every dashboard and report read goes through a `ReportContext` (`$ctx->db()`)." (`CLAUDE.md`)
5. "Money is never a float in new code … rounded once at the boundary." (X-D08)
6. "Business date is owned by Night Audit … they never compute their own." (X-D08 / X-D14)
7. KPIs come only from `KpiCalculator`; "ratios over several hotels or periods are recomputed from summed parts, never averaged". (X-D13, `CLAUDE.md`)
8. "Whenever a new permission is introduced, add it [to `config/novapermissions.php`]"; it is granted by an idempotent migration. (`CLAUDE.md`, X-D12)
9. "Do not change database migrations — add new ones instead." (`CLAUDE.md`)

---

## 5. Reports FGRE will ingest

All reports run on one engine, **Reports Plus** (Nova module `nova-components/PmsReports`, classes in `app/Services/Reporting/Reports/`). A report class declares `key()`, `code()`, `parameters()`, `columns()`, `rows()` and optional `groups()`, totals and `charts()`. The same run produces screen JSON, PDF, XLSX and CSV. Specs per report: `docs/reports/specs/<CODE>.md`.

The engine only exists for hotels with control flag `pms_reports_enabled = 1`.

### 5.1 Manager Flash Report (EOD-016)

- Class `app/Services/Reporting/Reports/EndOfDay/ManagerFlashReport.php`. Key `manager-flash`, code `EOD-016`, program name `manager_report`, title "Manager - Flash" / "تقرير المدير السريع". Letter portrait, matrix layout. Permissions `rpt view end of day` + `rpt report manager-flash`.
- **Parameters:** `date` (business date, default = last closed date), `last_year` (default true), `gross_net` (`net` default | `gross`).
- **Columns:** `label`, then six period columns. In the PDF each column has the year printed above it.

| Key | Header | Period (`OpsMetrics::periods()`) |
|---|---|---|
| `d_ty` | DAY | the business date D |
| `m_ty` | MONTH | 1st of D's calendar month → D |
| `y_ty` | YEAR | 1 January of D's year → D |
| `d_ly` | DAY (last year) | D − 1 year (29 Feb → 28 Feb) |
| `m_ly` | MONTH (last year) | 1st of that month → D − 1 year |
| `y_ly` | YEAR (last year) | 1 January → D − 1 year |

```php
// app/Services/Reporting/Query/Operations/OpsMetrics.php
$ly = date('Y-m-d', strtotime($date . ' -1 year'));
if (substr($date, 5) === '02-29') { $ly = (substr($date, 0, 4) - 1) . '-02-28'; }
$p = ['d_ty' => [$date, $date], 'm_ty' => [substr($date, 0, 8) . '01', $date], 'y_ty' => [substr($date, 0, 5) . '01-01', $date]];
if ($lastYear) { $p += ['d_ly' => [$ly, $ly], 'm_ly' => [substr($ly, 0, 8) . '01', $ly], 'y_ly' => [substr($ly, 0, 5) . '01-01', $ly]]; }
```

  Last year is the same calendar date, not the same weekday. Transactions are assigned to business days with the hotel's cutoff time (`BusinessDay::forTeam()->range()`).

- **Rows:** 100 lines in 9 sections, in this order. Section titles are not printed; each JSON row carries `section` and a stable key `_code`. "Day only" lines have `null` in the month and year columns.

| § | Rows (printed label → `_code`) |
|---|---|
| 1 Rooms | Total Rooms in Hotel `total_rooms` · Rooms Occupied `rooms_occupied` · Total Rooms in Hotel minus OOO Rooms `total_minus_ooo` · Available Rooms `available_rooms` · Available Rooms minus OOO Rooms `available_minus_ooo` · Complimentary Rooms `comp_rooms` · House Use Rooms `house_rooms` · Rooms Occupied minus Comp and House Use `occ_minus_comp_house` · Rooms Occupied minus House Use `occ_minus_house` · Rooms Occupied minus Comp `occ_minus_comp` · Day Use Rooms `day_use_rooms` · Out of Order Rooms `ooo_rooms` · Out of Service Rooms `oos_rooms` |
| 2 In house | In-House Adults · In-House Children · Total In-House Persons · Individual / Block / Member / VIP Persons In-House · Individual / Block / Source / Company / Travel Agent / Group Rooms In-House · Blocks In-House · Birthdays In-House · eight occupancy % lines: % Rooms Occupied `pct_occ` · % minus Comp and House · % minus Comp, House and OOO · % minus Comp · % minus House · % minus Comp and OOO · % minus House and OOO · % minus OOO `pct_occ_minus_ooo` |
| 3 Arrivals | Arrival Rooms / Persons · Deducted Arrivals · Non-Deducted Arrivals · Walk-in Rooms / Persons |
| 4 Departures | Extended Departure Rooms / Persons · Departure Rooms / Persons · Early Departure Rooms / Persons · Individual Departure Rooms / Persons · Individual Member Departure Rooms / Persons · % Individual Member Departures · Member Departure Rooms / Persons · % Member Departures |
| 5 No shows | No Show Rooms / Persons · Cancelled Reservations for Today · Late Reservation Cancellations for Today |
| 6 Bookings | Reservations Made Today · Reservation Cancellations made Today · Room Nights Reserved Today · Turnaways · Today's Demand |
| 7 Housekeeping | Clean Rooms (day only) · Dirty Rooms (day only) · Doubles As Singles · % Beds Occupied |
| 8 Revenue | ADR `adr` · ADR minus Comp · ADR minus House · ADR minus Comp and House · Average Person Rate · Average Persons per Block Rooms · Average Revenue per Block Rooms · Average Room Revenue per Block Rooms · Room Revenue `room_revenue` · Food And Beverage Revenue · Other Revenue · Total Revenue · Block Revenue · Block Room Revenue · Individual Revenue · Individual Room Revenue · Member Revenue · Member Room Revenue · Total Revenue per Person · Payment · Maximum Revenue · Maximum Revenue for Rooms Occupied · Maximum Revenue % · Maximum Revenue % per Rooms Occupied |
| 9 Tomorrow (day only) | Arrival Rooms / Persons for Tomorrow · Departure Rooms / Persons for Tomorrow · % Rooms Occupied for Tomorrow · % Multiple Occupancy · % Rooms Occupied for the Next 7 Days |

- Row definitions: `const LINES` (`ManagerFlashReport.php:31-62`). Figures: `components()` (eight additive queries, public so dashboards reuse it) and `period()`.
- Watch out: **"Available Rooms" on this report is vacant rooms** (`total_rooms − rooms_occupied`), not sellable inventory. Percent lines are percentages (`1.59` means 1.59 %). A zero denominator prints `0.00`.

### 5.2 KPI formulas

The single implementation is `app/Services/Reporting/Query/KpiCalculator.php` (decision X-D13). Inputs per period are sums over business days:

```
rooms_sold        Σ rpt_room_nights.rooms_sold (day use excluded: rooms_sold = 0)
total_rooms       Σ rooms in hotel per day (enabled, not virtual)       -> room-nights available
ooo_rooms         Σ out-of-order rooms per day       oos_rooms  Σ out-of-service rooms per day
comp_rooms        Σ sold nights of Complimentary stays
house_rooms       Σ sold nights of HouseUse stays
room_revenue_net  Σ net room revenue (no VAT, EWA or tourism; comp / house nights at their stored rate)
```

| KPI | Formula | Where |
|---|---|---|
| Rooms occupied | `rooms_sold` | KpiCalculator |
| % Rooms Occupied (`pct_occ`) | occupied / total_rooms × 100 | KpiCalculator |
| % minus OOO | occupied / (total_rooms − ooo) × 100 | KpiCalculator |
| % minus Comp and House | (occupied − comp − house) / total_rooms × 100 | KpiCalculator |
| **Headline occupancy** (X-D13, Night Audit, dashboards) | **(occupied − comp − house) / (total_rooms − ooo) × 100** | KpiCalculator `occupancy` |
| ADR (`adr`, as printed) | room_revenue_net / occupied | KpiCalculator |
| **Headline ADR** | **room_revenue_net / (occupied − comp − house)** | `adr_net` |
| RevPAR | room_revenue_net / total_rooms | `revpar` |
| **Headline RevPAR** | **room_revenue_net / (total_rooms − ooo)** | `revpar_net` |
| TRevPAR | total net revenue (rooms + F&B + other) / (total_rooms − ooo) | `ManagerFlashDashboard.php:191` (not a Flash line) |
| ALOS | nights / reservations departed | KpiCalculator `avg_length_of_stay` (not a Flash line) |
| Avg persons per room | (adults + children) / occupied | KpiCalculator |
| % Beds Occupied | persons / beds × 100 | ManagerFlashReport |
| Maximum Revenue % | room revenue / rack revenue × 100 | ManagerFlashReport |
| GOPPAR | GOP / available rooms | Finance dashboard and USALI KPI report only |

Rules behind the inputs (`docs/reports/ARCHITECTURE.md` §5–6):

- **Available inventory:** units with `deleted_at IS NULL AND enabled = 1`, virtual room types excluded. **Out-of-order rooms are removed from inventory** in the headline KPIs; **out-of-service rooms are never removed**, only reported.
- **Sold rooms:** cancelled, timed-out and no-show stays, stays without a room and virtual types are excluded. **Day use is not a room night.** Complimentary (`Complimentary`) and house-use (`HouseUse`) stays count as sold in the plain lines and are removed only in the "minus Comp / House" and headline variants.
- **Room revenue:** **net of tax** (no VAT, EWA or tourism tax), per night from the reservation's daily prices, **plus room-type service items** (adjustments, rebates, no-show charges). F&B and other revenue are separate lines. There is no package split. `gross_net = gross` switches the nightly amounts to tax-inclusive (gross = net × (sub_total + ewa + vat + ttx) / sub_total); service items stay net.
- Ratios: bcmath at scale 6, rounded half away from zero to 2 decimals; a zero or negative denominator gives `null` in data (with a reason) and `0.00` on the printed report.
- Over several hotels or periods, ratios are recomputed from the summed parts, never averaged.

Decision X-D13, verbatim (`.requirements/decisions.md`):

> - **Occupancy %** = rooms occupied minus comp and house use / (total rooms minus out-of-order) - "applicable sellable inventory";
> - **ADR** = net room revenue / rooms occupied minus comp and house use (comp and house use carry no revenue);
> - **RevPAR** = net room revenue / (total rooms minus out-of-order).
> Day-use is not a room night. Zero denominator: the value is `null` with a reason in the data, printed `0.00` on Opera-style reports.

**Consequence for FGRE validation:** the Manager Flash *prints* `pct_occ` and `adr` on the plain basis (comp and house included, OOO in inventory). If FGRE recomputes the headline definition it will not match the "% Rooms Occupied" line. Compare against the matching variant line (e.g. "% Rooms Occupied minus Comp, House and OOO", "ADR minus Comp and House").

### 5.3 When a day's figures are final

- A report run over a day Night Audit has not closed is **provisional**: `meta.provisional = true` in JSON and a red "PROVISIONAL - not closed by Night Audit" line under the PDF title.
- Closed days are read from **locked facts** (`rpt_*` tables loaded by the reports ETL), so a closed day's figures do not change. Corrections to a closed date are made by reversal and repost, never by editing.
- For **derived hotels** (no Night Audit v2), no day is ever closed, so every run is provisional.

### 5.4 Trial Balance: three reports

| | FIN-040 `trial-balance` | FST-001 `fin-trial-balance` | FAC-001 `fin-acc-period-trial-balance` |
|---|---|---|---|
| What | Opera-style **front-office** trial balance: guest, AR (city) and deposit ledger roll-forward | **General-ledger** trial balance from posted journals | **Accounting-period** trial balance (Financial Management v2) |
| Class | `Reports/Financial/TrialBalance/TrialBalanceReport.php` + `TrialBalanceBuilder.php` | `Reports/Modules/Finance/Statements/TrialBalanceReport.php` → `app/Services/Accounting/LedgerReportService.php::trialBalance()` | `Reports/Modules/Finance/Accounting/PeriodTrialBalanceReport.php` → `app/Services/Accounting/Periods/PeriodTrialBalanceService.php` |
| Parameters | `date` (one business date) | `period_from`, `period_to` (max 366 days), `include_statistical` | period or `acc_period` |
| Columns | label, transaction code, description, one signed amount | Account No., Account Name, Facility Ledger ID, Debit, Credit, Debit Balance, Credit Balance | Account No., Account Name, Account Class (`root_type`), Debit, Credit, Debit Balance, Credit Balance |
| Opening / closing | Yes: Balance Brought Forward / Carried Forward, Balance Yesterday / Today per ledger | **No opening column**: period movements and their net | **No opening column** |
| Permission | `rpt view financial` + `rpt report trial-balance` | `financial statements view` / `acc reports view` | `acc *`, hotel needs `fm_v2_enabled` |
| Final | when Night Audit closes the date | when the journals are APPROVED or SENT (§5.4.3) | when the period's journals are posted; closed periods refuse posting |

#### 5.4.1 FIN-040 (front-office trial balance)

Line order (`TrialBalanceReport.php:90-169`, see the sample PDF):

- **Daily Transactions:** Balance Brought Forward; Revenue / Non Revenue / Paid Out / Payment, each with one row per transaction code and a total; Transaction Total Today; Grand Total; AR Ledger Payments; Deposit Ledger Activity; Balance Carried Forward; Accruals Today; Guest Ledger Balance.
- **Guest Ledger:** Balance Yesterday, Charges, Payments, Deposits Transferred at Check-In, Balance Today.
- **AR Ledger:** Balance Yesterday, Charges and Transfers, Payments, Balance Today.
- **Deposit Ledger:** Balance Yesterday, Deposits Transferred at Check-In, Payments, Balance Today.
- **Package Ledger:** always 0.00 (the PMS has no package ledger).
- **Hotel Balance**, then check lines ("Guest Ledger is in balance", "There are 3 guests checked out with balance", …).

Formulas (`TrialBalanceBuilder.php`; charges +, payments −):

```php
$grand   = bcadd($gl0, $transactionTotal, 2);            // opening guest ledger + the day's postings
$carried = Money::sum([$grand, $arBack, $dlBack]);
$glToday = Money::sum([$gl0, $glCharges, $glPayments, $deposit8010]);
'hotel_balance' => Money::sum([$glToday, $arToday, $dlToday]),
```

Transaction codes are 4 digits: rooms `1xxx`, F&B `2xxx`, other `3xxx`, taxes `5xxx` (`5001` VAT, `5004` EWA, `5000` tourism), payments `7xxx`, `8010` deposits at check-in, `8020` city ledger, `9301` paid out.

#### 5.4.2 FST-001 (general-ledger trial balance) and the chart of accounts

```php
// LedgerReportService::trialBalance(): ledger_entries ⨝ ledger_journals ⨝ chart_of_accounts,
// journals with status APPROVED or SENT, transaction_datetime between from 00:00:00 and to 23:59:59
'balanceDebit'  => $net > 0 ? $this->round($net) : 0.0,
'balanceCredit' => $net < 0 ? $this->round(-$net) : 0.0,
```

Rows are grouped into a "Monetary Accounts" section and, if asked, a statistical section, each with a section total, plus a grand total. Σ debit = Σ credit when the ledger balances.

**Chart of accounts** (`chart_of_accounts`, `database/migrations/2026_09_14_100000_create_chart_of_accounts_table.php` and later):

- Based on the **NTMP** chart (Saudi National Tourism Monitoring Platform, `source = 'ntmp'`), laid out in **USALI 11th edition** terms (`docs/reports/FINANCE-REPORTS.md`). Hotels may add their own accounts (`source = 'manual'`) and their own numbering.
- **6-digit account numbers, 4 levels**, parent by `parent_account_number`. Example from the NTMP template:

| Level | Example |
|---|---|
| 1 | `100000` Assets |
| 2 | `110000` Current Assets |
| 3 | `111000` Cash and Bank |
| 4 (postable) | `111101` Cash on Hand, `111201` Bank Account - SAR |

  First digit = class: `1` assets, `2` liabilities, `3` equity, `4` income (`410101` Room Revenue - Individual / Transient, `420101` Restaurant Revenue, `419101` Room Discounts), `5`+ expenses (`510101` Rooms Payroll).
- Columns: `account_number`, `account_name_en` / `_ar`, `parent_account_number`, `node_type` (`GROUP` headings / `LEDGER` postable), `root_type` (`ASSET`, `LIABILITY`, `EQUITY`, `INCOME`, `EXPENSE`, `STATISTICAL`), `account_type` (e.g. `CURRENT_ASSET`, `OPERATING_REVENUE`, `CONTRA_REVENUE`, `DEPARTMENTAL_EXPENSE`), `normal_balance` (`DEBIT` / `CREDIT`), `financial_statement` (`BALANCE_SHEET` / `PROFIT_AND_LOSS`), `report_section`, `report_line`, `level`, `kpi_tags`.
- The hotel's own numbering: `facility_ledger_id` (unique per hotel), `facility_account_name_en/_ar`, `facility_parent_account_number`. Several facility accounts can map to one NTMP account. Both numbers are printed on FST-001.
- GL tables: `ledger_journals` (`status` DRAFT | APPROVED | SENT | FAILED, `transaction_datetime`, `journal_type`, `period_id`), `ledger_entries` (`account_number`, `chart_of_account_id`, `debit`, `credit` `decimal(15,2)`).

#### 5.4.3 When the GL is final for a day

- Night Audit v2 writes the day's GL journals inside the close (`app/Services/NightAudit/Gl/GlJournalWriter.php`), dated D 23:59:00, reference `NA-yyyymmdd-<CAT>`.
- They are **DRAFT by default** (hotel setting `night_audit_gl_posting_mode` = `draft` | `approve`). **FST-001 and FAC-001 count only APPROVED and SENT journals**, so in draft mode a day's Night Audit postings appear only after an accountant approves them.
- Accounting periods (`acc_periods`) go open → soft_closed → closed; a closed period refuses posting. The year-end closing journal is period 13 and is excluded from FAC-001.

### 5.5 Structured data (JSON)

**Yes, every report can be produced as JSON**, through the Reports Plus API. JSON is not a file export format; it is the screen payload.

- Routes: `nova-components/PmsReports/routes/api.php`, prefix `/nova-vendor/pms-reports`, middleware `['nova', Authorize::class]`.
- **Authentication: a Nova browser session** (web guard). The team is always the user's `current_team_id`; a `team_id` in the request is refused. There is **no token API** for reports.
- Authorization: `rpt view <category>` + `rpt report <key>`; exports also need `rpt export` (and `rpt export pii` for reports with personal data); finance reports need their own `acc` / `financial statements` permissions.

| Method | Path | Purpose |
|---|---|---|
| GET | `catalogue` | reports the user may open |
| GET | `reports/{key}` | definition: parameters, columns, groups |
| **POST** | **`reports/{key}/run`** | body = parameters (+ `per_page`, max 500) → JSON, first page |
| GET | `runs/{uuid}/page/{n}` | next pages |
| POST | `reports/{key}/export/{pdf\|xlsx\|csv}` | file, or `202 {run_id, status: "queued"}` above the sync limits (PDF 2 000 rows, XLSX/CSV 50 000) |
| GET | `runs/{uuid}`, `runs/{uuid}/download` | queued export status and file (kept 24 h) |
| GET | `group/{key}`, `group/{key}/export`, `group/{key}/pdf` | multi-hotel group reports (`group/manager-flash`: Day columns, one row per hotel) |
| GET | `group-dashboard`, `group-dashboard/export` | Group Performance Dashboard (all the user's hotels; CSV export) |
| GET | `dashboards/{key}` | dashboard JSON |

Errors: `{message}` or `{message, errors}` with 403, 404, 413, 422, 504. Run and export endpoints are throttled at 20–30 requests per minute.

**Sample payload** (real engine output for the demo hotel, trimmed; full files in `docs/samples/fandaqah-pms/*.json`):

```json
POST /nova-vendor/pms-reports/reports/manager-flash/run
{"date": "2026-11-19", "last_year": 1, "gross_net": "net"}

{
  "meta": {
    "key": "manager-flash", "code": "EOD-016", "program": "manager_report", "category": "end_of_day",
    "layout": "matrix", "title_en": "Manager - Flash", "title_ar": "تقرير المدير السريع",
    "hotel": {"name": "فندق العنوان", "en_name": "Address Hotel", "logo": null, "currency": "SAR"},
    "business_date": "2026-10-08",
    "day_statuses": {"2026-11-19": "open"},
    "provisional": true,
    "generated_at": "2026-11-21T20:13:51+03:00", "generated_by": "Night Audit", "lang": "en",
    "filters": {"date": "2026-11-19", "last_year": true, "gross_net": "net"},
    "notes": [{"en": "Provisional: 19.11.26 not closed by Night Audit", "ar": "مبدئي: لم يُغلق التدقيق الليلي 19.11.26"}],
    "run_id": "756cbfea-7cf8-4ed4-be29-6a4878da4a4b", "source": "mixed"
  },
  "columns": [
    {"key": "label", "type": "string"},
    {"key": "d_ty", "label_en": "Day", "label_ar": "اليوم", "type": "decimal", "band": "ty"},
    {"key": "m_ty", "label_en": "MONTH", "type": "decimal", "band": "ty"},
    {"key": "y_ty", "label_en": "YEAR", "type": "decimal", "band": "ty"},
    {"key": "d_ly", "band": "ly"}, {"key": "m_ly", "band": "ly"}, {"key": "y_ly", "band": "ly"}
  ],
  "column_bands": [
    {"key": "ty", "label_en": "This Year", "columns": ["d_ty", "m_ty", "y_ty"], "column_labels": {"d_ty": "2026", "m_ty": "2026", "y_ty": "2026"}},
    {"key": "ly", "label_en": "Last Year", "columns": ["d_ly", "m_ly", "y_ly"], "column_labels": {"d_ly": "2025", "m_ly": "2025", "y_ly": "2025"}}
  ],
  "sections": [{"key": "all", "rows": [
    {"section": 1, "_code": "total_rooms", "label": "Total Rooms in Hotel", "_format": "int", "d_ty": 66, "m_ty": 1254, "y_ty": 21318, "d_ly": 66, "m_ly": 1254, "y_ly": 21318, "_type": "metric"},
    {"section": 1, "_code": "rooms_occupied", "label": "Rooms Occupied", "_format": "int", "d_ty": 0, "m_ty": 20, "y_ty": 79, "d_ly": 0, "m_ly": 0, "y_ly": 2, "_type": "metric"},
    {"section": 2, "_code": "pct_occ", "label": "% Rooms Occupied", "_format": "percent", "d_ty": 0, "m_ty": 1.59, "y_ty": 0.37, "d_ly": 0, "m_ly": 0, "y_ly": 0.01, "_type": "metric"},
    {"section": 7, "_code": "clean_rooms", "label": "Clean Rooms", "_format": "int", "d_ty": 63, "m_ty": null, "y_ty": null, "d_ly": null, "m_ly": null, "y_ly": null, "_type": "metric"},
    {"section": 8, "_code": "adr", "label": "ADR", "_format": "money", "d_ty": 0, "m_ty": 450, "y_ty": 543.57, "d_ly": 0, "m_ly": 0, "y_ly": 1050, "_type": "metric"},
    {"section": 8, "_code": "room_revenue", "label": "Room Revenue", "_format": "money", "d_ty": 0, "m_ty": 9000, "y_ty": 42941.98, "d_ly": 0, "m_ly": 0, "y_ly": 2100, "_type": "metric"}
  ]}],
  "totals": null, "summary": [], "charts": []
}
```

GL Trial Balance (FST-001) rows look like this:

```json
{"section": 1, "section_label": "Monetary Accounts", "account_number": "111101", "account_name": "Cash on Hand",
 "facility_ledger_id": "111101", "debit": 4500, "credit": 0, "balance_debit": 4500, "balance_credit": 0, "_type": "data"}
// section subtotal and "totals": {"label_en": "Grand Total", "debit": 1936100, "credit": 1936100, "balance_debit": 1872100, "balance_credit": 1872100, "_count": 20}
```

Notes for parsers:

- Use `_code` (Manager Flash) and `account_number` / `facility_ledger_id` (GL TB) as keys, never labels; labels follow the request locale.
- `meta.business_date` is the hotel's **current** business date, not the report date. The report date is `meta.filters.date` (or `period_from` / `period_to`).
- Check `meta.provisional` before storing a day as final.

Other APIs: an older Passport API `GET /api/reports/trial-balance` exists for the mobile app (`routes/api.php:112-126`), but the reports team flags its amounts as wrong (hard-coded ÷100). The fandaqah_api service (separate repo) serves `/api/v1/ops-reports/{key}` for operational reports; it is not documented here.

### 5.6 PDF layout and file names

Renderer: mPDF 7 (`app/Services/Reporting/Render/PdfRenderer.php` → `Render/Pdf/PdfDocumentWriter.php`), frame from `nova-components/PmsReports/resources/views/print/frame.blade.php` and `Render/Pdf/FrameBuilder.php`. Page layouts per report in `resources/pms-reports/layouts/<CODE>.php` (`docs/reports/LAYOUT-STANDARD.md`). PDF metadata creator: "Fandaqah PMS Reports".

| Position | Content |
|---|---|
| Top left | hotel logo (from `teams.photo_url` or website settings, when present) |
| Top centre | **hotel name** (`en_name` in English, `name` in Arabic), then the report title; module and finance reports also print address, phone, e-mail, VAT and CR numbers |
| Under the title | red "PROVISIONAL - not closed by Night Audit" when the day is open |
| Top right | `Date/Time: dd.mm.yy` and `HH:MM` (print time, hotel timezone), `User: <name>` ("Night Audit" for system renders such as the e-mail) |
| Column header | column bands (the year above each Flash column) |
| Bottom left | `Filter` echo of the parameters, e.g. "Calendar/Month to Date 19.11.26", "Last Year Yes", "Net / Gross Net" |
| Bottom centre | `Page n of N` |
| Bottom right | Opera program name (`manager_report`, `trial_balance`) |

- **Business date is not a separate header field**; it appears in the filter echo (bottom left) as `dd.mm.yy`.
- **Property code: not printed.** The hotel's id is not printed either; only its name.
- **Arabic PDFs are mirrored** (labels on the right, last-year columns on the left) and their extracted text order is scrambled. Read them visually, not by text extraction.

File names:

| Source | Pattern | Example |
|---|---|---|
| Download from Reports Plus | `{CODE}_{hotel-slug}_{from}[_{to}].{ext}` (`Engine/ExportService.php:119-173`) | `EOD-016_<slug>_2026-11-19.pdf` |
| Night Audit e-mail attachment | `manager_flash_{Ymd}.pdf` (`ReportMailRenderer.php`) | `manager_flash_20261119.pdf` |
| Dashboard e-mail attachment | `dashboard_{key}_{period_key}.pdf` | |

### 5.7 Other financial and KPI reports

Keys and codes from the report classes (`config/pms_reports_catalogue.php` lists every code).

- **End of day:** `manager-flash` (EOD-016), `daily-statistics` (EOD-019), `eod-cashier-summary` (EOD-005), `eod-guests-by-tax-type` (EOD-011), `no-show-reservations` (EOD-020).
- **Statistics:** `day-mtd-ytd-statistics` (STA-004), `reservation-statistics-daily` (STA-012), `reservation-statistics` (STA-011), `room-type-statistics` (STA-014), `room-statistics` (STA-013), `source-statistics` (STA-021), country / nationality / length-of-stay statistics, `yearly-forecast`.
- **Forecast:** `rooms-on-the-books` (FOR-012), `business-on-the-books` (FOR-002), **`market-segment-totals`** (FOR-009), `history-and-forecast` (FOR-004), `four-week-forecast`, `three-month-forecast`, `forecast-future-occupancy` (RES-007).
- **Financial (front office):** `trial-balance` (FIN-040), `guest-trial-balance` (FIN-026), `guest-ledger` (FIN-024), `deposit-ledger` (FIN-008), **`cashier-audit`** (FIN-002), **`revenue-by-transaction-codes`** (FIN-013, the daily revenue by code), `taxes` (FIN-039), `room-revenue-by-tax-type` (FIN-037), `journal-by-cashier-and-code` (FIN-032), `paid-outs` (FIN-035), `open-balance-all` (FIN-033).
- **Accounts receivable:** **`ar-aging-summary-by-types`** (AR-002), `ar-aging-summary-all-accounts` (AR-003), `ar-detailed-aging` (AR-021), `ar-ledger` (AR-012), `ar-payments`, `ar-collection-statistics`.
- **Finance statements (FST):** `fin-trial-balance`, `fin-profit-and-loss`, `fin-balance-sheet`, `fin-comparative-statement`, `fin-profit-and-loss-by-month`, `fin-general-ledger-detail`, `fin-journal-register`, `fin-chart-of-accounts`, `fin-cash-flow-summary`.
- **USALI (FUS):** `fin-usali-summary-statement`, `fin-usali-rooms-schedule`, `fin-usali-fnb-schedule`, `fin-usali-undistributed-expenses`, `fin-usali-kpis`, `fin-usali-monthly-trend`, `fin-usali-revenue-mix`.
- **Payments and cashiers (FPR):** **`fin-cashier-shifts-summary`**, `fin-collections-by-user`, `fin-receipts-by-method`, `fin-payment-receipts-register`, `fin-refunds-paid-outs`.
- **Accounting (FAC):** `fin-acc-period-trial-balance`, `fin-acc-ar-aging`, `fin-acc-ap-aging`, `fin-acc-budget-vs-actual`.
- **POS:** `pos-sales-daily-summary`, `pos-z-history`, `pos-cash-variance`.
- **Not present:** a report named "Daily Revenue" (the nearest are `revenue-by-transaction-codes` and the revenue lines of the Manager Flash).

---

## 6. Scheduled report e-mails

Two mechanisms exist. Neither sends a Trial Balance.

### 6.1 Night Audit Manager Flash e-mail (the one FGRE can ingest)

- **Who gets it:** only hotels on **Night Audit v2** (`night_audit_v2_enabled = 1`) that switch it on in Night Audit settings: `night_audit_flash_mail_enabled` (default off), `night_audit_flash_mail_recipients` (up to 10 addresses), `night_audit_flash_mail_locale` (`ar` default | `en`). Hotels without Night Audit v2 get **no** scheduled Manager Flash.
- **When:** event-driven, not a fixed clock. After a Night Audit close commits, the `flash_mail` post-commit task inserts one `na_report_mails` row (unique per hotel, business date and report) and queues `App\Jobs\NightAudit\SendReportMailJob` 60 seconds later. The job waits until the run has finished successfully and the day's locked facts are loaded, then sends. Retries after 5, 15 and 60 minutes (4 attempts); after the last failure an alert is raised in Night Audit. **A provisional (open) day is never mailed.** A date is mailed once; "Resend" in the Night Audit day view sends it again.
- So the e-mail arrives shortly after the hotel's Night Audit, which is D+1 local at `max(cutoff, auto-run time)` for automatic runs, or whenever a user runs it.
- **Content:** the Manager Flash for the closed date with `last_year = 1`, `gross_net = net`, as PDF, the same document a user would export. Short bilingual body; figures only in the PDF.
- **Sender:** the application's global `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME`; no per-hotel sender.
- **Subject** (`na_flash_mail_subject`):
  - English: `Manager Flash – {hotel} – {Y-m-d}`
  - Arabic: `التقرير السريع للمدير – {hotel} – {Y-m-d}`
  - `{hotel}` is `teams.name` (normally the Arabic name), the dash is an en dash (–), the date is the business date.
- **Attachment:** `manager_flash_{Ymd}.pdf`.
- **Property code:** not in the subject, file name or PDF. The **business date is in the subject (`Y-m-d`), the file name (`Ymd`) and the PDF filter line**. To match a hotel FGRE has the sender, the hotel name in the subject and the hotel name printed in the PDF header. Hotel names are not guaranteed unique, so FGRE should allow-list recipient address + hotel name per property. Adding `teams.id` (or a code) to the subject and file name would be a small PMS change (`ManagerFlashMail::subjectLine()`, `ReportMailRenderer::managerFlash()`); it does not exist today.

Classes: `app/Services/NightAudit/Mail/ReportMailOutbox.php`, `app/Jobs/NightAudit/SendReportMailJob.php`, `app/Services/NightAudit/Mail/ReportMailRenderer.php`, `app/Mail/NightAudit/ManagerFlashMail.php`, view `emails.night-audit.manager-flash`.

### 6.2 Reports Plus dashboard subscriptions

- A user subscribes to a **dashboard** (not a report): table `rpt_dashboard_subscriptions` (frequency daily | weekly | monthly, `send_time` in hotel time, default 07:00, locale, charts / tables / both).
- `rpt:dashboards:dispatch` runs every 5 minutes (`app/Providers/PmsReportsServiceProvider.php`). Daily covers business date − 1, weekly the last Monday–Sunday week, monthly the previous month; it is sent at or after `send_time` once every date in the period is closed and loaded.
- Recipient: **only the subscriber's own e-mail address**; permissions are re-checked at send time.
- Subject `:dashboard – :hotel – :period`, attachment `dashboard_{key}_{period_key}.pdf`.
- Not useful as a Trial Balance source.

### 6.3 Not present

- Scheduled e-mail of any report other than the Manager Flash (no per-report subscriptions, no `rpt_subscriptions` table).
- Any scheduled Trial Balance e-mail.
- The legacy `daily:brief-report` (06:00) sends SMS only.

---

## 7. Fandaqah PMS MCP server

**Not present in this repository.** No MCP server, transport, tool or resource definitions exist in the PMS code (`app/`, `routes/`, `config/`, `packages/`, `nova-components/`). The repository's `.mcp.json` only configures MySQL read tools for developers.

- A connector named "Fandaqah PMS" appears in the claude.ai connector list of a Fandaqah account, so an MCP server may be hosted elsewhere (fandaqah_api is the likely place, as it already serves external APIs). Its tools could not be listed during this review because the connector was not authorized in the session. **Ask the fandaqah_api team for its URL, authentication and tool list.**
- Without an MCP server, the options for pulling structured data are:
  1. **E-mail (works today):** Manager Flash PDF, only for Night Audit v2 hotels with the mail switched on.
  2. **A new token-protected report endpoint in the PMS:** wrap the existing `ReportRunner::runForTeam($report, $params, $teamId, 'snapshot')` + `ScreenRenderer` (exactly what produced the JSON samples here) behind a Passport client-credentials or per-group token, scoped to an explicit list of hotel ids. The JSON shape in §5.5 would not change.
  3. **Read replica:** the `rpt_*` locked-fact tables and `ledger_*` tables, as fandaqah_api does with `owner_ro`.
- Multi-property addressing today: by `teams.id`. Rate limits: Reports Plus run/export endpoints allow 20–30 requests per minute per user.

---

## 8. UI and design system

### 8.1 Layout

- Shell: `resources/views/vendor/nova/layout.blade.php` (`dir="rtl"` for Arabic).
- **Left sidebar** (`w-sidebar bg-grad-sidebar`, gradient `#22292f → #3c434c`): hotel logo, quick links (New Booking, New Group Booking, Support, Settings), then one link per Nova tool.
- **Top bar** (`h-header bg-black`): `<fq-top-band>`, global search, the **business date chip**, notification bell, user menu (with the hotel switcher).
- Module pages share a "fq-" layer: `.fq-header` (page header), `.fq-card`, buttons, tables, copied per module (`nova-components/<Module>/resources/js/theme.css`, "keep them in step").
- **No dark mode** anywhere in the PMS UI (the public POS QR menu is the only page with `prefers-color-scheme: dark`).

### 8.2 Two colour systems

1. **Legacy PMS chrome** (most screens), `nova-components/Theme/resources/css/theme.css` `:root`:

```css
--black: #22292f; --danger: #e74444; --success: #21b978; --warning: #ffeb3b; --info: #03a9f4;
--primary: #4099de; --primary-dark: #297ec0; --logo: #252d37; --sidebar-icon: #b3c1d1;
--20: #f6fbff; --30: #f4f7fa; --40: #eef1f4; --50: #e3e7eb; --60: #bacad6; --70: #b3b9bf; --80: #7c858e; --90: #252d37;
```

2. **Fandaqah identity (navy / coral)**, used by the new dashboards (main dashboard, Reports Plus dashboards, group dashboard, report charts) and the Owner App. **This is the one FGRE should use.** Source of truth in code: `mobile/owner-app/src/theme.js` ("docs/Fandaqah_Mobile_App_Identity.pdf … five official swatches") and `nova-components/DashboardUnits/resources/js/components/dashboard/_dashboard.scss`:

| Token | Hex | Use |
|---|---|---|
| navy | `#262E43` | primary dark, text, headline cards, top band |
| navy-light | `#3A4360` | |
| navy-deep | `#1C2233` | |
| coral | `#F15F5A` | brand accent, highlights |
| coral-text | `#D2443F` | coral text on light backgrounds |
| coral-tint | `#FDE8E6` | |
| salmon | `#F58985` | pattern / chart colour |
| peach | `#FCC5A9` | soft accent |
| off-white | `#F8F7F4` | page background |
| warm | `#EDEBE6` | track / surface |
| border | `#ECE9E3` | |
| grey | `#889594` | official swatch, muted |
| secondary | `#5B6374` | secondary text |
| muted | `#687170` (`#76807F` in charts) | |
| success / soft | `#4C6B67` / `#E4ECEA` | |
| danger / soft | `#B73530` / `#FBE1DF` | |
| teal (pattern) | `#214F5F` | |
| ice (pattern) | `#B8D7D9` | |

Chart series palette (`charts/theme.js`): `['#262E43', '#F15F5A', '#889594', '#FCC5A9', '#5E6886', '#F58985', '#C3CBCA', '#D8D4CC']`. Top band gradient: `linear-gradient(160deg, #2C354D 0%, #262E43 55%, #222A3D 100%)`. Group dashboard CSS variables (`PmsReports/resources/css/group-dashboard.css`): `--gd-navy: #262e43; --gd-coral: #f15f5a; --gd-coral-tint: #fde8e6; --gd-peach: #fcc5a9; --gd-soft: #f8f7f4; --gd-warm: #edebe6`.

**FGRE's `docs/BRAND.md` values were sampled from the PDF and differ slightly.** Replace them with the code values above: navy `#252D43` → `#262E43`, ivory `#F7F7F3` → `#F8F7F4`, slate `#8A9593` → `#889594`, peach `#FAC6A8` → `#FCC5A9`, teal `#224F60` → `#214F5F`, ice `#B8DADB` → `#B8D7D9`, salmon `#F48A86` → `#F58985`. Coral `#F15F5A` matches.

### 8.3 Fonts and logo

- **Brand fonts (new dashboards):** El Messiri (titles), Montserrat (body), Almarai (Arabic), loaded from Google Fonts at runtime:

```js
// nova-components/DashboardUnits/resources/js/components/dashboard/format.js
link.href = 'https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&family=El+Messiri:wght@500;600;700&family=Montserrat:wght@400;500;600;700&display=swap'
```

```scss
// _dashboard.scss
font-family: 'Montserrat', 'Almarai', system-ui, ...;          // body
font-family: 'El Messiri', 'Almarai', Georgia, serif;          // titles
```

- The rest of the PMS uses **Dubai** (`public/fonts/dubai/`, `Dubai-Regular/Medium/Bold`) and Nunito.
- Logo: `public/images/logo.svg` (official). Inline version `DashLogo.vue`: mark `#f95959`, dot `#879693`, letters `#23313f` (white on navy). Also `public/fandaqah_logo.svg` / `.png`, favicons in `public/images/icons/`, the Saudi riyal symbol `public/sar_symbol.svg`.
- Identity guides: `docs/Fandaqah Identity.pdf`, `docs/Fandaqah_Mobile_App_Identity.pdf`.

### 8.4 Components, dashboards and charts

- Chart kit (hand-written SVG, Vue 2): `nova-components/DashboardUnits/resources/js/components/charts/` — `OaBarChart`, `OaLineChart`, `OaDonut`, `OaGauge` (half dial, navy-to-coral arc on a warm track), `OaHBarList`, `OaKpi`, `OaSegmentBar`, `OaSparkline`, `OaCard`.
- Dashboard building blocks: `DashHero`, `DashTiles`, `DashTables`, `DashCard`, `DashSecNav` (main dashboard, `/home/dashboards/main`); `DashboardPage`, `DashWidget`, `DashDataTable`, `DashHeatmap`, `SubscribeModal` (Reports Plus dashboards).
- **Group dashboard** (closest to FGRE): `nova-components/PmsReports/resources/js/components/group-dashboard/` — `GroupDashboard`, `RankBars`, `QuadrantChart`, `PaceHeatmap`, `MixBars`, `DonutChart`, `LineChart`, `palette.js`.
- Report screens: `ReportTable`, `FiltersPanel`, `Pager`, `ExportButtons`, `PageHeader` (`nova-components/PmsReports/resources/js/components/`).
- Shared legacy components (`nova-components/Common`): `DateRangePicker` (v-calendar), `Loader`, `AlertComponent`, `CountriesSelect`. Modals: `sweet-modal-vue`, sweetalert2. Date pickers: v-calendar, flatpickr.
- Module bundles cannot share code, so each module copies its components ("decision O1").

---

## 9. Appendix

### 9.1 Tenancy configuration, middleware and base trait

- Tenancy config: **not present**; the Spark configuration in §2.1 is the equivalent.
- Tenant identification: no tenant middleware; the scope reads `auth()->user()->current_team_id` (§2.3). Spark `VerifyUserHasTeam` (redirect to `/settings/hotel/missing`) and `HasManageTeam` are the only team middlewares.
- Base trait: `BelongsToCurrentTeam` + `TeamScope`, full text in §2.7.

### 9.2 Sample reports (`docs/samples/fandaqah-pms/`)

Rendered on 2026-11-21 through the same code path the Night Audit e-mail uses (`ReportRunner::runForTeam(…, 'pdf')` + `PdfRenderer::store()`), for the PMS's local **Live Demo hotel** (team 2411, "Address Hotel" / "فندق العنوان", demo data only, no guest names). The demo hotel is not on Night Audit v2, so the Manager Flash and FIN-040 samples carry the **PROVISIONAL** line that a real e-mail never has.

| File | Report | Notes |
|---|---|---|
| `fandaqah-sample-manager-flash-20261119-en.pdf` / `-ar.pdf` | Manager Flash (EOD-016), 19 Nov 2026, last year, net | 2 pages, Letter portrait |
| `fandaqah-sample-manager-flash-20261119-en.json` | the same report as screen JSON | full payload of §5.5 |
| `fandaqah-sample-trial-balance-20261119-en.pdf` / `-ar.pdf` | FIN-040 front-office trial balance, 19 Nov 2026 | |
| `fandaqah-sample-fin-trial-balance-202611-en.pdf` / `-ar.pdf` | FST-001 general-ledger trial balance, 1–19 Nov 2026 | charts pages first, table on the last page |
| `fandaqah-sample-fin-trial-balance-202611-en.json` | the same as screen JSON | |

The FST-001 sample needed general-ledger data the demo hotel does not have: the NTMP chart of accounts and seven balanced demo journals were inserted inside a database transaction, the report was rendered, and the transaction was rolled back. The header's address, phone, e-mail, VAT and CR numbers are placeholders.

### 9.3 Roles and permissions seeding

`app/Team.php`, `boot()` → `static::created` (the "seeder"; there is no database seeder for roles):

```php
static::created(function ($query) {
    $role = new \App\Role();
    $role->name = 'Admin';
    $role->slug = 'admin';
    $role->deletable = 0;
    $role->team_id = $query->id;
    $role->save();
    foreach (config('novapermissions.permissions') as $key => $permissions) {
        // the owner app screens live on the editable Owner App role, not on Admin
        if (!OwnerAppRole::isOwnerAppPermission($key)) {
            $role->grant($key);
        }
    }
    $user = User::find($query->owner_id);
    $user->roles()->attach($role);

    $staff = new \App\Role();
    $staff->name = 'Staff';
    $staff->slug = 'staff';
    $staff->team_id = $query->id;
    $staff->deletable = 0;
    $staff->save();
    $excluded_permissions = array_merge(
        ['view settings', 'view reports', 'view financial', 'purchase shomoos credits', 'view shomoos invoices'],
        OperationsRoles::permissionsExcludedFromStaff(),
        OwnerAppRole::permissions()
    );
    foreach (config('novapermissions.permissions') as $key => $permissions) {
        if (!in_array($key, $excluded_permissions)) {
            $staff->grant($key);
        }
    }

    $housekeeping = new \App\Role();
    $housekeeping->name = 'Housekeeping';
    $housekeeping->slug = 'housekeeping';
    $housekeeping->team_id = $query->id;
    $housekeeping->deletable = 0;
    $housekeeping->save();
    $housekeeping->grant('view reservations');

    OperationsRoles::seedForTeam($query->id);   // housekeeping / maintenance / storekeeper roles
    OwnerAppRole::seedForTeam($query->id);      // what the owner sees in the owner mobile app
    // ... 15-day trial
});
```

Permissions added later reach existing hotels through a grant migration, e.g. `database/migrations/2026_10_22_100020_grant_pms_reports_permissions.php` (insert the new slugs into `role_permission` for every role with `slug = 'admin'`, skipping existing rows).

### 9.4 One complete feature: Purchasing › create supplier

**Route** (`nova-components/Purchasing/routes/api.php`, mounted by `ToolServiceProvider` under `nova-vendor/purchasing` with `['nova', Authorize::class]`):

```php
// suppliers are inv_suppliers (shared with Inventory); deactivated, never deleted
Route::get('suppliers', 'SupplierController@index');
Route::post('suppliers', 'SupplierController@store');
Route::get('suppliers/{id}', 'SupplierController@show')->where('id', '[0-9]+');
Route::put('suppliers/{id}', 'SupplierController@update')->where('id', '[0-9]+');
```

**Controller** (`nova-components/Purchasing/src/Http/Controllers/SupplierController.php`):

```php
public function store(SaveSupplierRequest $request)
{
    return $this->attempt(function () use ($request) {
        $supplier = $this->suppliers->save($request->details(), null, $request->input('idempotency_key'));

        return (new SupplierResource($supplier))->response()->setStatusCode(201);
    });
}
```

**Request** (`app/Http/Requests/Purchasing/SaveSupplierRequest.php`; the base `PurchasingRequest::authorize()` checks `$permission` through `PurchasingPermissions::any()`):

```php
class SaveSupplierRequest extends PurchasingRequest
{
    protected $permission = 'pur manage suppliers';

    public function rules()
    {
        return [
            'name'               => $this->requiredOnCreate() . '|string|max:255',
            'name_ar'            => 'nullable|string|max:255',
            'code'               => 'nullable|string|max:30',
            'email'              => 'nullable|email|max:255',
            'vat_number'         => ['nullable', 'string', new SaudiVatNumber()],
            'cr_number'          => 'nullable|regex:/^\d{1,20}$/',
            'iban'               => 'nullable|string|max:50',
            'payment_terms_days' => 'nullable|integer|min:0|max:365',
            'idempotency_key'    => 'nullable|string|max:64',
            // ...
        ];
    }

    /** The supplier fields that were sent, plus the write-only IBAN inputs. */
    public function details()
    {
        return $this->sent(array_merge(SupplierService::FIELDS, ['iban', 'clear_iban']));
    }
}
```

**Service** (`app/Services/Purchasing/SupplierService.php`):

```php
public function save(array $data, Supplier $supplier = null, $key = null)
{
    $teamId = $this->teamId();

    if ($supplier) {
        return DB::transaction(function () use ($data, $supplier, $teamId) {
            $locked = Supplier::whereKey($supplier->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new PurchasingException(__('The supplier was not found.'));
            }
            return $this->write($locked, $data, $teamId);
        });
    }

    return $this->createOnce(Supplier::class, $teamId, $key, function () use ($data, $teamId, $key) {
        return DB::transaction(function () use ($data, $teamId, $key) {
            $supplier = new Supplier();
            $supplier->team_id = $teamId;
            $supplier->idempotency_key = $key;
            $supplier->is_active = true;
            $supplier->country_code = 'SA';
            return $this->write($supplier, $data, $teamId);   // validates code / VAT uniqueness per team, encrypts the IBAN
        });
    });
}
```

**Model:** `App\Inventory\Supplier` (table `inv_suppliers`, shared with Inventory), team-scoped through `BelongsToCurrentTeam`, activity-logged with the IBAN ignored.

**Resource** (`app/Http/Resources/Purchasing/SupplierResource.php`):

```php
public function toArray($request)
{
    return [
        'id'           => $this->id,
        'code'         => $this->code,
        'name'         => $this->name,
        'name_ar'      => $this->name_ar,
        'display_name' => app()->getLocale() === 'ar' && $this->name_ar ? $this->name_ar : $this->name,
        'vat_number'   => $this->vat_number,
        'has_iban'     => (bool) $this->iban_last4,
        'iban_masked'  => $this->resource->maskedIban(),
        'created_at'   => optional($this->created_at)->toDateTimeString(),
        'can'          => [
            'edit'        => PurchasingPermissions::has('pur manage suppliers'),
            'reveal_iban' => (bool) $this->iban_last4
                && PurchasingPermissions::any(['pur manage suppliers', 'pur record payments']),
        ],
        // ...
    ];
}
```

**Test** (`tests/Feature/Purchasing/SupplierTest.php`, base `PurchasingTestCase`, disposable MySQL database, rolled-back transaction):

```php
class SupplierTest extends PurchasingTestCase
{
    const VAT = '310122393500003';

    protected function setUp(): void
    {
        parent::setUp();
        $this->bypassNovaMiddleware();
    }

    public function test_vat_number_format_and_uniqueness_per_team()
    {
        $this->assertPurchasingException(function () {
            $this->suppliers()->save(['name' => 'Bad VAT', 'vat_number' => '210122393500003']);
        }, 'VAT number must be 15 digits');

        $first = $this->fullSupplier();

        $this->assertPurchasingException(function () {
            $this->suppliers()->save(['name' => 'Copy', 'vat_number' => self::VAT]);
        }, 'already has the VAT number');

        // another hotel may register the same supplier
        list(, $other) = $this->createTeamWithUser(self::ALL_PERMISSIONS);
        $theirs = $this->runAs($other, function () {
            return $this->suppliers()->save(['name' => 'Same supplier elsewhere', 'vat_number' => self::VAT]);
        });
        $this->assertNotSame($this->teamId, (int) $theirs->team_id);
    }
}
```

HTTP-level example from the same suite (`tests/Feature/Purchasing/OrderFlowTest.php`):

```php
$response = $this->postJson('/nova-vendor/purchasing/orders', [
    'supplier_id' => $supplier->id, 'grand_total' => 1, 'idempotency_key' => 'amounts-1',
    'lines' => [['line_type' => 'item', 'item_id' => $item->id, 'qty' => 3, 'unit_cost' => 33.33, 'discount_percent' => 10]],
])->assertStatus(201);
$this->assertSame('PO-000001', $response->json('data.number'));
```

### 9.5 What FGRE should adopt, adapt or avoid

| Topic | PMS | Recommendation for FGRE |
|---|---|---|
| Tenancy | `team_id` + global scope from the logged-in user | Same shape, one level up: a **group** tenant key on every business table plus `property_id`, scoped by a `BelongsToCurrentTeam`-style trait. Do not copy `HasTeam`. |
| Property identity | `teams.id`, no code | Store the PMS `teams.id` as the external property key for Fandaqah hotels; keep name and `en_name` for matching e-mails. |
| Permissions | lowercase-with-spaces slugs in one config file, per-tenant roles | Same convention and one config file (e.g. `fgre view group dashboard`). Decide deliberately whether a permission is per property or per group; the PMS applies a user's permission in every hotel they belong to. |
| Money | DECIMAL + bcmath, half away from zero | Same (FGRE already plans `numeric(18,2)` + currency). |
| KPIs | `KpiCalculator`, X-D13 | Implement the same formulas and recompute group ratios from summed parts. |
| Business date | Night Audit owns it; closed days frozen | Treat a Fandaqah day as final only when it was e-mailed (always closed) or `meta.provisional` is false. |
| Brand | navy / coral, El Messiri / Montserrat / Almarai | Use §8.2 values; no dark mode exists in the PMS to copy. |
