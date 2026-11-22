<?php

namespace App\Http\Middleware;

use App\Enums\DocumentStatus;
use App\Models\Property;
use App\Models\ReportDocument;
use App\Support\HotelScope;
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
                'user' => ['name' => $user->name, 'email' => $user->email, 'theme' => $user->theme ?? 'system'],
                'groups' => $user->groups->map(fn ($g) => ['id' => $g->id, 'name' => $g->name, 'name_ar' => $g->name_ar])->values(),
                'currentGroupId' => $user->current_group_id,
                'role' => $user->roleIn(),
                'can' => collect(array_keys(config('fgre_permissions.permissions')))
                    ->mapWithKeys(fn (string $p) => [$p => $user->hasGroupPermission($p)]),
            ],
            'reviewCount' => fn () => $user?->hasGroupPermission('review documents')
                ? ReportDocument::where('status', DocumentStatus::NeedsReview)->whereIn('property_id', $user->accessiblePropertyIds())->count()
                : 0,
            // Hotel switch: the hotels the user may see, and the selected one (null = all).
            'scope' => fn () => $user?->current_group_id === null ? null : [
                'current' => HotelScope::selected($request),
                'hotels' => Property::with('pmsProvider:id,name')
                    ->whereIn('id', $user->accessiblePropertyIds())
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Property $p) => [
                        'id' => $p->id, 'name' => $p->name, 'name_ar' => $p->name_ar,
                        'pms' => $p->pmsProvider?->name, 'rooms' => $p->total_rooms,
                    ])->values(),
            ],
        ];
    }

    /** @return array<string, string> */
    private function translations(string $locale): array
    {
        $file = lang_path("{$locale}.json");

        return is_file($file) ? json_decode(file_get_contents($file), true) : [];
    }
}
