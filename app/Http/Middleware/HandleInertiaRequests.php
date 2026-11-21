<?php

namespace App\Http\Middleware;

use App\Enums\DocumentStatus;
use App\Models\ReportDocument;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        $user = $request->user();
        $locale = app()->getLocale();

        return [
            ...parent::share($request),
            'locale' => $locale,
            'dir' => $locale === 'ar' ? 'rtl' : 'ltr',
            'translations' => fn () => $this->translations($locale),
            'flash' => ['status' => fn () => $request->session()->get('status')],
            'auth' => fn () => $user === null ? null : [
                'user' => ['name' => $user->name, 'email' => $user->email],
                'groups' => $user->groups->map(fn ($g) => ['id' => $g->id, 'name' => $g->name, 'name_ar' => $g->name_ar])->values(),
                'currentGroupId' => $user->current_group_id,
                'role' => $user->roleIn(),
                'can' => collect(array_keys(config('fgre_permissions.permissions')))
                    ->mapWithKeys(fn (string $p) => [$p => $user->hasGroupPermission($p)]),
            ],
            'reviewCount' => fn () => $user?->hasGroupPermission('review documents')
                ? ReportDocument::where('status', DocumentStatus::NeedsReview)->count()
                : 0,
        ];
    }

    /** @return array<string, string> */
    private function translations(string $locale): array
    {
        $file = lang_path("{$locale}.json");

        return is_file($file) ? json_decode(file_get_contents($file), true) : [];
    }
}
