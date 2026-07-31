<?php

namespace App\Http\Controllers\Api\Agency;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApplicationDocumentRequest;
use App\Http\Requests\ApplicationRequest;
use App\Mail\SendEmailTemplate;
use App\Models\Agency;
use App\Models\Application;
use App\Models\ApplicationChecklist;
use App\Models\ApplicationDocument;
use App\Models\Candidate;
use App\Models\EmailTemplate;
use App\Models\Job;
use App\Models\PoolList;
use App\Models\Template;
use App\Notifications\NewNotification;
use Aws\S3\S3Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Smalot\PdfParser\Parser;
use PhpOffice\PhpWord\IOFactory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Psr7\Stream;
use GuzzleHttp\Psr7;


class ApplicationController extends Controller
{
    private $user;
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            return $next($request);
        });
    }
    public function applicationList(Request $request)
    {
        if ($this->user->hasPermissionTo('applications_list')) {
            $recruiterIds = Agency::where('agency_id', Auth::id())->pluck('id')->toArray();
            $perPage = $request->input('perPage');
            $page = $request->input('page');
            $search = $request->input('search');
            $columnName = $request->input('columnName') ? $request->input('columnName') : 'created_at';
            $type = $request->input('type') ? $request->input('type') : 'desc';

            $candidateAppsQuery = Application::where('is_deleted', 0)
                ->where('candidate_pool', 0)
                ->orderBy('created_at', 'desc');

            $candidateAppsQuery->where(function ($query) use ($recruiterIds) {
                $query->where('created_by', Auth::id())
                    ->orWhere('recruiter_id', Auth::id())
                    ->orWhereIn('created_by', $recruiterIds); // All recruiters under the agency                        // add this condition for getting agency related data to any recruiter which is created by the agency

            });

            $candidateApps = $candidateAppsQuery->pluck('id');

            $applicationsQuery = Application::with(['job', 'job.country', 'job.category', 'job.subcategory', 'recruiter'])
                ->where('applications.is_deleted', 0)
                ->where('candidate_pool', 0)
                ->whereIn('applications.created_by', [Auth::id()])
                ->orWhereIn('applications.recruiter_id', $recruiterIds); // this i have added 

            // ->whereIn('applications.created_by', [Auth::id(), 0]); this i have commented for tested

            // if (Auth::user()->role_id != 2) {
            $applicationsQuery->orWhereIn('recruiter_id', [Auth::id(), 0])->orWhereIn('applications.id', $candidateApps);
            // }

            if ($page || $perPage) {

                if ($columnName === 'country_id') {
                    $applicationsQuery->join('jobs', 'applications.job_id', '=', 'jobs.id')
                        ->join('countries', 'jobs.country_id', '=', 'countries.id');
                    $columnName = 'countries.name';
                } elseif ($columnName === 'recruiter_id') {
                    $applicationsQuery->join('agencies', 'applications.recruiter_id', '=', 'agencies.id');
                    $columnName = DB::raw("CONCAT(agencies.first_name, ' ', agencies.last_name)");
                } elseif ($columnName === 'job_id') {
                    $applicationsQuery->join('jobs', 'applications.job_id', '=', 'jobs.id');
                    $columnName = 'jobs.title';
                } elseif ($columnName === 'category_id') {
                    $applicationsQuery->join('jobs', 'applications.job_id', '=', 'jobs.id')
                        ->join('category', 'jobs.category_id', '=', 'category.id');
                    $columnName = 'category.name';
                } elseif ($columnName === 'assigned') {
                    $columnName = 'recruiter_id';
                }
                $applicationsQuery->when($request->filled('recruiter_id'), function ($query) use ($request) {
                    $query->where('recruiter_id', $request->input('recruiter_id'));
                });

                $applicationsQuery->when($request->filled('job_id'), function ($query) use ($request) {
                    $query->where('job_id', $request->input('job_id'));
                });

                $applicationsQuery->when($request->filled('country_id'), function ($query) use ($request) {
                    $query->where('country_id', $request->input('country_id'));
                });

                $applicationsQuery->when($request->filled('source'), function ($query) use ($request) {
                    $query->where('source', $request->input('source'));
                });

                $applicationsQuery->when($request->filled('status'), function ($query) use ($request) {
                    $query->where('status', $request->input('status'));
                });
                if ($request->input('status_hired')) {
                    $applicationsQuery->when($request->filled('status_hired'), function ($query) use ($request) {
                        $query->where('status', 'Hired');
                    });
                } else {
                    $applicationsQuery->whereNotIn('status', ['Hired']);
                }


                $applicationsQuery->when($request->filled('assigned'), function ($query) use ($request) {
                    $assigned = $request->input('assigned') === 'no' ? 0 : 1;
                    if ($assigned === 0) {
                        $query->where('recruiter_id', 0); // Not assigned
                    } else {
                        $query->where('recruiter_id', '!=', 0); // Assigned
                    }
                });

                if (!empty($search)) {
                    $applicationsQuery->where(function ($query) use ($search) {
                        $query->where('applications.first_name', 'like', "%$search%")
                            ->orWhere('applications.last_name', 'like', "%$search%")
                            ->orWhere('applications.email', 'like', "%$search%")
                            ->orWhere('applications.status', 'like', "%$search%")
                            ->orWhereHas('job', function ($query) use ($search) {
                                $query->where(function ($query) use ($search) {
                                    $query->where('title', 'like', '%' . $search . '%');
                                });
                            })->orWhereHas('recruiter', function ($query) use ($search) {
                                $query->where(function ($query) use ($search) {
                                    $query->where('first_name', 'like', '%' . $search . '%')
                                        ->orWhere('last_name', 'like', '%' . $search . '%');
                                });
                            })->orWhereHas('job.country', function ($query) use ($search) {
                                $query->where(function ($query) use ($search) {
                                    $query->where('name', 'like', '%' . $search . '%');
                                });
                            });
                    });
                }

                $applications = $applicationsQuery
                    ->orderBy($columnName, $type)
                    ->paginate($perPage, ['*'], 'page', $page);
            } else {
                $applications = $applicationsQuery
                    ->orderBy('created_at', 'desc')->get();
            }

            if ($applications->isNotEmpty()) {
                return response()->json([
                    'message' => 'Applications retrieved successfully.',
                    'applications' => $applications,
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'message' => "No applications found.",
                    'applications' => $applications,
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

    public function addApplication(ApplicationRequest $request)
    {
        if (!$this->user->hasPermissionTo('applications_add')) {
            return response()->json(['error' => "This role doesn't have permission.", 'status' => 403], 403);
        }

        $alreadyApply = Application::where('email', $request->get('email'))->where('job_id', $request->get('job_id'))->first();
        if ($alreadyApply) {
            return response()->json(['error' => "This User already applied for this Job.", 'status' => 403], 403);
        }

        if ($request->hasFile('cv')) {
            $validator = Validator::make($request->all(), [
                'cv' => 'file|mimes:pdf,doc,docx|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 400);
            }

            // Store the CV file
            $image = $request->file('cv');
            $imageName = 'cv_' . time() . '.' . $image->getClientOriginalExtension();
            $image->storePubliclyAs('public/images/cv', $imageName);
            $imageFile = 'images/cv/' . $imageName;
        } else {
            $imageFile = $request->get('cv');
        }

        // Create or update candidate data
        $candidateData = [
            'first_name' => $request->get('first_name'),
            'last_name' => $request->get('last_name'),
            'email' => $request->get('email'),
            'phone' => $request->get('phone'),
            'cv' => $imageFile,
            'country_id' => $request->get('country_id'),
            'created_by' => Auth::id(),
            'experience' => $request->get('experience'),
            'address' => $request->get('address'),
        ];

        $candidate = Candidate::updateOrCreate(
            ['email' => $request->get('email')],
            $candidateData
        );

        // Create application
        $applicationData = array_merge($candidateData, [
            'job_id' => $request->get('job_id'),
            'recruiter_id' => $request->get('recruiter_id'),
            'contract_brief' => $request->get('contract_brief'),
            'status' => $request->get('status') ? $request->get('status') : "New",
            'source' => $request->get('source'),
            'created_by' => Auth::id(),
            'candidate_id' => $candidate->id,
        ]);

        $application = Application::create($applicationData);

        // If CV file is provided, process matching analysis
        if ($request->hasFile('cv')) {
            $response = Http::attach(
                'resume',
                file_get_contents($request->file('cv')->path()),
                'resume.pdf'
            )->post('http://74.207.230.150:8000/Matching_Analysis_and_Vacancies', [
                'applied_job_id' => $request->get('job_id')
            ]);


            if ($response->successful()) {
                $matchData = $response->json();
                $application->update(['match_payload' => $matchData]);
                $candidate->update(['match_payload' => $matchData]);
            }
        }
        if ($request->get('candidate_id')) {
            $candidateMatch = Candidate::find($request->get('candidate_id'));
            $fileName = Str::afterLast($candidateMatch->cv, '/');
            $bucketName = env('AWS_BUCKET');
            $fileKey = 'public/images/cv/' . $fileName;
            $tmpDir = '/tmp';

            if (!file_exists($tmpDir)) {
                mkdir($tmpDir, 0755, true);
            }

            $s3 = new S3Client([
                'version' => 'latest',
                'region' => env('AWS_DEFAULT_REGION'), // Update with your bucket's region
                'credentials' => [
                    'key' => env('AWS_ACCESS_KEY_ID'),
                    'secret' => env('AWS_SECRET_ACCESS_KEY'),
                ],
            ]);

            $path = $tmpDir . '/' . $fileName;
            $result = $s3->getObject([
                'Bucket' => $bucketName,
                'Key' => $fileKey,
                'SaveAs' => $path,
            ]);

            // Perform matching analysis
            $response = Http::attach(
                'resume',
                file_get_contents($path),
                'resume.pdf'
            )->post('http://74.207.230.150:8000/Matching_Analysis_and_Vacancies', [
                'applied_job_id' => $request->get('job_id')
            ]);

            if ($response->successful()) {
                $matchData = $response->json();
                $application->update([
                    'match_payload' => $candidateMatch->match_payload
                ]);
                $candidate->update(['match_payload' => $matchData]);
            }
        }

        // Send email notifications
        $temp = Template::where('name', 'Application Registration Success Email')->first();
        $emailTemplate = EmailTemplate::where('template_id', $temp->id)->first();
        $search = ['{{first_name}}', '{{last_name}}', '{{email}}'];
        $replace = [$request->get('first_name'), $request->get('last_name'), $request->get('email')];
        $modifiedParagraph = str_replace($search, $replace, $emailTemplate->html);

        $recruiterEmail = Agency::find($request->get('recruiter_id'));

        if ($recruiterEmail) {
            Mail::to($recruiterEmail->email)->send(new SendEmailTemplate($modifiedParagraph));

            $details = [
                "notification" => 'New application created successfully.',
                "category" => 'application',
                "id" => $recruiterEmail->id,
                "first_name" => $recruiterEmail->first_name,
                "last_name" => $recruiterEmail->last_name,
                "email" => $recruiterEmail->email,
                "created_at" => $recruiterEmail->created_at,
            ];

            $recruiterEmail->notify(new NewNotification($details));
        }

        $email = $request->get('email');

        if ($email) {
            Mail::to($email)->send(new SendEmailTemplate($modifiedParagraph));
        }

        return $application ? response()->json(['message' => 'New application created successfully.', 'status' => 200], 200)
            : response()->json(['error' => "Sorry!! application not Created.", 'status' => 422], 422);
    }

    public function getObjectFromS3($imageName)
    {
        $s3 = new S3Client([
            'version' => 'latest',
            'region' => env('AWS_DEFAULT_REGION'), // Update with your bucket's region
            'credentials' => [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ],
        ]);

        // Specify the bucket name and file key
        $bucket = env('AWS_BUCKET');
        $filePath = 'public/' . $imageName;

        // Specify the directory to save the file
        $tmpDir = '/tmp/images/cv';
        if (!file_exists($tmpDir)) {
            mkdir($tmpDir, 0755, true); // Create the directory if it doesn't exist
        }

        // Download the file from S3
        $path = $tmpDir . '/' . basename($imageName); // Temporary file path
        $result = $s3->getObject([
            'Bucket' => $bucket,
            'Key' => $filePath,
            'SaveAs' => $path,
        ]);

        return $path;
    }

    public function updateApplication(ApplicationRequest $request, $id)
    {
        if ($this->user->hasPermissionTo('applications_update')) {
            if ($request->hasFile('cv')) {
                $image = $request->file('cv');
                $validator = Validator::make($request->all(), [
                    'cv' => 'file|mimes:pdf,doc,docx|max:2048', // Modify the max file size as needed
                ]);
                if ($validator->fails()) {
                    return response()->json(['error' => $validator->errors()], 400);
                }
                $imageName = 'cv_' . time() . '.' . $image->getClientOriginalExtension();
                $image->storePubliclyAs('public/images/cv', $imageName);
                $application = Application::find($id)->update([
                    'cv' => 'images/cv/' . $imageName
                ]);
            }
            $applicationId = Application::find($id);
            $application = $applicationId->update([
                'first_name' => $request->get('first_name'),
                'last_name' => $request->get('last_name'),
                'email' => $request->get('email'),
                'phone' => $request->get('phone'),
                'job_id' => $request->get('job_id'),
                'recruiter_id' => $request->get('recruiter_id'),
                'contract_brief' => $request->get('contract_brief'),
                'status' => $request->get('status'),
                'source' => $request->get('source'),
                'country_id' => $request->get('country_id'),
                'experience' => $request->get('experience'),
                'address' => $request->get('address')
            ]);
            if ($application) {
                $recruiter = Agency::where('id', $request->get('recruiter_id'))->first();
                if ($recruiter) {
                    $details = [
                        "notification" => 'Application details updated successfully.',
                        "category" => 'application',
                        "id" => $applicationId->id,
                        "first_name" => $applicationId->first_name,
                        "last_name" => $applicationId->last_name,
                        "email" => $applicationId->email,
                        "created_at" => $applicationId->created_at,
                    ];

                    $recruiter->notify(new NewNotification($details));
                }
                return response()->json([
                    'message' => 'Application updated successfully.',
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => "Sorry!! Application not updated.",
                    'status' => 422
                ], 422);
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ],  403);
        }
    }

    public function deleteApplication(Request $request)
    {
        if ($this->user->hasPermissionTo('applications_delete')) {
            $application_ids = $request->get('application_ids');
            if (!empty($application_ids)) {
                foreach ($application_ids as $application_id) {
                    $applicationId = Application::find($application_id);
                    $applicationId->update(['is_deleted' => 1]);
                }

                $recruiter = Agency::where('id', $applicationId->recruiter_id)->first();

                $details = [
                    "notification" => 'Application deleted successfully.',
                    "category" => 'application',
                    "id" => $applicationId->id,
                    "first_name" => $applicationId->first_name,
                    "last_name" => $applicationId->last_name,
                    "email" => $applicationId->email,
                    "created_at" => $applicationId->created_at,
                ];
                if ($recruiter) {
                    $recruiter->notify(new NewNotification($details));
                }

                return response()->json([
                    'message' => 'Application Deleted successfully',
                    'status' => 200
                ],  200);
            } else {
                return response()->json([
                    'message' => "Sorry!! Couldn't delete Customer.",
                    'status' => 422
                ],  422);
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ],  403);
        }
    }

    public function viewApplication($id)
    {
        if ($this->user->hasPermissionTo('applications_view')) {
            $application = Application::with(['job.country'])->find($id);
            $candidate =  Candidate::where('id', $application->candidate_id)->where('is_deleted', 0)->first();
            $matchedJobs = [];

            $fileName = Str::afterLast($application->cv, '/');
            $bucketName = env('AWS_BUCKET');
            $fileKey = 'public/images/cv/' . $fileName;
            $tmpDir = '/tmp';

            if (!file_exists($tmpDir)) {
                mkdir($tmpDir, 0755, true);
            }

            $s3 = new S3Client([
                'version' => 'latest',
                'region' => env('AWS_DEFAULT_REGION'), // Update with your bucket's region
                'credentials' => [
                    'key' => env('AWS_ACCESS_KEY_ID'),
                    'secret' => env('AWS_SECRET_ACCESS_KEY'),
                ],
            ]);

            $path = $tmpDir . '/' . $fileName;
            $result = $s3->getObject([
                'Bucket' => $bucketName,
                'Key' => $fileKey,
                'SaveAs' => $path,
            ]);
            // Perform matching analysis
            $response = Http::attach(
                'resume',
                file_get_contents($path),
                'resume.pdf'
            )->post('http://74.207.230.150:8000/Matching_Analysis_and_Vacancies', [
                'applied_job_id' => $application->job_id
            ]);
            if ($response->successful()) {
                $matchData = $response->json();
                $application->update([
                    'match_payload' => $matchData
                ]);

                if (!empty($matchData['matching_vacancies'])) {
                    foreach ($matchData['matching_vacancies'] as $job) {
                        $apply = Application::where('email', $application->email)->where('job_id', $job['job_id'])->first();
                        $matchedJobObject = [
                            'job' => $job,
                            'apply' => $apply ? true : false,
                        ];
                        $matchedJobs[] = $matchedJobObject;
                    }
                }
            }

            //
            $checklist = ApplicationChecklist::where('application_id', $id)->first();
            $documents = ApplicationDocument::where('is_deleted', 0)
                ->where('created_by', Auth::id())
                ->where('application_id', $id)
                ->orderBy('created_at', 'desc')->get();

            if ($application) {
                return response()->json([
                    'message' => 'Application List get successfully.',
                    'application' => $application,
                    'matchPer' => $application->match_payload !== null && $application->match_payload['applied_job'] && $application->match_payload['applied_job']['Match_Analysis'] ? $application->match_payload['applied_job']['Match_Analysis'] : 0,
                    'matchedJobs' => $matchedJobs ? $matchedJobs : [],
                    'checklist' => $checklist,
                    'documents' => $documents,
                    'notes' => $candidate && $candidate->notes ? $candidate->notes : [],
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => "Sorry!!! Couldn't get application view details.",
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

    public function sourceList()
    {
        if ($this->user->hasPermissionTo('applications_view')) {
            $source = Application::distinct()->pluck('source')->toArray();

            if ($source) {
                return response()->json([
                    'message' => 'Souce List get successfully.',
                    'source' => $source,
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => "No records Found",
                    'status' => 200
                ], 200);
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ], 403);
        }
    }

    public function getMatchingApp($filename, $tags_points)
    {
        // Initialize S3 customer
        $s3 = new S3Client([
            'version' => 'latest',
            'region' => env('AWS_DEFAULT_REGION'), // Update with your bucket's region
            'credentials' => [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ],
        ]);



        // Specify the bucket name and file key
        $bucketName = env('AWS_BUCKET');
        $fileKey = 'public/images/cv/' . $filename;

        // Specify the directory to save the file
        $tmpDir = '/tmp';
        if (!file_exists($tmpDir)) {
            mkdir($tmpDir, 0755, true); // Create the directory if it doesn't exist
        }

        // Download the file from S3
        $path = $tmpDir . '/' . $filename; // Temporary file path
        $result = $s3->getObject([
            'Bucket' => $bucketName,
            'Key' => $fileKey,
            'SaveAs' => $path,
        ]);

        // Ensure the file was downloaded successfully
        if (!file_exists($path)) {
            abort(404, 'File not found');
        }

        // Extract content based on file extension
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        switch ($extension) {
            case 'pdf':
                $content = $this->extractPdfContent($path);
                break;
            case 'doc':
            case 'docx':
                $content = $this->extractDocContent($path);
                break;
            default:
                abort(400, 'Unsupported file format');
        }

        // Match tags and points in content
        $resultArray = [];

        foreach ($tags_points as $tp) {
            $word = $tp['tag'];
            $found = strpos($content, $word) !== false;
            $point = $tp['point'];
            $resultArray[] = [
                'word' => $word,
                'point' => $point,
                'found' => $found,
            ];
        }

        return $resultArray;
    }

    protected function extractPdfContent($path)
    {

        $parser = new Parser();
        $pdf = $parser->parseFile($path);
        return $pdf->getText();
    }

    protected function extractDocContent($path)
    {
        $phpWord = IOFactory::load($path);

        $content = '';
        foreach ($phpWord->getSections() as $section) {

            foreach ($section->getElements() as $element) {
                if ($element instanceof \PhpOffice\PhpWord\Element\Table) {
                    // Iterate through rows
                    foreach ($element->getRows() as $row) {
                        // Iterate through cells
                        foreach ($row->getCells() as $cell) {
                            // Iterate through elements in cell
                            foreach ($cell->getElements() as $cellElement) {
                                // Extract text from cell element
                                $content .= $cellElement->getText() . ' ';
                            }
                        }
                        // Add newline after each row
                        $content .= PHP_EOL;
                    }
                } else {
                    // Extract text from other types of elements
                    $content .= $element->getText() . ' ';
                }
            }
        }

        return $content;
    }

    public function applicationStatusChange($id, Request $request)
    {
        if (!$this->user->hasPermissionTo('applications_view')) {
            return response()->json(['error' => "This role doesn't have permission.", 'status' => 403], 403);
        }

        $application = Application::find($id);

        if (!$application) {
            return response()->json(['error' => "Sorry!!! Couldn't get application view details.", 'status' => 422], 422);
        }

        $oldStatus = $application->status;

        if ($request->get('status') === "Candidate Pool") {
            $application->update(['candidate_pool' => 1]);
            Candidate::updateOrCreate(
                ['email' => $application->email],
                [
                    'first_name' => $application->first_name,
                    'last_name' => $application->last_name,
                    'email' => $application->email,
                    'phone' => $application->phone,
                    'cv' => $application->cv,
                    'experience' => $application->experience,
                    'address' => $application->address,
                    'created_by' => Auth::id(),
                    'pool_list_id' => $request->get('pool_list_id'),
                    'country_id' => $application->country_id,
                    'category_id' => $request->get('category_id'),
                    'subcategory_id' => $request->get('subcategory_id'),
                    'status' => 1,
                    'match_payload' => $application->match_payload && $application->match_payload['matching_vacancies'] ? $application->match_payload['matching_vacancies'] : []
                ]
            );
        } else {
            $application->update(['status' => $request->get('status')]);
        }



        $statusArray = ['New', 'Application Review', 'Initial Screening', 'Client Submission'];
        if (in_array($request->get('status'), $statusArray)) {
            $application->update(['schedule_date' => null, 'schedule_time' => null]);
        }


        if ($request->has('schedule_date')) {
            $application->update(['schedule_date' => $request->get('schedule_date')]);
        }
        if ($request->has('schedule_time')) {
            $application->update(['schedule_time' => $request->get('schedule_time')]);
        }

        $recruiter = Agency::find($application->recruiter_id);
        $tempName = $request->has('schedule_date') && $request->has('schedule_time') ? 'Schedule Reschedule Interview' : 'Application Status Change';
        $temp = Template::where('name', $tempName)->first();
        $emailTemplate = EmailTemplate::where('template_id', $temp->id)->first();
        $search = ['{{first_name}}', '{{last_name}}', '{{email}}', '{{old_status}}', '{{new_status}}'];
        $replace = [$application->first_name, $application->last_name, $application->email, $oldStatus, $request->get('status')];
        $modifiedParagraph = str_replace($search, $replace, $emailTemplate->html);

        if ($recruiter && $recruiter->email) {
            Mail::to($recruiter->email)->send(new SendEmailTemplate($modifiedParagraph));
        }
        if ($application->email) {
            Mail::to($application->email)->send(new SendEmailTemplate($modifiedParagraph));
        }

        $notificationDetails = [
            "notification" => $request->has('schedule_date') && $request->has('schedule_time') ? 'Application Status changed with Schedule Date and Time.' : 'Application Status changed successfully.',
            "category" => 'application',
            "id" => $application->id,
            "first_name" => $application->first_name,
            "old_status" => $oldStatus,
            "new_status" => $request->get('status'),
            "last_name" => $application->last_name,
            "email" => $application->email,
            "created_at" => $application->created_at,
        ];
        if ($recruiter) {
            $recruiter->notify(new NewNotification($notificationDetails));
        }

        return response()->json(['message' => 'Application status change successfully.', 'application' => $application, 'status' => 200], 200);
    }


    public function rescheduleApplication($id, Request $request)
    {
        if ($this->user->hasPermissionTo('applications_view')) {
            $applicationId = Application::find($id);
            $application = $applicationId->update([
                'schedule_date' => $request->get('schedule_date') ? $request->get('schedule_date') : null,
                'schedule_time' => $request->get('schedule_time') ? $request->get('schedule_time') : null
            ]);
            $recruiterEmail = Agency::where('id', $applicationId->recruiter_id)->first();
            $temp = Template::where('name', 'Schedule Reschedule Interview')->first();
            $emailTemplate = EmailTemplate::where('template_id', $temp->id)->first();
            $search = ['{{first_name}}', '{{last_name}}', '{{email}}', '{{schedule_date}}', '{{schedule_time}}'];
            $replace = [$request->get('first_name'), $request->get('last_name'), $request->get('email'), $request->get('schedule_date'), $request->get('schedule_time')];
            $modifiedParagraph = str_replace($search, $replace, $emailTemplate->html);
            $email = $applicationId->email;
            if ($recruiterEmail) {
                Mail::to($recruiterEmail)->send(new SendEmailTemplate($modifiedParagraph));
            }
            if ($email) {
                Mail::to($email)->send(new SendEmailTemplate($modifiedParagraph));
            }

            $details = [
                "notification" => 'Application Rescheduled successfully.',
                "category" => 'application',
                "id" => $applicationId->id,
                "first_name" => $applicationId->first_name,
                "schedule_date" => $request->get('schedule_date'),
                "schedule_time" => $request->get('schedule_time'),
                "last_name" => $applicationId->last_name,
                "email" => $applicationId->email,
                "created_at" => $applicationId->created_at,
            ];
            $recruiter = Agency::where('id', $applicationId->recruiter_id)->first();
            if ($recruiter) {
                $recruiter->notify(new NewNotification($details));
            }
            if ($application) {
                return response()->json([
                    'message' => 'Application rescheduled successfully.',
                    'application' => $application,
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => "Sorry!!! Couldn't rescheduled your application.",
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

    public function candidatePoolList(Request $request)
    {
        if ($this->user->hasPermissionTo('candidatepool_list')) {
            $candidatePool = Application::with('job')->where('is_deleted', 0)
                ->where('created_by', Auth::id())
                ->where('status', "Candidate Pool")
                ->orderBy('created_at', "DESC")
                ->get();
            if ($candidatePool->isNotEmpty()) {
                return response()->json([
                    'message' => 'Candidate Pool get successfully.',
                    'candidatePool' => $candidatePool,
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'message' => "No candidates pool found.",
                    'candidatePool' => $candidatePool,
                    'status' => 200
                ]);
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ], 403);
        }
    }

    public function addNotes(Request $request, $id)
    {
        if ($this->user->hasPermissionTo('applications_view')) {
            $candidate = Candidate::find($id);

            $notes = $candidate->notes ?? [];
            $user = Auth::user();
            $fullName = $user->first_name . ' ' . $user->last_name;
            $newNote = [
                'note' => $request->get('notes'),
                'timestamp' => now()->toDateTimeString(),
                'created_by' => $fullName
            ];

            $notes[] = $newNote;

            $candidate->update([
                'notes' => $notes,
            ]);
            if ($notes) {
                return response()->json([
                    'message' => 'Notes created successfully.',
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => "Sorry!!! Couldn't create notes.",
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

    public function addTasks(Request $request, $id)
    {
        if ($this->user->hasPermissionTo('applications_view')) {
            $candidate = Candidate::find($id);

            $tasks = $candidate->tasks ?? [];
            $user = Auth::user();
            $fullName = $user->first_name . ' ' . $user->last_name;
            $newTask = [
                'task' => $request->get('tasks'),
                'timestamp' => now()->toDateTimeString(),
                'created_by' => $fullName
            ];

            $tasks[] = $newTask;

            $candidate->update([
                'tasks' => $tasks,
            ]);
            if ($tasks) {
                return response()->json([
                    'message' => 'Tasks created successfully.',
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => "Sorry!!! Couldn't create tasks.",
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


    public function ApplyOnAnotherJob(Request $request, $id)
    {
        if (!$this->user->hasPermissionTo('applications_view')) {
            return response()->json(['error' => "This role doesn't have permission.", 'status' => 403], 403);
        }

        $job = Job::find($id);
        $s3 = new S3Client([
            'version' => 'latest',
            'region' => env('AWS_DEFAULT_REGION'),
            'credentials' => [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ],
        ]);

        $newApp = null;

        if ($request->application_id) {
            $application = Application::find($request->application_id);
            $candidateData = [
                'first_name' => $application->first_name,
                'last_name' => $application->last_name,
                'email' => $application->email,
                'phone' => $application->phone,
                'cv' => $application->cv,
                'country_id' => $application->country_id,
                'created_by' => Auth::id(),
                'experience' => $application->experience,
                'address' => $application->address,
            ];

            $candidate = Candidate::updateOrCreate(
                ['email' => $application->email],
                $candidateData
            );
            if ($application) {
                $newApp = Application::create([
                    'created_by' => Auth::id(),
                    'first_name' => $application->first_name,
                    'last_name' => $application->last_name,
                    'email' => $application->email,
                    'phone' => $application->phone,
                    'job_id' => $id,
                    'recruiter_id' => $application->recruiter_id,
                    'country_id' => $application->country_id,
                    'cv' => $application->cv,
                    'status' => "New",
                    'source' => $application->source,
                    'experience' => $application->experience,
                    'address' => $application->address,
                ]);
            }
        } elseif ($request->candidate_id) {
            $candidate = Candidate::find($request->candidate_id);
            if ($candidate) {
                $newApp = Application::create([
                    'created_by' => Auth::id(),
                    'first_name' => $candidate->first_name,
                    'last_name' => $candidate->last_name,
                    'email' => $candidate->email,
                    'phone' => $candidate->phone,
                    'job_id' => $id,
                    'recruiter_id' => 0,
                    'country_id' => $candidate->country_id,
                    'cv' => $candidate->cv,
                    'status' => "New",
                    'source' => 'website',
                    'country_id' => $candidate->country_id,
                    'experience' => $candidate->experience,
                    'address' => $candidate->address,
                ]);
            }
        }

        if ($newApp) {
            $fileName = Str::afterLast($newApp->cv, '/');
            $bucketName = env('AWS_BUCKET');
            $fileKey = 'public/images/cv/' . $fileName;
            $tmpDir = '/tmp';

            if (!file_exists($tmpDir)) {
                mkdir($tmpDir, 0755, true);
            }

            $path = $tmpDir . '/' . $fileName;
            $result = $s3->getObject([
                'Bucket' => $bucketName,
                'Key' => $fileKey,
                'SaveAs' => $path,
            ]);

            // Perform matching analysis
            $response = Http::attach(
                'resume',
                file_get_contents($path),
                'resume.pdf'
            )->post('http://74.207.230.150:8000/Matching_Analysis_and_Vacancies', [
                'applied_job_id' => $id
            ]);

            if ($response->successful()) {
                $matchData = $response->json();
                $newApp->update(['match_payload' => $matchData]);
            }

            return response()->json([
                'message' => 'This Application successfully applied on ' . $job->title,
                'status' => 200
            ], 200);
        } else {
            return response()->json([
                'error' => "Sorry!!! Couldn't create new application.",
                'status' => 422
            ], 422);
        }
    }


    public function applicationMultipleActions(Request $request, $action)
    {
        if ($this->user->hasPermissionTo('applications_update')) {
            $applicationIds = $request->get('application_ids');
            $recruiterId = $request->get('recruiter_id');
            $poolListId = $request->get('pool_list_id');
            $applicationQuery =  Application::whereIn('id', $applicationIds);
            if ($action === 'assigned') {
                if ($recruiterId) {
                    $applicationQuery->update(['recruiter_id' => $recruiterId]);
                }

                if ($applicationQuery) {
                    return response()->json([
                        'message' => 'All applications Assigned successfully.',
                        'status' => 200
                    ], 200);
                } else {
                    return response()->json([
                        'error' => "Sorry!! Application not updated.",
                        'status' => 422
                    ], 422);
                }
            } else if ($action === 'notassigned') {
                $applicationQuery->update(['recruiter_id' => 0]);

                if ($applicationQuery) {
                    return response()->json([
                        'message' => 'All applications Not assigned successfully.',
                        'status' => 200
                    ], 200);
                } else {
                    return response()->json([
                        'error' => "Sorry!! Application not updated.",
                        'status' => 422
                    ], 422);
                }
            } elseif ($action === 'movetopool') {
                $applicationQuery->update(['candidate_pool' => 1]);
                $poolCategory = PoolList::find($poolListId);
                foreach ($applicationIds as $appId) {
                    $applicationId = Application::find($appId);
                    $candidate = Candidate::updateOrCreate(
                        ['email' => $applicationId->email],
                        [
                            'first_name' => $applicationId->first_name,
                            'last_name' => $applicationId->last_name,
                            'email' => $applicationId->email,
                            'phone' => $applicationId->phone,
                            'cv' => $applicationId->cv,
                            'experience' => $applicationId->experience,
                            'address' => $applicationId->address,
                            'created_by' => Auth::id(),
                            'country_id' => $applicationId->country_id,
                            'pool_list_id' => $poolCategory->id,
                            'country_id' => $applicationId->country_id,
                            'status' => 1,
                            'category_id' => $request->get('category_id'),
                            'subcategory_id' => $request->get('subcategory_id')
                        ]
                    );
                }
                if ($candidate) {
                    return response()->json([
                        'message' => 'All applications Move to Pool successfully.',
                        'status' => 200
                    ], 200);
                } else {
                    return response()->json([
                        'error' => "Sorry!! Application not updated.",
                        'status' => 422
                    ], 422);
                }
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ],  403);
        }
    }

    public function addDocument(ApplicationDocumentRequest $request, $id)
    {
        if ($this->user->hasPermissionTo('applications_view')) {
            $document = ApplicationDocument::create([
                'created_by' => Auth::id(),
                'application_id' => $id,
                'doc_name' => $request->get('doc_name'),
            ]);

            if ($request->hasFile('doc')) {
                $image = $request->file('doc');
                $validator = Validator::make($request->all(), [
                    'doc' => 'file|mimes:pdf,doc,docx,jpeg,png|max:2048', // Modify the max file size as needed
                ]);
                // Check if validation fails
                if ($validator->fails()) {
                    return response()->json(['error' => $validator->errors()], 400);
                }
                // Generate a unique name for the image
                $imageName = 'doc_' . time() . '.' . $image->getClientOriginalExtension();

                // Store the image in the storage folder (you might need to configure storage in Laravel)
                $image->storePubliclyAs('public/images/document', $imageName);

                $document->update([
                    'doc' => $imageName ? 'images/document/' . $imageName : ""
                ]);
            }

            if (!empty($document)) {
                return response()->json([
                    'message' => 'Document Uploaded successfully.',
                    'status' => 200
                ],  200);
            } else {
                return response()->json([
                    'error' => "Sorry!! Document not Uploaded.",
                    'status' => 422
                ],  422);
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ],  403);
        }
    }

    public function updateDocument(ApplicationDocumentRequest $request, $id)
    {
        if ($this->user->hasPermissionTo('applications_view')) {
            if ($request->hasFile('doc')) {
                $image = $request->file('doc');
                $validator = Validator::make($request->all(), [
                    'doc' => 'file|mimes:pdf,doc,docx,jpeg,png|max:2048', // Modify the max file size as needed
                ]);
                if ($validator->fails()) {
                    return response()->json(['error' => $validator->errors()], 400);
                }
                $imageName = 'doc_' . time() . '.' . $image->getClientOriginalExtension();
                $image->storePubliclyAs('public/images/document', $imageName);
                ApplicationDocument::find($id)->update([
                    'doc' => 'images/document/' . $imageName
                ]);
            }
            $document = ApplicationDocument::find($id);
            $document->update([
                'doc_name' => $request->get('doc_name')
            ]);
            if ($document) {
                return response()->json([
                    'message' => 'Document updated successfully.',
                    'status' => 200
                ],  200);
            } else {
                return response()->json([
                    'error' => "Sorry!! Document not updated.",
                    'status' => 422
                ],  422);
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ],  403);
        }
    }

    public function deleteDocument($id)
    {
        if ($this->user->hasPermissionTo('document_delete')) {
            $document = ApplicationDocument::find($id);
            $document->update(['is_deleted' => 1]);
            if ($document) {
                return response()->json([
                    'message' => 'Document Deleted successfully',
                    'status' => 200
                ],  200);
            } else {
                return response()->json([
                    'message' => "Sorry!! Couldn't delete Document.",
                    'status' => 422
                ],  422);
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ],  403);
        }
    }
}
