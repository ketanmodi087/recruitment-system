<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Job;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DashboardController extends Controller
{
    private $user;
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            return $next($request);
        });
    }
    public function totalAgency()
    {
        if ($this->user->hasPermissionTo('agency_total')) {
            $totalAgency = Agency::where('is_deleted', '!=', 1)->count();
            if ($totalAgency) {
                return response()->json([
                    'message' => 'Agency Count get successfully.',
                    'agencies' => $totalAgency,
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => "Sorry!! Couldn't get agency count.",
                    'status' => 422
                ], 422);
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ], 403);
        }
    }

    public function totalDashboardData()
    {
        $totalUsers = Agency::where('is_deleted', 0)->where('role_id', 1)->where('id', '!=', Auth::id())->count();
        $totalAgency  = Agency::where('is_deleted', 0)->where('role_id', 2)->count();
        $totalPayments  = Payment::sum('amount');
        if ($totalUsers) {
            return response()->json([
                'message' => 'Agency Count get successfully.',
                'totalUsers' => $totalUsers,
                'totalAgency' => $totalAgency,
                'totalPayments' => $totalPayments,
                'status' => 200
            ], 200);
        } else {
            return response()->json([
                'error' => "Sorry!! Not found agency count.",
                'status' => 200
            ], 200);
        }
    }

    public function subscriptionPlan(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if ($startDate && $endDate) {
            $agency['free'] = Agency::where('role_id', 2)->with(['payments' => function ($query) use ($startDate, $endDate) {
                $query->where('subscription_id', 0)
                    ->whereBetween('created_at', [$startDate, $endDate]);
            }])->whereHas('payments', function ($query) use ($startDate, $endDate) {
                $query->where('subscription_id', 0)
                    ->whereBetween('created_at', [$startDate, $endDate]);
            })->where('is_deleted', 0)->count();
        } else {
            $agency['free'] = Agency::where('role_id', 2)->with(['payments' => function ($query) {
                $query->where('subscription_id', 0)
                    ->whereBetween('created_at', [now()->subMonth(), now()]);
            }])->whereHas('payments', function ($query) {
                $query->where('subscription_id', 0)
                    ->whereBetween('created_at', [now()->subMonth(), now()]);
            })->where('is_deleted', 0)->count();
        }

        if ($startDate && $endDate) {
            $agency['expired'] = Agency::where('role_id', 2)->with(['payments' => function ($query) use ($startDate, $endDate) {
                $query->whereDate('expiry_date', '<', now())
                    ->whereBetween('created_at', [$startDate, $endDate]);
            }])->whereHas('payments', function ($query) use ($startDate, $endDate) {
                $query->whereDate('expiry_date', '<', now())
                    ->whereBetween('created_at', [$startDate, $endDate]);
            })->where('is_deleted', 0)->count();
        } else {
            $agency['expired'] = Agency::where('role_id', 2)->with(['payments' => function ($query) {
                $query->whereDate('expiry_date', '<', now())
                    ->whereBetween('created_at', [now()->subMonth(), now()]);
            }])->whereHas('payments', function ($query) {
                $query->whereDate('expiry_date', '<', now())
                    ->whereBetween('created_at', [now()->subMonth(), now()]);
            })->where('is_deleted', 0)->count();
        }

        if ($startDate && $endDate) {
            $agency['current'] = Agency::where('role_id', 2)->with(['payments' => function ($query) use ($startDate, $endDate) {
                $query->where('start_date', '<=', now())
                    ->where('expiry_date', '>=', now())
                    ->where('subscription_id', '!=', 0)
                    ->whereBetween('created_at', [$startDate, $endDate]);
            }])->whereHas('payments', function ($query) use ($startDate, $endDate) {
                $query->where('start_date', '<=', now())
                    ->where('expiry_date', '>=', now())
                    ->where('subscription_id', '!=', 0)
                    ->whereBetween('created_at', [$startDate, $endDate]);
            })->where('is_deleted', 0)->count();
        } else {
            $agency['current'] = Agency::where('role_id', 2)->with(['payments' => function ($query) {
                $query->where('start_date', '<=', now())
                    ->where('expiry_date', '>=', now())
                    ->where('subscription_id', '!=', 0)
                    ->whereBetween('created_at', [now()->subMonth(), now()]);
            }])->whereHas('payments', function ($query) {
                $query->where('start_date', '<=', now())
                    ->where('expiry_date', '>=', now())
                    ->where('subscription_id', '!=', 0)
                    ->whereBetween('created_at', [now()->subMonth(), now()]);
            })->where('is_deleted', 0)->count();
        }

        $totalAgency = $agency['free'] + $agency['expired'] + $agency['current'];

        $agency['free'] = $agency['free'];
        $agency['expired'] = $agency['expired'];
        $agency['current'] = $agency['current'];
        $agency['totalAgency'] = $totalAgency;
        if ($agency) {
            return response()->json([
                'message' => 'Agency Count get successfully.',
                'agency' => $agency,
                'status' => 200
            ], 200);
        } else {
            return response()->json([
                'error' => "Sorry!! Couldn't get agency count.",
                'status' => 422
            ], 422);
        }
    }
    public function twelveMonthGraphChart(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'date|before_or_equal:today',
            'end_date' => 'date|before_or_equal:today',
        ]);

        if ($validator->fails()) {
            // Validation failed
            return response()->json([
                'error' => $validator->errors()->all(),
                'status' => 422
            ], 422);
        } else {
            $startDate = $request->filled('start_date') ? Carbon::createFromFormat('Y-m-d', $request->start_date) : Carbon::now()->subDays(30)->startOfDay();
            $endDate = $request->filled('end_date') ? Carbon::createFromFormat('Y-m-d', $request->end_date) : Carbon::now()->endOfDay();

            $dates = $this->generateDateRange($startDate, $endDate);
            $data = Payment::select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(amount) as amount'))
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('date')
                ->get()
                ->keyBy('date')
                ->map(function ($item) {
                    return $item->amount ? $item->amount : 0;
                });

            $mergedData = collect($dates)->merge($data)->toArray();
            if (!empty($mergedData)) {
                return response()->json([
                    'message' => 'Applications Count retrieved successfully.',
                    'totalApplications' => $mergedData,
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'message' => "No Records found",
                    'status' => 200
                ]);
            }
        }
    }
    private function generateDateRange($startDate, $endDate)
    {
        $dates = [];
        for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
            $dates[$date->format('Y-m-d')] = 0;
        }

        return $dates;
    }


    public function signUpAgencyPerMonth(Request $request)
    {

        $startDate = request('start_date');
        $endDate = request('end_date');

        // If start_date and end_date are not provided, calculate the start and end dates for last month
        if (!$startDate && !$endDate) {
            $startDate = Carbon::now()->subDays(30)->startOfDay();
            $endDate = Carbon::now()->endOfDay();
        }

        // Generate dates between start_date and end_date
        $dates = [];
        $currentDate = Carbon::parse($startDate);

        while ($currentDate->lte($endDate)) { // use lte() instead of <= to include the end date
            $dates[] = $currentDate->copy();
            $currentDate->addDay();
        }
        // Fetch count of agencies for each date within the date range
        $agenciesByDate = Agency::select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date', 'asc')
            ->get()
            ->keyBy('date');

        // Merge the counts with the generated dates
        $result = collect($dates)->map(function ($date) use ($agenciesByDate) {
            return  [$date->format('Y-m-d') => isset($agenciesByDate[$date->format('Y-m-d')]) ? $agenciesByDate[$date->format('Y-m-d')]->count : 0];
        });

        if ($result) {
            return response()->json([
                'message' => 'Agency Signup per month get successfully.',
                'agency' => $result,
                'status' => 200
            ], 200);
        } else {
            return response()->json([
                'error' => "Sorry!! Couldn't get agency count.",
                'status' => 422
            ], 422);
        }
    }
}
