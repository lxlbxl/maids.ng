<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Validator;

class AdminWebhookController extends Controller
{
    public function index()
    {
        $webhooks = Webhook::latest()->get();
        
        $stats = [
            'total' => $webhooks->count(),
            'active' => $webhooks->where('is_active', true)->count(),
            // Mock stats for display purposes, would be tied to logs in a real system
            'total_deliveries' => 0, 
            'success_rate' => '0%',
        ];

        return Inertia::render('Admin/Webhooks', [
            'webhooks' => $webhooks,
            'stats' => $stats
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:255',
            'secret' => 'nullable|string|max:255',
            'events' => 'required|array|min:1',
            'events.*' => 'string',
            'timeout' => 'required|integer|min:1|max:120',
            'max_retries' => 'required|integer|min:0|max:10',
            'is_active' => 'boolean',
            'verify_ssl' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        Webhook::create($validator->validated());

        return back()->with('success', 'Webhook created successfully');
    }

    public function update(Request $request, Webhook $webhook)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:255',
            'secret' => 'nullable|string|max:255',
            'events' => 'required|array|min:1',
            'events.*' => 'string',
            'timeout' => 'required|integer|min:1|max:120',
            'max_retries' => 'required|integer|min:0|max:10',
            'is_active' => 'boolean',
            'verify_ssl' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $webhook->update($validator->validated());

        return back()->with('success', 'Webhook updated successfully');
    }

    public function destroy(Webhook $webhook)
    {
        $webhook->delete();
        
        return back()->with('success', 'Webhook deleted successfully');
    }

    public function toggle(Webhook $webhook)
    {
        $webhook->update(['is_active' => !$webhook->is_active]);
        
        return back()->with('success', 'Webhook status updated');
    }

    public function events()
    {
        $events = [
            ['id' => 'booking.created', 'label' => 'Booking Created', 'description' => 'Triggered when a new booking is created.'],
            ['id' => 'booking.updated', 'label' => 'Booking Updated', 'description' => 'Triggered when a booking is modified.'],
            ['id' => 'booking.cancelled', 'label' => 'Booking Cancelled', 'description' => 'Triggered when a booking is cancelled.'],
            ['id' => 'user.created', 'label' => 'User Created', 'description' => 'Triggered when a new user signs up.'],
            ['id' => 'user.updated', 'label' => 'User Updated', 'description' => 'Triggered when a user profile is updated.'],
            ['id' => 'maid.created', 'label' => 'Maid Created', 'description' => 'Triggered when a new maid profile is created.'],
            ['id' => 'payment.success', 'label' => 'Payment Success', 'description' => 'Triggered on successful invoice payments.'],
            ['id' => 'payment.failed', 'label' => 'Payment Failed', 'description' => 'Triggered when a payment attempt fails.'],
            ['id' => 'maid.hired', 'label' => 'Maid Hired', 'description' => 'Triggered when a maid is successfully hired.'],
            ['id' => 'assignment.completed', 'label' => 'Assignment Completed', 'description' => 'Triggered when an assignment is marked as completed.'],
            ['id' => 'assignment.cancelled', 'label' => 'Assignment Cancelled', 'description' => 'Triggered when an assignment is cancelled.'],
            ['id' => 'maid.replaced', 'label' => 'Maid Replaced', 'description' => 'Triggered when a maid is replaced in an active booking.'],
        ];

        return response()->json($events);
    }
}

