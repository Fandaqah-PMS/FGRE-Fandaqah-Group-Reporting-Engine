<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\GroupRole;
use App\Support\RolePermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Roles & permissions of the current group. Anyone who manages users can see the matrix;
 * only owners can change it (it decides who can manage users). The owner role is fixed.
 */
class RolesController extends Controller
{
    public function index(Request $request, RolePermissions $permissions): Response
    {
        Gate::authorize('manage users');
        $groupId = $request->user()->current_group_id;

        $counts = DB::table('group_user')->where('group_id', $groupId)->selectRaw('role, count(*) as n')->groupBy('role')->pluck('n', 'role');
        $custom = GroupRole::where('group_id', $groupId)->pluck('role')->all();

        return Inertia::render('Settings/Roles', [
            'roles' => collect(RolePermissions::roles())->map(fn (string $role) => [
                'key' => $role,
                'permissions' => $permissions->for($groupId, $role),
                'defaults' => RolePermissions::defaults($role),
                'members' => (int) ($counts[$role] ?? 0),
                'fixed' => $role === RolePermissions::FIXED_ROLE,
                'customized' => in_array($role, $custom, true),
            ])->values(),
            'categories' => collect(config('fgre_permissions.categories'))->map(fn ($keys, $label) => [
                'label' => $label,
                'permissions' => collect($keys)->map(fn ($k) => ['key' => $k, 'description' => config("fgre_permissions.permissions.{$k}")])->values(),
            ])->values(),
            'canEdit' => $request->user()->roleIn() === RolePermissions::FIXED_ROLE,
        ]);
    }

    public function update(Request $request, string $role, RolePermissions $permissions): RedirectResponse
    {
        $this->authorizeEdit($request, $role);
        $groupId = $request->user()->current_group_id;

        $data = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::in(RolePermissions::all())],
        ]);
        $new = array_values(array_intersect(RolePermissions::all(), $data['permissions']));
        $old = $permissions->for($groupId, $role);

        // Back to the defaults = no override row, so later default changes still apply.
        if ($new === RolePermissions::defaults($role)) {
            GroupRole::where(['group_id' => $groupId, 'role' => $role])->delete();
        } else {
            GroupRole::updateOrCreate(['group_id' => $groupId, 'role' => $role], ['permissions' => $new]);
        }
        $permissions->forget($groupId);

        AuditLog::record('role.updated', $role, [
            'added' => array_values(array_diff($new, $old)),
            'removed' => array_values(array_diff($old, $new)),
        ]);

        return back()->with('status', __('Permissions for :role saved.', ['role' => __("role_{$role}")]));
    }

    public function reset(Request $request, string $role, RolePermissions $permissions): RedirectResponse
    {
        $this->authorizeEdit($request, $role);
        $groupId = $request->user()->current_group_id;

        GroupRole::where(['group_id' => $groupId, 'role' => $role])->delete();
        $permissions->forget($groupId);
        AuditLog::record('role.reset', $role);

        return back()->with('status', __('Permissions for :role are back to the defaults.', ['role' => __("role_{$role}")]));
    }

    private function authorizeEdit(Request $request, string $role): void
    {
        Gate::authorize('manage users');
        abort_unless($request->user()->roleIn() === RolePermissions::FIXED_ROLE, 403);
        abort_unless(in_array($role, RolePermissions::roles(), true) && $role !== RolePermissions::FIXED_ROLE, 404);
    }
}
