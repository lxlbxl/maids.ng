<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\{Cache, DB};

class SeoStatsController extends Controller
{
    public function stats(): JsonResponse
    {
        $stats = Cache::remember('seo_public_stats', 21600, function () {
            $totalMaids = 0;
            $totalEmployers = 0;
            $totalMatches = 0;
            $ninVerified = 0;

            if (DB::getConnection()->getPdo()) {
                try {
                    if (DB::table('users')->whereRaw("1=1")->exists()) {
                        $totalMaids = DB::table('users')
                            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                            ->where('roles.name', 'maid')
                            ->count();

                        $totalEmployers = DB::table('users')
                            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                            ->where('roles.name', 'employer')
                            ->count();
                    }

                    if (DB::table('matching_fee_payments')->whereRaw("1=1")->exists()) {
                        $totalMatches = DB::table('matching_fee_payments')
                            ->where('status', 'successful')
                            ->count();
                    }

                    if (DB::table('nin_verifications')->whereRaw("1=1")->exists()) {
                        $ninVerified = DB::table('nin_verifications')
                            ->where('status', 'approved')
                            ->count();
                    }
                } catch (\Throwable $e) {
                }
            }

            return [
                'total_maids_registered'     => $totalMaids,
                'total_employers_registered' => $totalEmployers,
                'total_successful_matches'   => $totalMatches,
                'nin_verified_staff'         => $ninVerified,
                'cities_covered'             => 3,
                'areas_covered'              => 80,
                'average_match_time_minutes' => 5,
                'guarantee_days'             => 10,
            ];
        });

        return response()->json($stats);
    }
}
