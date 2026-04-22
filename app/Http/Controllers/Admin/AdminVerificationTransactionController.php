<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Illuminate\Http\Request;

class AdminVerificationTransactionController extends Controller
{
    public function index() { return Inertia::render('Admin/VerificationTransactions'); }
    public function show($id) { return Inertia::render('Admin/VerificationTransactionDetail', ['id' => $id]); }
    public function update($id, Request $request) { return back()->with('success', 'Updated.'); }
    public function updatePricing(Request $request) { return back()->with('success', 'Pricing updated.'); }
    public function export() { return response()->json(['message' => 'Export in progress']); }
    public function stats() { return response()->json([]); }
}
