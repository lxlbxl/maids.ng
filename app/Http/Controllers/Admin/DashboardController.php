<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BusinessMetricsService;
use Inertia\Inertia;

/**
 * The business dashboard.
 *
 * It used to show seven totals — users, bookings, lifetime revenue — which
 * describe the size of the database rather than the state of the business.
 * Nothing on it answered whether requests were being filled, where customers
 * dropped out, or whether there was enough supply to serve demand.
 */
class DashboardController extends Controller
{
    public function index(BusinessMetricsService $metrics)
    {
        return Inertia::render('Admin/Dashboard', $metrics->all());
    }

    /** Same numbers as JSON, for polling and for anything outside Inertia. */
    public function metrics(BusinessMetricsService $metrics)
    {
        return response()->json(['success' => true, 'data' => $metrics->all()]);
    }
}
