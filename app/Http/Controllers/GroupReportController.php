<?php

namespace App\Http\Controllers;

use App\Reports\Kpi\GroupKpiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Hotels side by side: every Manager Flash figure, one row per hotel, plus the group total. */
class GroupReportController extends Controller
{
    public function __invoke(Request $request, GroupKpiService $kpis, ManagerFlashController $flash): Response
    {
        Gate::authorize('view reports');

        return Inertia::render('GroupReport', $flash->data($request, $kpis));
    }
}
