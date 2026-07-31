<?php

namespace App\Http\Controllers\Api\Agency;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Application;
use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    private $user;
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            return $next($request);
        });
    }

    public function reportsList(Request $request, $reportType)
    {
        if ($this->user->hasPermissionTo('reports_list')) {
            $perPage = $request->input('perPage');
            $page = $request->input('page');
            $columnName = $request->input('columnName') ? $request->input('columnName') : 'created_at';
            $type = $request->input('type') ? $request->input('type') : 'desc';
            if ($reportType === "general_report") {
                $data = $this->generalReport($page, $perPage, $columnName, $type);
                // return $this->downloadGeneralReport($data);
            } elseif ($reportType === "recruiter_by_status") {
                $data = $this->recruiterByStatus($page, $perPage, $columnName, $type);
                // return $this->downloadRecruiterByStatus($data);
            } elseif ($reportType === "job_opening") {
                $data = $this->jobOpening($page, $perPage, $columnName, $type);
                // return $this->downloadJobOpening($data);
            }
            if ($data->isNotEmpty()) {
                return response()->json([
                    'message' => 'All Reports get successfully.',
                    'data' => $data,
                    'status' => 200
                ]);
            } else {
                return response()->json([
                    'message' => "No Records found.",
                    'data' => $data,
                    'status' => 200
                ]);
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ],  403);
        }
    }

    public function generalReport($page, $perPage, $columnName, $type)
    {
        $candidateAppsQuery = Application::where('is_deleted', 0)
            ->whereNotIn('status', ['Candidate Pool'])
            ->orderBy('created_at', 'desc');

        $candidateAppsQuery->where(function ($query) {
            $query->where('created_by', Auth::id())
                ->orWhere('recruiter_id', Auth::id());
        });

        $candidateApps = $candidateAppsQuery->pluck('id');

        $applicationsQuery = Application::select('applications.created_at', 'applications.source', 'applications.status', 'applications.job_id', 'applications.recruiter_id', 'applications.source', 'applications.first_name', 'applications.last_name', DB::raw('DATE(applications.updated_at) as updated_date'), 'customers.name as customer_name') // Include customer name
            ->with(['job:title,id', 'job.customer', 'recruiter:first_name,id,last_name'])
            ->leftJoin('jobs', 'applications.job_id', '=', 'jobs.id')
            ->leftJoin('customers', 'jobs.customer_id', '=', 'customers.id') // Join customers table
            ->where('applications.is_deleted', 0)
            ->whereNotIn('applications.status', ['Candidate Pool'])
            ->whereIn('applications.created_by', [Auth::id(), 0]);

        if (Auth::user()->role_id != 2) {
            $applicationsQuery->orWhereIn('applications.recruiter_id', [Auth::id(), 0])->orWhereIn('applications.id', $candidateApps);
        }

        if ($page || $perPage) {
            if ($columnName === 'customer_id') {
                $columnName = 'customer_name';
            } elseif ($columnName === 'candidate_id') {
                $columnName = DB::raw("CONCAT(applications.first_name, ' ', applications.last_name)");
            }

            $applications = $applicationsQuery
                ->orderBy($columnName, $type)
                ->paginate($perPage, ['*'], 'page', $page);
        }
        return $applications;
    }

    public function recruiterByStatus($page, $perPage, $columnName, $type)
    {
        $recruitersQuery = Agency::with(['applications' => function ($query) {
            $query->select('recruiter_id', 'status', \DB::raw('count(*) as count'))
                ->groupBy('recruiter_id', 'status');
        }])
            ->selectRaw('id, CONCAT(first_name, " ", last_name) as name')
            ->where('is_deleted', 0)
            ->whereNotIn('role_id', [1, 2])
            ->withCount('applications');
        if (Auth::user()->role_id != 2) {
            $recruitersQuery->where('id', Auth::id());
        } else {
            $recruitersQuery->whereIn('created_by', [Auth::id(), 0]);
        }

        if ($page || $perPage) {
            if ($columnName === 'recruiter') {
                $columnName = 'name';
            }
            $recruiters = $recruitersQuery
                ->orderBy($columnName, $type)
                ->paginate($perPage, ['*'], 'page', $page);
        }
        return $recruiters;
    }

    public function jobOpening($page, $perPage, $columnName, $type)
    {
        $jobQuery = Job::select(
            'title',
            'customer_id',
            'recruiter_id',
            'category_id',
            'subcategory_id',
            'city',
            'country_id',
            DB::raw('DATE(created_at) as created_date')
        )
            ->with(['recruiter:id,first_name,last_name', 'customer:id,name', 'category:name,id', 'subcategory:name,id', 'country:name,id'])
            ->where('is_deleted', 0)
            ->where('created_by', Auth::id());

        if (Auth::user()->role_id != 2) {
            $jobQuery->orWhereIn('recruiter_id', [Auth::id(), 0]);
        }


        if ($page || $perPage) {
            $jobs = $jobQuery->orderBy($columnName, $type)->paginate($perPage, ['*'], 'page', $page);
            $encryptedJobs = $jobQuery->get()->map(function ($item, $key) {
                $item['eid'] = Crypt::encryptString($item['id']);
                return $item;
            });

            $paginatedJobs = new LengthAwarePaginator(
                $encryptedJobs,
                $jobs->total(),
                $jobs->perPage(),
                $jobs->currentPage(),
                ['path' => LengthAwarePaginator::resolveCurrentPath()]
            );
        }
        return $paginatedJobs;
    }

    public function downloadGeneralReport($data)
    {
        $fileName = date('Ymdhms') . '-general-report.csv';
        if (count($data) > 0) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/csv');
            header("Content-Disposition: attachment; filename=" . $fileName);
            header('Cache-Control: must-revalidate');
            header("Content-Transfer-Encoding: UTF-8");

            $columns = array(
                'Client',
                'Candidate',
                'Status',
                'Recruiter',
                'Source',
                'Last Updates',
            );

            $uploadDirectory = public_path() . '/uploads/report_csv/';
            if (!file_exists($uploadDirectory)) {
                mkdir($uploadDirectory, 0777, true); // Create the directory recursively
            }
            $uploadFile = $uploadDirectory . $fileName;

            $file = fopen($uploadFile, 'w');
            if (!$file) {
                return response()->json(['message' => 'Failed to create file.', 'status' => false], 500);
            }

            fputcsv($file, $columns);
            foreach ($data as $app) {
                if ($app->recruiter) {
                    $recruiter = $app->recruiter->first_name . ' ' . $app->recruiter->last_name;
                } else {
                    $recruiter = "";
                }
                $row['Client']      = $app->customer_name;
                $row['Candidate']   = $app->first_name . ' ' . $app->last_name;
                $row['Status']      = $app->status;
                $row['Recruiter']   = $recruiter;
                $row['Source']      = $app->source;
                $row['Last Updates'] = $app->updated_date;

                fputcsv($file, $row);
            }

            fclose($file);

            if (file_exists($uploadFile)) {
                $filePath = url('/uploads/report_csv/' . $fileName);
                return response()->json(['status' => true, 'message' => 'Report generated successfully.', 'data' => $filePath], 200);
            } else {
                return response()->json(['message' => 'Sorry, Report could not be generated!', 'status' => false], 200);
            }
        } else {
            return response()->json(['message' => 'Sorry, Report could not be generated!', 'status' => false], 200);
        }
    }



    public function downloadRecruiterByStatus($data)
    {
        $fileName = date('Ymdhms') . '-recruiter-by-status.csv';
        if (count($data) > 0) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/csv');
            header("Content-Disposition: attachment; filename=" . $fileName);
            header('Cache-Control: must-revalidate');
            header("Content-Transfer-Encoding: UTF-8");

            $columns = array(
                'Recruiter',
                'Total Application',
                'New',
                'Application Review',
                'Initial Screening',
                'Client Submission',
                'Interview By Client',
                'Offer Acceptance',
                'On Boarding',
                'Hired',
                'Candidate Pool'
            );

            $uploadFile = $fileName;

            $file = fopen($uploadFile, 'w');
            fputcsv($file, $columns);
            foreach ($data as $recruiter) {

                $row['Recruiter']    = $recruiter->name;
                $row['Total']    = $recruiter->applications_count;
                // $row['Ward Name']    = $recruiter->ward_name;
                $statusCounts = array(
                    'New' => 0,
                    'Application Review' => 0,
                    'Initial Screening' => 0,
                    'Client Submission' => 0,
                    'Interview By Client' => 0,
                    'Offer Acceptance' => 0,
                    'On Boarding' => 0,
                    'Hired' => 0,
                    'Candidate Pool' => 0,
                );

                // Update status-wise counts if available
                foreach ($recruiter['applications'] as $application) {
                    $status = $application['status'];
                    if (array_key_exists($status, $statusCounts)) {
                        $statusCounts[$status] += $application['count'];
                    }
                }

                // Add status-wise counts to row
                foreach ($statusCounts as $key => $count) {
                    $row[$key] = $count;
                }

                fputcsv($file, $row);
            }

            fclose($file);
            $filePath = url('/' . $uploadFile);
            rename(public_path() . '/' . $uploadFile, public_path() . '/uploads/report_csv/' . $uploadFile);
            $filePath = public_path() . '/uploads/report_csv/' . $uploadFile;
            $filePath =  url('/') . '/uploads/report_csv/' . $uploadFile;
            return response()->json(['status' => true, 'message' => 'Report generated successfully. ', 'data' => $filePath], 200);
        } else {
            return response()->json(['message' => 'Sorry, Report could not be generated!', 'status' => false], 200);
        }
    }

    public function downloadJobOpening($data)
    {
        $fileName = date('Ymdhms') . '-job-opening-report.csv';
        if (count($data) > 0) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/csv');
            header("Content-Disposition: attachment; filename=" . $fileName);
            header('Cache-Control: must-revalidate');
            header("Content-Transfer-Encoding: UTF-8");

            $columns = array(
                'Job Title',
                'Location',
                'Client',
                'Speciality',
                'Subspecialty',
                'Recruiter',
                'Date of Job Created',
            );

            $uploadFile = $fileName;

            $file = fopen($uploadFile, 'w');
            fputcsv($file, $columns);
            foreach ($data as $recruiter) {
                if ($recruiter->recruiter) {
                    if ($recruiter->recruiter->first_name && $recruiter->recruiter->first_name) {
                        $recruiterName = $recruiter->recruiter->first_name . '' . $recruiter->recruiter->last_name;
                    } elseif ($recruiter->recruiter->first_name) {
                        $recruiterName = $recruiter->recruiter->first_name;
                    } elseif ($recruiter->recruiter->last_name) {
                        $recruiterName = $recruiter->recruiter->last_name;
                    } else {
                        $recruiterName = "";
                    }
                } else {
                    $recruiterName = "";
                }
                $row['Job Title']    = $recruiter->title;
                $row['Location']    = $recruiter->country->name;
                $row['Client']    = $recruiter->customer->name;
                $row['Speciality']    = $recruiter->category->name;
                $row['Subspecialty']    = $recruiter->subcategory->name;
                $row['Recruiter']    = $recruiterName;
                $row['Date of Job Created']    = $recruiter->created_date;
                fputcsv($file, $row);
            }

            fclose($file);
            $filePath = url('/' . $uploadFile);
            rename(public_path() . '/' . $uploadFile, public_path() . '/uploads/report_csv/' . $uploadFile);
            $filePath = public_path() . '/uploads/report_csv/' . $uploadFile;
            $filePath =  url('/') . '/uploads/report_csv/' . $uploadFile;
            return response()->json(['status' => true, 'message' => 'Report generated successfully. ', 'data' => $filePath], 200);
        } else {
            return response()->json(['message' => 'Sorry, Report could not be generated!', 'status' => false], 200);
        }
    }

    public function downloadReport($reportType)
    {
        if ($this->user->hasPermissionTo('reports_list')) {
            if ($reportType === "general_report") {
                $candidateAppsQuery = Application::where('is_deleted', 0)
                    ->whereNotIn('status', ['Candidate Pool'])
                    ->orderBy('created_at', 'desc');

                $candidateAppsQuery->where(function ($query) {
                    $query->where('created_by', Auth::id())
                        ->orWhere('recruiter_id', Auth::id());
                });

                $candidateApps = $candidateAppsQuery->pluck('id');

                $applicationsQuery = Application::select('applications.created_at', 'applications.source', 'applications.status', 'applications.job_id', 'applications.recruiter_id', 'applications.source', 'applications.first_name', 'applications.last_name', DB::raw('DATE(applications.updated_at) as updated_date'), 'customers.name as customer_name') // Include customer name
                    ->with(['job:title,id', 'job.customer', 'recruiter:first_name,id,last_name'])
                    ->leftJoin('jobs', 'applications.job_id', '=', 'jobs.id')
                    ->leftJoin('customers', 'jobs.customer_id', '=', 'customers.id') // Join customers table
                    ->where('applications.is_deleted', 0)
                    ->whereNotIn('applications.status', ['Candidate Pool'])
                    ->whereIn('applications.created_by', [Auth::id(), 0]);

                if (Auth::user()->role_id != 2) {
                    $applicationsQuery->orWhereIn('applications.recruiter_id', [Auth::id(), 0])->orWhereIn('applications.id', $candidateApps);
                }

                $applications = $applicationsQuery
                    ->orderBy('created_at', 'desc')
                    ->get();
                return $this->downloadGeneralReport($applications);
            } elseif ($reportType === "recruiter_by_status") {
                $recruitersQuery = Agency::with(['applications' => function ($query) {
                    $query->select('recruiter_id', 'status', \DB::raw('count(*) as count'))
                        ->groupBy('recruiter_id', 'status');
                }])
                    ->selectRaw('id, CONCAT(first_name, " ", last_name) as name')
                    ->where('is_deleted', 0)
                    ->whereNotIn('role_id', [1, 2])
                    ->whereIn('created_by', [Auth::id(), 0])
                    ->withCount('applications');
                if (Auth::user()->role_id != 2) {
                    $recruitersQuery->orWhereIn('id', [Auth::id(), 0]);
                }

                $recruiters = $recruitersQuery
                    ->orderBy('created_at', 'desc')
                    ->get();
                return $this->downloadRecruiterByStatus($recruiters);
            } elseif ($reportType === "job_opening") {
                $jobQuery = Job::select(
                    'title',
                    'customer_id',
                    'recruiter_id',
                    'category_id',
                    'subcategory_id',
                    'city',
                    'country_id',
                    DB::raw('DATE(created_at) as created_date')
                )
                    ->with(['recruiter:id,first_name,last_name', 'customer:id,name', 'category:name,id', 'subcategory:name,id', 'country:name,id'])
                    ->where('is_deleted', 0)
                    ->where('created_by', Auth::id());

                if (Auth::user()->role_id != 2) {
                    $jobQuery->orWhereIn('recruiter_id', [Auth::id(), 0]);
                }

                $jobs = $jobQuery->orderBy('created_at', 'desc')->get();
                return $this->downloadJobOpening($jobs);
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ],  403);
        }
    }
}
