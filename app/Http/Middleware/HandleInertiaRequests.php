<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'phone' => $request->user()->phone,
                    'roles' => $request->user()->getRoleNames(),
                ] : null,
            ],
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'message' => fn () => $request->session()->get('message'),
            ],
            'meta' => [
                'title'       => 'Maids.ng — Find Verified Domestic Staff in Nigeria',
                'description' => 'Nigeria\'s leading platform for finding verified housekeepers, nannies, cooks, and drivers. AI-matched. NIN-verified. 10-day guarantee.',
                'canonical'   => url()->current(),
            ],
            // Public app settings shared with ALL Inertia pages automatically.
            // Update rates in the Admin → Settings panel; no code changes needed.
            'appSettings' => fn () => [
                'matchingFee'          => (int) \App\Models\Setting::get('matching_fee_amount', 5000),
                'matchingFeeFormatted' => '₦' . number_format((int) \App\Models\Setting::get('matching_fee_amount', 5000)),
            ],
            'controlRoom' => function () {
                if (!auth()->check() || !auth()->user()->hasRole('admin')) {
                    return null;
                }
                return [
                    'pendingHitl' => \App\Models\HumanTask::pending()->count(),
                    'agentErrors' => \App\Models\AgentEvent::where('severity', 'error')
                        ->where('created_at', '>=', now()->subHour())
                        ->count(),
                ];
            },
        ];
    }
}
