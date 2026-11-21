<?php

/*
|--------------------------------------------------------------------------
| FGRE permissions and roles (single source of truth)
|--------------------------------------------------------------------------
|
| Same convention as Fandaqah PMS: lowercase slugs with spaces, one Gate
| ability per permission (registered in AppServiceProvider).
|
| Difference from the PMS, on purpose: a user's role applies only inside the
| group it was given in. Groups are separate customers, so a permission held
| in one group never counts in another.
|
| Roles are fixed (no per-group role editor yet). Add a permission here, then
| add it to the roles that should have it.
|
*/

return [

    'permissions' => [
        'view dashboard' => 'Open the group dashboard and KPIs',
        'view reports' => 'Open group operational reports (flash, occupancy, revenue)',
        'view financials' => 'Open trial balances and financial reports',
        'export reports' => 'Export reports to Excel / CSV / PDF',
        'upload reports' => 'Upload report PDFs manually',
        'review documents' => 'Review and resolve documents that failed checks',
        'manage properties' => 'Add and edit hotels, senders and aliases',
        'manage users' => 'Invite users and set their role and hotels',
    ],

    'roles' => [
        'owner' => ['*'],
        'finance' => [
            'view dashboard', 'view reports', 'view financials', 'export reports',
            'upload reports', 'review documents',
        ],
        'operations' => ['view dashboard', 'view reports', 'export reports', 'upload reports'],
        'viewer' => ['view dashboard', 'view reports'],
    ],

    // Fandaqah staff (users.is_platform_admin). Not a bypass of group permissions.
    'platform_permissions' => [
        'manage groups' => 'Create hotel groups and their owners',
        'review unmatched documents' => 'Resolve documents that could not be matched to any hotel',
    ],

];
