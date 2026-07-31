<?php

namespace App\Http\Controllers\Api\Agency;

use App\Http\Controllers\Controller;
use App\Http\Requests\JobRequest;
use App\Models\Agency;
use App\Models\Application;
use App\Models\Job;
use App\Models\Payment;
use App\Models\SocialIntegration;
use App\Notifications\NewNotification;
use Carbon\Carbon;
use HTMLPurifier;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use League\HTMLToMarkdown\HtmlConverter;

class JobController extends Controller
{
    private $user;
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            return $next($request);
        });
    }
    public function joblist(Request $request, $customer_id = null)
    {
        if (!$this->user->hasPermissionTo('jobs_list')) {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ], 403);
        }
        $recruiterIds = Agency::where('agency_id', Auth::id())->pluck('id')->toArray();
        $perPage = $request->input('perPage');
        $page = $request->input('page');
        $search = $request->input('search');
        $columnName = $request->input('columnName') ? $request->input('columnName') : 'created_at';
        $type = $request->input('type') ? $request->input('type') : 'desc';

        $jobQuery = Job::with(['recruiter', 'customer', 'category', 'subcategory', 'country'])->where('is_deleted', 0)
            ->where('created_by', Auth::id())
            ->orWhere("created_by", Auth::user()->agency_id)
            ->orWhereIn('created_by', $recruiterIds); // All recruiters under the agency


        if (Auth::user()->role_id != 2) {
            $jobQuery->orWhere('recruiter_id', Auth::id());
        }

        if ($customer_id) {
            $jobQuery->where('customer_id', $customer_id);
        }

        if ($page || $perPage) {
            $jobQuery->when($request->filled('recruiter_id'), function ($query) use ($request) {
                $query->where('recruiter_id', $request->input('recruiter_id'));
            });

            $jobQuery->when($request->filled('category_id'), function ($query) use ($request) {
                $query->where('category_id', $request->input('category_id'));
            });

            $jobQuery->when($request->filled('client_id'), function ($query) use ($request) {
                $query->where('customer_id', $request->input('client_id'));
            });

            if (!empty($search)) {
                $jobQuery->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%$search%")
                        ->orWhere('description', 'like', "%$search%")
                        ->orWhereHas('recruiter', function ($query) use ($search) {
                            $query->where(function ($query) use ($search) {
                                $query->where('first_name', 'like', '%' . $search . '%')
                                    ->orWhere('last_name', 'like', '%' . $search . '%');
                            });
                        })->orWhereHas('customer', function ($query) use ($search) {
                            $query->where(function ($query) use ($search) {
                                $query->where('name', 'like', '%' . $search . '%');
                            });
                        });
                });
            }




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
        } else {
            $encryptedJobs = $jobQuery->get()->map(function ($item, $key) {
                $item['eid'] = Crypt::encryptString($item['id']);
                return $item;
            });

            $paginatedJobs = $encryptedJobs;
        }

        if ($encryptedJobs->isNotEmpty()) {
            return response()->json([
                'message' => 'All jobs fetched successfully.',
                'jobs' => $paginatedJobs,
                'status' => 200
            ], 200);
        } else {
            return response()->json([
                'message' => "No jobs found.",
                'jobs' => $paginatedJobs,
                'status' => 200
            ]);
        }
    }

    public function getLinkedinProfileId()
    {
        $accessToken = SocialIntegration::where('agency_id', Auth::id())->where('type', 'linkedin')->first();

        if ($accessToken->token) {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken->token,
            ])->get('https://api.linkedin.com/v2/userinfo');
            // Check if the request was successfull
            if ($response->successful()) {
                $userInfo = $response->json();

                $sub = $userInfo['sub'];
                return $sub; // Return JSON response
            } else {
                return $response->status(); // Return the status code in case of error
            }
        } else {
            return response()->json([
                'error' => "Sorry!! Access Token May Expired!! Please Generate again. ",
                'status' => 422
            ], 422);
        }
    }

    public function addJob(JobRequest $request)
    {
        if ($this->user->hasPermissionTo('jobs_add')) {
            $agency = Auth::user();
            if (Auth::user()->agency_id != null) {
                $payment = Payment::where('agency_id', $agency->agency_id)->orderBy('created_at', 'DESC')->where('status', 'Active')->first();
            } else {
                $payment = Payment::where('agency_id', $agency->id)->orderBy('created_at', 'DESC')->where('status', 'Active')->first();
            }
            $currentDate = Carbon::now();
            $startDate = $currentDate->format('Y-m-d');
            if ($payment->subscription_id === 0 && ($payment && $payment->expiry_date && $payment->expiry_date > $startDate)) {
                $job = Job::where('is_deleted', 0)->where('created_by', Auth::id())->get();
                if (count($job) > 9) {
                    return response()->json([
                        'error' => "Sorry!! You can't create more then 10 jobs in this free plan.",
                        'status' => 422
                    ], 422);
                } else {
                    $imageName = "";
                    if ($request->hasFile('image')) {
                        $image = $request->file('image');
                        $imageName = 'job_' . time() . '.' . $image->getClientOriginalExtension();
                        $image->storePubliclyAs('public/images/job', $imageName);
                    }
                    $jobs = Job::create([
                        'title' => $request->get('title'),
                        'description' => $request->get('description'),
                        'country_id' => $request->get('country_id'),
                        'customer_id' => $request->get('customer_id'),
                        'recruiter_id' => $request->get('recruiter_id'),
                        'category_id' => $request->get('category_id'),
                        'subcategory_id' => !empty($request->get('subcategory_id')) ? $request->get('subcategory_id') : 0,
                        'tags_points' => !empty($request->get('tags_points')) ? json_decode($request->get('tags_points')) : [],
                        'last_date_apply' => $request->get('last_date_apply'),
                        'created_by' => Auth::id(),
                        'images' => $imageName ? 'images/job/' . $imageName : null,
                        'city' => $request->get('city')
                    ]);
                }
            } else {
                $imageName = "";
                if ($request->hasFile('image')) {
                    $image = $request->file('image');
                    $imageName = 'job_' . time() . '.' . $image->getClientOriginalExtension();
                    $image->storePubliclyAs('public/images/job', $imageName);
                }

                $jobs = Job::create([
                    'title' => $request->get('title'),
                    'description' => $request->get('description'),
                    'country_id' => $request->get('country_id'),
                    'customer_id' => $request->get('customer_id'),
                    'recruiter_id' => $request->get('recruiter_id'),
                    'category_id' => $request->get('category_id'),
                    'subcategory_id' => !empty($request->get('subcategory_id')) ? $request->get('subcategory_id') : 0,
                    'tags_points' => !empty($request->get('tags_points')) ? json_decode($request->get('tags_points')) : [],
                    'last_date_apply' => $request->get('last_date_apply'),
                    'created_by' => Auth::id(),
                    'images' => $imageName ? 'images/job/' . $imageName : null,
                    'city' => $request->get('city')
                ]);
            }

            if (!empty($jobs)) {
                $details = [
                    "notification" => 'Job created successfully.',
                    "category" => 'job',
                    "id" => $jobs->id,
                    "title" => $jobs->title,
                    "description" => $jobs->description,
                    "last_date_apply" => $jobs->last_date_apply,
                    "created_at" => $jobs->created_at,
                ];
                $recruiter = Agency::where('id', $jobs->recruiter_id)->first();
                $recruiter->notify(new NewNotification($details));

                return response()->json([
                    'message' => 'Job created successfully',
                    'jobs' => $jobs,
                    'eid' => Crypt::encryptString($jobs->id),
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => "Sorry!! Couldn't create job.",
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

    public function updateJob(JobRequest $request, $id)
    {
        if ($this->user->hasPermissionTo('jobs_update')) {
            $jobId = Job::find($id);
            $imageName = "";
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imageNameNew = 'job_' . time() . '.' . $image->getClientOriginalExtension();
                $image->storePubliclyAs('public/images/job', $imageNameNew);
            } else {
                $imageNameOld = $jobId->images;
            }
            $jobUpdatedData = $request->all();
            $jobUpdatedData['tags_points'] = !empty($request->get('tags_points')) ? json_decode($request->get('tags_points')) : $jobId->tags_points;
            $jobUpdatedData['images'] = !empty($imageNameNew) ? 'images/job/' . $imageNameNew : $imageNameOld;
            $jobs = $jobId->update($jobUpdatedData);

            //Notification when job updated
            $details = [
                "notification" => 'Job updated successfully.',
                "category" => 'job',
                "id" => $jobId->id,
                "title" => $jobId->title,
                "description" => $jobId->description,
                "last_date_apply" => $jobId->last_date_apply,
                "created_at" => $jobId->created_at,
            ];
            $recruiter = Agency::where('id', $jobId->recruiter_id)->first();
            if ($recruiter) {
                $recruiter->notify(new NewNotification($details));
            }
            if (!empty($jobs)) {
                return response()->json([
                    'message' => 'Job updated successfully.',
                    'jobs' => $jobs,
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => "Sorry!! Couldn't update job.",
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

    public function deleteJob(Request $request)
    {
        if ($this->user->hasPermissionTo('jobs_delete')) {
            $job_ids = $request->get('job_ids');
            if (!empty($job_ids)) {
                foreach ($job_ids as $job_id) {
                    $job = Job::find($job_id);
                    $job->update(['is_deleted' => 1]);
                    $application = Application::where('job_id', $job_id);
                    $application->update([
                        'is_deleted' => 1
                    ]);
                }
                return response()->json([
                    'message' => 'Job Deleted successfully',
                    'status' => 200
                ],  200);
            } else {
                return response()->json([
                    'message' => "Sorry!! Couldn't delete Job.",
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

    public function registerImage($linkedInId)
    {
        $accessToken = SocialIntegration::where('agency_id', Auth::id())->where('type', 'linkedin')->first();

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken->token,
        ])->post('https://api.linkedin.com/v2/assets?action=registerUpload', [
            "registerUploadRequest" => [
                "recipes" => [
                    "urn:li:digitalmediaRecipe:feedshare-image"
                ],
                "owner" => "urn:li:person:" . $linkedInId,
                "serviceRelationships" => [
                    [
                        "relationshipType" => "OWNER",
                        "identifier" => "urn:li:userGeneratedContent"
                    ]
                ]
            ]
        ]);

        // Handle response
        if ($response->successful()) {
            // Request was successful, handle the response
            return $response->json();
        } else {
            // Request failed, handle errors
            $errorCode = $response->status();
            $errorMessage = $response->body();
            return response()->json([
                'error' => $errorMessage,
                'status' => $errorCode
            ],  $errorCode);
        }
    }

    public function showHtml()
    {
        $htmlContent = '
            <div><b>HEllo&nbsp;</b></div>
            <i style="font-weight: bold;">How r u ?</i><br>
            <u style="font-style: italic; font-weight: bold;">I am fine</u><br>
            <u style="text-decoration-line: line-through; font-style: italic; font-weight: bold;">You say</u><br>
            <ol>
                <li>sdfsfsf<br></li>
                <li>sdfsdfsf</li>
                <li><span style="font-size: 0.875rem;">sdfsdfsdf</span></li>
            </ol>
            <a href="http://www.google.com">www.google.com</a><br>
            <h1><h2>sdfsf<br><pre><span style="font-weight: normal;">sdcdcdcc</span></pre></h2></h1><br><br style="font-size: 0.875rem;">
        ';

        return response($htmlContent, 200)
            ->header('Content-Type', 'text/html');
    }

    public function shareJobInSocialMedia(Request $request, $id)
    {
        if ($this->user->hasPermissionTo('jobs_add')) {
            $job = Job::find($id);

            // Image upload code in jobs
            if ($request->hasFile('images')) {
                $image = $request->file('images');
                $validator = Validator::make($request->all(), [
                    'images' => 'required|mimes:jpeg,png,jpg,gif|max:2048', // Modify the max file size as needed
                ]);

                // Check if validation fails
                if ($validator->fails()) {
                    return response()->json(['error' => $validator->errors()], 400);
                }

                // Generate a unique name for the image
                $imageName = 'job_image_' . time() . '.' . $image->getClientOriginalExtension();

                // Store the image in the storage folder (you might need to configure storage in Laravel)
                $image->storePubliclyAs('public/images/job_image', $imageName);
                if ($imageName) {
                    $job->update([
                        'images' => 'images/job_image/' . $imageName,
                        'title' => $request->title,
                        'description' => $request->description,
                    ]);
                }
            }
            $lnresponseData = null;

            // Job post shared on LinkedIn
            $linkedInaccessToken = SocialIntegration::where('agency_id', Auth::id())->where('type', 'linkedin')->first();
            if ($linkedInaccessToken) {
                $linkedInId = $this->getLinkedinProfileId();
                $registerImage = $this->registerImage($linkedInId);

                $uploadUrl = $registerImage['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'];
                $asset = $registerImage['value']['asset'];

                if ($uploadUrl) {
                    $response = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $linkedInaccessToken->token,
                    ])->withBody($request->file('images')->getContent(), "application/octet-stream")->put($uploadUrl);
                    if (!$response->successful()) {
                        return response()->json([
                            'error' => $response->body(),
                            'status' => $response->status()
                        ], $response->status());
                    }
                }
                $plainTextDescription = strip_tags($job->description);
                $linkedInDescription = $this->convertHtmlToUnicode($job->description);
                $jobDescription = $job->description;
                if ($linkedInId) {


                    // Sanitize and format your HTML content if needed

                    $response = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $linkedInaccessToken->token,
                        'Content-Type' => 'application/json',
                    ])->post('https://api.linkedin.com/v2/ugcPosts', [
                        "author" => "urn:li:person:" . $linkedInId,
                        "lifecycleState" => "PUBLISHED",
                        "specificContent" => [
                            "com.linkedin.ugc.ShareContent" => [
                                "shareCommentary" => [
                                    "text" => $job->title . "\n" . $linkedInDescription . "\nYou can Apply this Job using this Link: " . env('REACT_APP_URL') . '/apply/linkedin/' . Crypt::encryptString($job->id),
                                ],
                                "shareMediaCategory" => "IMAGE",
                                "media" => [
                                    [
                                        "status" => "READY",
                                        "description" => [
                                            "text" => $linkedInDescription,
                                        ],
                                        "media" => $asset,
                                        "title" => [
                                            "text" => $job->title
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        "visibility" => [
                            "com.linkedin.ugc.MemberNetworkVisibility" => "PUBLIC"
                        ]
                    ]);
                    $lnresponseData = $response->json();
                }
            }

            // Job post shared on Facebook
            $fbResponseData = null;
            $filePath = 'public/images/job_image/' . $imageName;
            $fullUrl = Storage::url($filePath);
            $fbaccessToken = SocialIntegration::where('agency_id', Auth::id())->where('type', 'facebook')->first();
            if ($fbaccessToken) {
                $pageId = 182374221636829;

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $fbaccessToken->token,
                ])->post('https://graph.facebook.com/v19.0/' . $pageId . '/photos', [
                    "message" => $job->title . "\n" . $linkedInDescription . "\nYou can Apply this Job using this Link: " . env('REACT_APP_URL') . '/apply/facebook/' . Crypt::encryptString($job->id),
                    "published" => true,
                    "url" => url($fullUrl)
                ]);
                $fbResponseData = $response->json();
            }

            // Handle response
            $message = "";
            if ($lnresponseData && $fbResponseData) {
                $message = 'Job created and Shared on LinkedIn and Facebook successfully';
            } elseif ($lnresponseData) {
                $message = 'Job created and Shared on LinkedIn successfully';
            } elseif ($fbResponseData) {
                $message = 'Job created and Shared on Facebook successfully';
            } else {
                return response()->json([
                    'error' => "Can't share this post on LinkedIn and Facebook!! Please generate Token with use of Integration Page.",
                    'status' => 422
                ], 422);
            }

            $details = [
                "notification" => $message,
                "category" => 'job',
                "id" => $job->id,
                "title" => $job->title,
                "description" => $job->description,
                "last_date_apply" => $job->last_date_apply,
                "created_at" => $job->created_at,
            ];
            $recruiter = Agency::where('id', $job->recruiter_id)->first();
            $recruiter->notify(new NewNotification($details));

            return response()->json([
                'message' => $message,
                'status' => 200
            ], 200);
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ], 403);
        }
    }

    private function convertHtmlToUnicode($html)
    {
        // Decode HTML entities
        $html = html_entity_decode($html);

        // Handle bold and italic tags
        $html = preg_replace_callback('/<b>(.*?)<\/b>/', function ($matches) {
            return $this->convertToBoldUnicode($matches[1]);
        }, $html);

        $html = preg_replace_callback('/<i>(.*?)<\/i>/', function ($matches) {
            return $this->convertToItalicUnicode($matches[1]);
        }, $html);

        $html = preg_replace_callback('/<u>(.*?)<\/u>/', function ($matches) {
            return $this->convertToUnderline($matches[1]);
        }, $html);

        // Handle <a> tags
        $html = preg_replace_callback('/<a href="(.*?)">(.*?)<\/a>/', function ($matches) {
            return $matches[2] . " (" . $matches[1] . ")";
        }, $html);

        // Handle <br> tags
        $html = preg_replace('/<br\s*\/?>/', "\n", $html);

        // Handle <ol> and <li> tags
        $html = preg_replace('/<ol>/', "\n", $html);
        $html = preg_replace('/<li>/', "- ", $html);
        $html = preg_replace('/<\/li>/', "\n", $html);
        $html = preg_replace('/<\/ol>/', "\n", $html);

        // Remove all other tags
        $html = strip_tags($html);

        return $html;
    }

    private function convertToBoldUnicode($text)
    {
        $bold_unicode_map = [
            'A' => '𝗔',
            'B' => '𝗕',
            'C' => '𝗖',
            'D' => '𝗗',
            'E' => '𝗘',
            'F' => '𝗙',
            'G' => '𝗚',
            'H' => '𝗛',
            'I' => '𝗜',
            'J' => '𝗝',
            'K' => '𝗞',
            'L' => '𝗟',
            'M' => '𝗠',
            'N' => '𝗡',
            'O' => '𝗢',
            'P' => '𝗣',
            'Q' => '𝗤',
            'R' => '𝗥',
            'S' => '𝗦',
            'T' => '𝗧',
            'U' => '𝗨',
            'V' => '𝗩',
            'W' => '𝗪',
            'X' => '𝗫',
            'Y' => '𝗬',
            'Z' => '𝗭',
            'a' => '𝗮',
            'b' => '𝗯',
            'c' => '𝗰',
            'd' => '𝗱',
            'e' => '𝗲',
            'f' => '𝗳',
            'g' => '𝗴',
            'h' => '𝗵',
            'i' => '𝗶',
            'j' => '𝗷',
            'k' => '𝗸',
            'l' => '𝗹',
            'm' => '𝗺',
            'n' => '𝗻',
            'o' => '𝗼',
            'p' => '𝗽',
            'q' => '𝗾',
            'r' => '𝗿',
            's' => '𝘀',
            't' => '𝘁',
            'u' => '𝘂',
            'v' => '𝘃',
            'w' => '𝘄',
            'x' => '𝘅',
            'y' => '𝘆',
            'z' => '𝘇',
            '0' => '𝟬',
            '1' => '𝟭',
            '2' => '𝟮',
            '3' => '𝟯',
            '4' => '𝟰',
            '5' => '𝟱',
            '6' => '𝟲',
            '7' => '𝟳',
            '8' => '𝟴',
            '9' => '𝟵'
        ];
        return strtr($text, $bold_unicode_map);
    }

    private function convertToItalicUnicode($text)
    {
        $italic_unicode_map = [
            'A' => '𝐴',
            'B' => '𝐵',
            'C' => '𝐶',
            'D' => '𝐷',
            'E' => '𝐸',
            'F' => '𝐹',
            'G' => '𝐺',
            'H' => '𝐻',
            'I' => '𝐼',
            'J' => '𝐽',
            'K' => '𝐾',
            'L' => '𝐿',
            'M' => '𝑀',
            'N' => '𝑁',
            'O' => '𝑂',
            'P' => '𝑃',
            'Q' => '𝑄',
            'R' => '𝑅',
            'S' => '𝑆',
            'T' => '𝑇',
            'U' => '𝑈',
            'V' => '𝑉',
            'W' => '𝑊',
            'X' => '𝑋',
            'Y' => '𝑌',
            'Z' => '𝑍',
            'a' => '𝑎',
            'b' => '𝑏',
            'c' => '𝑐',
            'd' => '𝑑',
            'e' => '𝑒',
            'f' => '𝑓',
            'g' => '𝑔',
            'h' => '𝒽',
            'i' => '𝑖',
            'j' => '𝑗',
            'k' => '𝑘',
            'l' => '𝑙',
            'm' => '𝑚',
            'n' => '𝑛',
            'o' => '𝑜',
            'p' => '𝑝',
            'q' => '𝑞',
            'r' => '𝑟',
            's' => '𝑠',
            't' => '𝑡',
            'u' => '𝑢',
            'v' => '𝑣',
            'w' => '𝑤',
            'x' => '𝑥',
            'y' => '𝑦',
            'z' => '𝑧',
            '0' => '𝟎',
            '1' => '𝟏',
            '2' => '𝟐',
            '3' => '𝟑',
            '4' => '𝟒',
            '5' => '𝟓',
            '6' => '𝟔',
            '7' => '𝟕',
            '8' => '𝟖',
            '9' => '𝟗'
        ];
        return strtr($text, $italic_unicode_map);
    }

    private function convertToUnderline($text)
    {
        $underline_unicode_map = [
            'A' => 'A̲',
            'B' => 'B̲',
            'C' => 'C̲',
            'D' => 'D̲',
            'E' => 'E̲',
            'F' => 'F̲',
            'G' => 'G̲',
            'H' => 'H̲',
            'I' => 'I̲',
            'J' => 'J̲',
            'K' => 'K̲',
            'L' => 'L̲',
            'M' => 'M̲',
            'N' => 'N̲',
            'O' => 'O̲',
            'P' => 'P̲',
            'Q' => 'Q̲',
            'R' => 'R̲',
            'S' => 'S̲',
            'T' => 'T̲',
            'U' => 'U̲',
            'V' => 'V̲',
            'W' => 'W̲',
            'X' => 'X̲',
            'Y' => 'Y̲',
            'Z' => 'Z̲',
            'a' => 'a̲',
            'b' => 'b̲',
            'c' => 'c̲',
            'd' => 'd̲',
            'e' => 'e̲',
            'f' => 'f̲',
            'g' => 'g̲',
            'h' => 'h̲',
            'i' => 'i̲',
            'j' => 'j̲',
            'k' => 'k̲',
            'l' => 'l̲',
            'm' => 'm̲',
            'n' => 'n̲',
            'o' => 'o̲',
            'p' => 'p̲',
            'q' => 'q̲',
            'r' => 'r̲',
            's' => 's̲',
            't' => 't̲',
            'u' => 'u̲',
            'v' => 'v̲',
            'w' => 'w̲',
            'x' => 'x̲',
            'y' => 'y̲',
            'z' => 'z̲',
            '0' => '0̲',
            '1' => '1̲',
            '2' => '2̲',
            '3' => '3̲',
            '4' => '4̲',
            '5' => '5̲',
            '6' => '6̲',
            '7' => '7̲',
            '8' => '8̲',
            '9' => '9̲'
        ];
        return strtr($text, $underline_unicode_map);
    }

    public function closeJob($id)
    {
        if ($this->user->hasPermissionTo('jobs_update')) {
            $jobId = Job::find($id);
            $jobs = $jobId->update([
                'last_date_apply' => null
            ]);

            //Notification when job updated
            $details = [
                "notification" => 'Job updated successfully.',
                "category" => 'job',
                "id" => $jobId->id,
                "title" => $jobId->title,
                "description" => $jobId->description,
                "last_date_apply" => $jobId->last_date_apply,
                "created_at" => $jobId->created_at,
            ];
            $recruiter = Agency::where('id', $jobId->recruiter_id)->first();
            if ($recruiter) {
                $recruiter->notify(new NewNotification($details));
            }
            if (!empty($jobs)) {
                return response()->json([
                    'message' => 'Job closed successfully.',
                    'jobs' => $jobs,
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => "Sorry!! Couldn't close job.",
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

    public function reOpenJob($id)
    {
        if ($this->user->hasPermissionTo('jobs_update')) {
            $jobId = Job::find($id);
            $jobs = $jobId->update([
                'last_date_apply' => Carbon::now()->addDays(28)->format('Y-m-d')
            ]);

            //Notification when job updated
            $details = [
                "notification" => 'Job updated successfully.',
                "category" => 'job',
                "id" => $jobId->id,
                "title" => $jobId->title,
                "description" => $jobId->description,
                "last_date_apply" => $jobId->last_date_apply,
                "created_at" => $jobId->created_at,
            ];
            $recruiter = Agency::where('id', $jobId->recruiter_id)->first();
            if ($recruiter) {
                $recruiter->notify(new NewNotification($details));
            }
            if (!empty($jobs)) {
                return response()->json([
                    'message' => 'Job re-opened successfully.',
                    'jobs' => $jobs,
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => "Sorry!! Couldn't re-open job.",
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

    public function viewJob($id)
    {
        if ($this->user->hasPermissionTo('jobs_list')) {
            $job = Job::with(['applications'])->find($id);
            if (!empty($job)) {
                return response()->json([
                    'message' => 'Job get successfully.',
                    'job' => $job,
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => "Sorry!! Couldn't get job.",
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
}
