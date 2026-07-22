<?php

namespace App\Http\Controllers\Api\Agency;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Application;
use App\Models\Customer;
use App\Models\Job;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AgencyDashboardController extends Controller
{
    public function totalAplicationPerDay(Request $request)
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
            $recruiterIds = Agency::where('agency_id', Auth::id())->pluck('id')->toArray();

            $data = Application::select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
                ->where(function ($q) use ($recruiterIds) {
                    $q->where('created_by', intval(Auth::id()))
                        ->orWhere('created_by', Auth::user()->agency_id)
                        ->orWhere('recruiter_id', Auth::id())
                        ->orWhereIn('created_by', $recruiterIds)
                        ->orWhereIn('recruiter_id', $recruiterIds); // All recruiters under the agency
                    // All recruiters under the agency

                })
                // ->where('created_by', intval(Auth::id()))
                // ->orWhere('created_by', Auth::user()->agency_id) // add this line 
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('date')
                ->get()
                ->keyBy('date')
                ->map(function ($item) {
                    return $item->count;
                });

            if ($data->isNotEmpty()) {
                return response()->json([
                    'message' => 'Applications Count retrieved successfully.',
                    'totalApplications' => collect($dates)->merge($data)->toArray(),
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'message' => "No Records found",
                    'totalApplications' => collect($dates)->merge($data)->toArray(),
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

    public function jobTotalJobsCustomers(Request $request)
    {
        $recruiterIds = Agency::where('agency_id', Auth::id())->pluck('id')->toArray();

        $query = Customer::select('customers.name')
            ->selectRaw('COUNT(jobs.id) as job_count')
            ->leftJoin('jobs', 'customers.id', '=', 'jobs.customer_id')
            ->groupBy('customers.id', 'customers.name')
            ->where(function ($q) use ($recruiterIds) {
                $q->where('customers.created_by', intval(Auth::id()))
                    ->orWhere('customers.created_by', Auth::user()->agency_id)
                    ->orWhereIn('customers.created_by', $recruiterIds); // All recruiters under the agency

            })

            // ->where('customers.created_by', intval(Auth::id()))
            // ->orWhere("customers.created_by", Auth::user()->agency_id) // add this line 
            ->where('customers.is_deleted', 0)
            ->orderBy('customers.created_at', 'desc')
            ->take(10);

        if ($request->start_date && $request->end_date) {
            $query->whereBetween('customers.created_at', [$request->start_date, $request->end_date]);
        }

        $customers = $query->get()->map(function ($customer) {
            return [
                'customer' => $customer->name,
                'job_count' => $customer->job_count,
            ];
        });

        if ($customers->isNotEmpty()) {
            return response()->json([
                'message' => 'Jobs and Customers Count retrieved successfully.',
                'totalJobsCustomers' => $customers,
                'status' => 200
            ], 200);
        } else {
            return response()->json([
                'message' => "No Records found",
                'totalJobsCustomers' => $customers,
                'status' => 200
            ]);
        }
    }
    public function totalApplicationStatus(Request $request)
    {
        $recruiterIds = Agency::where('agency_id', Auth::id())->pluck('id')->toArray();

        // Determine date range based on request parameters or last month
        $startDate = $request->start_date ? $request->start_date : now()->subMonth();
        $endDate = $request->end_date ? $request->end_date : now();

        // Fetch applications based on date range and user's applications or candidate apps
        $applications = Application::with('job.country')
            ->where('is_deleted', 0)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where(function ($query) use ($recruiterIds) {
                $query->where('created_by', intval(Auth::id()))
                    ->orWhereIn('job_id', function ($query) use ($recruiterIds) {
                        $query->select('id')->from('jobs')->where('created_by', Auth::id())
                            ->orWhere('created_by', Auth::user()->agency_id)
                            ->orWhereIn('created_by', $recruiterIds)
                            ->orWhereIn('recruiter_id', $recruiterIds); // All recruiters under the agency
                        // added this line 
                    });
            })
            ->orderBy('created_at', 'desc')
            ->get();

        // Count the number of applications for each status
        $statusCounts = $applications->groupBy('status')->map->count();

        if ($statusCounts->isNotEmpty()) {
            return response()->json([
                'message' => 'Application status retrieved successfully.',
                'totalApplicationsStatus' => $statusCounts,
                'status' => 200
            ], 200);
        } else {
            return response()->json([
                'error' => "Sorry! Couldn't retrieve application status.",
                'status' => 200,
            ]);
        }
    }
}
