<?php

namespace App\Http\Controllers\Api\Agency;

use App\Http\Controllers\Controller;
use App\Http\Requests\CandidateRequest;
use App\Models\Agency;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;
use PhpOffice\PhpWord\IOFactory;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Http;
use DB;

class CandidateController extends Controller
{
    private $user;
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            return $next($request);
        });
    }
    public function candidateList(Request $request)
    {
        if ($this->user->hasPermissionTo('candidatepool_list')) {
            $perPage = $request->input('perPage');
            $page = $request->input('page');
            $search = $request->input('search');
            $columnName = $request->input('columnName') ? $request->input('columnName') : 'updated_at';
            $type = $request->input('type') ? $request->input('type') : 'desc';
            $filter = $request->input('pool_list_id') ? $request->input('pool_list_id') : [];
            $candidateQuery = Candidate::with('applications')
                ->select('candidates.*', DB::raw('(CASE WHEN EXISTS (SELECT 1 FROM applications WHERE candidate_id = candidates.id AND status = "Hired") THEN 1 ELSE 0 END) AS hired_status'))
                ->where('created_by', Auth::id())
                ->where('is_deleted', 0);
            // ;
            $recruiterIds = Agency::where('agency_id', Auth::id())->pluck('id')->toArray();

            if ($page || $perPage) {
                if (!empty($search)) {
                    $candidateQuery->where(function ($query) use ($search) {
                        $query->where('first_name', 'like', "%$search%")
                            ->orWhere('last_name', 'like', "%$search%")
                            ->orWhere('email', 'like', "%$search%");
                    });
                }
                if ($filter) {
                    $array = json_decode($filter, true);
                    $candidateQuery->whereIn('pool_list_id',  $array);
                }
                if ($request->filled('status')) {
                    $candidateQuery->when($request->filled('status'), function ($query) use ($request) {
                        $query->where('status', intval($request->input('status')));
                    });
                } else {
                    $candidateQuery->where('status', true);
                }
                $candidateQuery
                    ->orWhere('created_by', Auth::user()->agency_id)
                    ->orWhereIn('created_by', $recruiterIds);

                $candidateQuery->when($request->filled('country_id'), function ($query) use ($request) {
                    $query->where('country_id', $request->input('country_id'));
                });
                $candidateQuery->when($request->filled('category_id'), function ($query) use ($request) {
                    $query->where('category_id', $request->input('category_id'));
                });
                $candidateQuery->when($request->filled('subcategory_id'), function ($query) use ($request) {
                    $query->where('subcategory_id', $request->input('subcategory_id'));
                });
                $candidate = $candidateQuery->orderBy($columnName, $type)->paginate($perPage, ['*'], 'page', $page);
            } else {
                $candidate = $candidateQuery->orderBy('updated_at', 'desc')->get();
            }
            if ($candidate->isNotEmpty()) {
                return response()->json([
                    'message' => 'All candidates get successfully.',
                    'candidate' => $candidate,
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'message' => "No candidates found.",
                    'candidate' => $candidate,
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

    public function addCandidate(CandidateRequest $request)
    {
        if ($this->user->hasPermissionTo('candidatepool_add')) {
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
            }
            $candidate = Candidate::updateOrCreate(
                ['email' => $request->get('email')],
                [
                    'first_name' => $request->get('first_name'),
                    'last_name' => $request->get('last_name'),
                    'email' => $request->get('email'),
                    'phone' => $request->get('phone'),
                    'cv' => 'images/cv/' . $imageName,
                    'experience' => $request->get('experience'),
                    'country_id' => $request->get('country_id'),
                    'address' => $request->get('address'),
                    'created_by' => Auth::id(),
                    'status' => $request->get('status') ?? 1,
                    'category_id' => $request->get('category_id'),
                    'subcategory_id' => $request->get('subcategory_id'),
                    'pool_list_id' => $request->get('pool_list_id')
                ]
            );
            $response = Http::attach(
                'resume',
                file_get_contents($request->file('cv')->path()),
                'resume.pdf'
            )->post('http://74.207.230.150:8000/matching_vacancies', [
                'resume' => $request->file('cv')
            ]);
            if ($response->successful()) {
                $matchData = $response->json()['matching_vacancies'];
                $candidateData = Candidate::find($candidate->id);
                $candidateData->update([
                    'match_payload' => $matchData
                ]);
            }

            if ($candidate) {
                return response()->json([
                    'message' => 'New candidate created successfully.',
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => "Sorry!! candidate not Created.",
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

    public function updateCandidate(CandidateRequest $request, $id)
    {
        if ($this->user->hasPermissionTo('candidatepool_update')) {
            $candidate = Candidate::find($id)->update($request->all());
            if ($request->hasFile('cv')) {
                $image = $request->file('cv');
                $validator = Validator::make($request->all(), [
                    'cv' => 'file|mimes:pdf,doc,docx|max:2048', // Modify the max file size as needed
                ]);
                // Check if validation fails
                if ($validator->fails()) {
                    return response()->json(['error' => $validator->errors()], 400);
                }
                // Generate a unique name for the image
                $imageName = 'cv_' . time() . '.' . $image->getClientOriginalExtension();

                // Store the image in the storage folder (you might need to configure storage in Laravel)
                $image->storePubliclyAs('public/images/cv', $imageName);
                $candidate = Candidate::find($id)->update([
                    'cv' => 'images/cv/' . $imageName
                ]);
                $response = Http::attach(
                    'resume',
                    file_get_contents($request->file('cv')->path()),
                    'resume.pdf'
                )->post('http://74.207.230.150:8000/matching_vacancies', [
                    'resume' => $request->file('cv')
                ]);
                if ($response->successful()) {
                    $matchData = $response->json()['matching_vacancies'];
                    $candidateData = Candidate::find($candidate->id);
                    $candidateData->update([
                        'match_payload' => $matchData
                    ]);
                }
            }


            if ($candidate) {
                return response()->json([
                    'message' => 'Candidate updated successfully.',
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => "Sorry!! Candidate not updated.",
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

    public function deleteCandidate(Request $request)
    {
        if ($this->user->hasPermissionTo('candidatepool_delete')) {
            $candidate_ids = $request->get('candidate_ids');
            if (!empty($candidate_ids)) {
                foreach ($candidate_ids as $candidate_id) {
                    $candidate = Candidate::find($candidate_id);
                    $candidate->update(['is_deleted' => 1]);
                }
                return response()->json([
                    'message' => 'Candidate Deleted successfully',
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

    public function getMatchingApp($filename)
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

        return $content;
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

    public function candidateApplicationdetails($id)
    {
        if ($this->user->hasPermissionTo('candidatepool_list')) {
            $candidate = Candidate::find($id);
            $applications = Application::where('created_by', Auth::id())
                ->where('is_deleted', '!=', 1)
                ->where('candidate_id', $id)
                ->get()->toArray();

            // dd($applications);
            $matchedJobs = [];
            $s3 = new S3Client([
                'version' => 'latest',
                'region' => env('AWS_DEFAULT_REGION'), // Update with your bucket's region
                'credentials' => [
                    'key' => env('AWS_ACCESS_KEY_ID'),
                    'secret' => env('AWS_SECRET_ACCESS_KEY'),
                ],
            ]);

            $fileName = Str::afterLast($candidate->cv, '/');
            // Specify the directory to save the file
            $bucketName = env('AWS_BUCKET');
            $fileKey = 'public/images/cv/' . $fileName;
            $tmpDir = '/tmp';
            if (!file_exists($tmpDir)) {
                mkdir($tmpDir, 0755, true); // Create the directory if it doesn't exist
            }

            // Download the file from S3
            $path = $tmpDir . '/' . $fileName; // Temporary file path
            $result = $s3->getObject([
                'Bucket' => $bucketName,
                'Key' => $fileKey,
                'SaveAs' => $path,
            ]);
            // $matchedJobs = json_decode($candidate->match_payload) ?? [];
            if (!empty($candidate->match_payload)) {
                foreach ($candidate->match_payload as $job) {
                    if (isset($job['job_id'])) {
                        $apply = Application::where('email', $candidate->email)->where('job_id', $job['job_id'])->first();
                        $matchedJobObject = [
                            'job' => $job,
                            'apply' => $apply ? true : false,
                        ];
                        $matchedJobs[] = $matchedJobObject;
                    }
                }
            }

            $allJobs = Job::where('is_deleted', 0)->where('last_date_apply', null)->where('created_by', Auth::id())->get();
            $jobs = []; //add this blanks jobs array when no jobs are available
            if (!empty($allJobs)) {
                foreach ($allJobs as $job) {
                    $apply = Application::where('email', $candidate->email)->where('job_id', $job->id)->first();
                    $allJobsObject = [
                        'job' => $job,
                        'apply' => $apply ? true : false,
                    ];
                    $jobs[] = $allJobsObject;
                }
            }
            if ($candidate) {
                return response()->json([
                    'message' => 'All candidates get successfully.',
                    'candidate' => $candidate,
                    'applications' => $applications,
                    'matchedJobs' => $matchedJobs,
                    'jobs' => $jobs,
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => "Sorry!! Couldn't get candidates.",
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

    public function candidateMultipleActions(Request $request)
    {
        if ($this->user->hasPermissionTo('candidatepool_update')) {
            $candidateIds = $request->get('candidate_ids');
            $jobId = $request->get('job_id');
            $job = Job::find($jobId);
            $s3 = new S3Client([
                'version' => 'latest',
                'region' => env('AWS_DEFAULT_REGION'),
                'credentials' => [
                    'key' => env('AWS_ACCESS_KEY_ID'),
                    'secret' => env('AWS_SECRET_ACCESS_KEY'),
                ],
            ]);
            foreach ($candidateIds as $candidateData) {
                $candidate = Candidate::find($candidateData);
                if ($candidate) {
                    $alreadyApply = Application::where('email', $candidate->email)->where('job_id', $jobId)->first();

                    if (!$alreadyApply) {
                        $newApp = Application::create([
                            'created_by' => Auth::id(),
                            'first_name' => $candidate->first_name,
                            'last_name' => $candidate->last_name,
                            'email' => $candidate->email,
                            'phone' => $candidate->phone,
                            'job_id' => $jobId,
                            'recruiter_id' => 0,
                            'country_id' => $candidate->country_id,
                            'cv' => $candidate->cv,
                            'status' => "New",
                            'source' => 'website',
                            'country_id' => $candidate->country_id,
                            'experience' => $candidate->experience,
                            'address' => $candidate->address,
                            'candidate_id' => $candidateData,
                        ]);

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
                                'applied_job_id' => $jobId
                            ]);

                            if ($response->successful()) {
                                $matchData = $response->json();
                                $newApp->update(['match_payload' => $matchData]);
                            }
                        }
                    }
                }
            }

            return response()->json([
                'message' => 'This candidate applied successfully on ' . $job->title,
                'status' => 200
            ], 200);
        } else {
            return response()->json([
                'error' => "This role Doesn't have Permission.",
                'status' => 403
            ],  403);
        }
    }
}
