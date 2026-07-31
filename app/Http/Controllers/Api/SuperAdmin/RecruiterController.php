<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\AgencyResource;
use App\Mail\SendEmailTemplate;
use App\Models\Agency;
use App\Models\Application;
use App\Models\EmailTemplate;
use App\Models\Job;
use App\Models\Payment;
use App\Models\Template;
use App\Notifications\NewNotification;
use Carbon\Carbon;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

class RecruiterController extends Controller
{

    private $user;
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            return $next($request);
        });
    }
    public function addRecruiter(RegisterRequest $request)
    {
        if ($this->user->hasPermissionTo('recruiters_add')) {
            $agency = Auth::user();
            $payment = Payment::where('agency_id', $agency->id)->where('status', 'Active')->orderBy('created_at', 'DESC')->first();
            $currentDate = Carbon::now();
            $startDate = $currentDate->format('Y-m-d');
            $recruiter = Agency::where('is_deleted', 0)->where('agency_id', Auth::id())->get();
            $temp = Template::where('name', 'Create Recruiter')->first();
            $emailTemplate = EmailTemplate::where('template_id', $temp->id)->first();
            $email = $request->get('email');
            if ($agency->role_id !== 1) { //For SuperAdmin user
                if ($payment && $payment->expiry_date && $payment->expiry_date > $startDate) {
                    if (($payment->subscription_id === 0)) {
                        if (count($recruiter) > 4) {
                            return response()->json([
                                'error' => "Sorry!! You can't create more then 5 Recruiters in this free plan.",
                                'status' => 422
                            ], 422);
                        } else {
                            $recruiter = Agency::create([
                                'created_by' => Auth::id(),
                                'first_name' => $request->get('first_name'),
                                'last_name' => $request->get('last_name'),
                                'email' => $request->get('email'),
                                'phone' => $request->get('phone'),
                                'address' => $request->get('address'),
                                'other_details' => $request->get('other_details'),
                                'role_id' => $request->get('role_id'),
                                'agency_id' => Auth::id()
                            ]);
                            $recruiter->givePermissionTo(['roles_list', 'document_list', 'category_list', 'subcategory_list', 'country_list', 'recruiters_list']);

                            $recruiterId = Crypt::encryptString($recruiter->id);

                            $search = ['{{first_name}}', '{{last_name}}', '{{email}}', '{{user_id}}'];
                            $replace = [$request->get('first_name'), $request->get('last_name'), $request->get('email'), $recruiterId];
                            $modifiedParagraph = str_replace($search, $replace, $emailTemplate->html);

                            if ($recruiter) {
                                $details = [
                                    "notification" => 'Your Account successfully created as a Recruiter',
                                    "category" => 'recruiter',
                                    "id" => $recruiter->id,
                                    "first_name" => $recruiter->first_name,
                                    "last_name" => $recruiter->last_name,
                                    "email" => $recruiter->email,
                                    "created_at" => $recruiter->created_at
                                ];
                                $recruiter->notify(new NewNotification($details));
                                Mail::to($email)->send(new SendEmailTemplate($modifiedParagraph));
                            } else {
                                return response()->json([
                                    'error' => "Can't sent mail.Something went wrong!!",
                                    'status' => 422
                                ], 422);
                            }
                        }
                    } else {
                        $recruiter = Agency::create([
                            'created_by' => Auth::id(),
                            'first_name' => $request->get('first_name'),
                            'last_name' => $request->get('last_name'),
                            'email' => $request->get('email'),
                            'phone' => $request->get('phone'),
                            'address' => $request->get('address'),
                            'other_details' => $request->get('other_details'),
                            'role_id' => $request->get('role_id'),
                            'agency_id' => Auth::id()
                        ]);
                        $recruiter->givePermissionTo(['roles_list', 'document_list', 'category_list', 'subcategory_list', 'country_list', 'recruiters_list']);
                        $recruiterId = Crypt::encryptString($recruiter->id);
                        $search = ['{{first_name}}', '{{last_name}}', '{{email}}', '{{user_id}}'];
                        $replace = [$request->get('first_name'), $request->get('last_name'), $request->get('email'), $recruiterId];
                        $modifiedParagraph = str_replace($search, $replace, $emailTemplate->html);

                        if ($recruiter) {
                            $details = [
                                "notification" => 'Your Account successfully created as a Recruiter',
                                "category" => 'recruiter',
                                "id" => $recruiter->id,
                                "first_name" => $recruiter->first_name,
                                "last_name" => $recruiter->last_name,
                                "email" => $recruiter->email,
                                "created_at" => $recruiter->created_at
                            ];
                            $recruiter->notify(new NewNotification($details));
                            Mail::to($email)->send(new SendEmailTemplate($modifiedParagraph));
                        } else {
                            return response()->json([
                                'error' => "Can't sent mail.Something went wrong!!",
                                'status' => 422
                            ], 422);
                        }
                    }
                } else {
                    return response()->json([
                        'error' => "Sorry!! Your current plan is expired, Please upgrade your plan or contact admin for it. ",
                        'status' => 422
                    ], 422);
                }
            } else { //For Agency user
                $recruiter = Agency::create([
                    'created_by' => Auth::id(),
                    'first_name' => $request->get('first_name'),
                    'last_name' => $request->get('last_name'),
                    'email' => $request->get('email'),
                    'phone' => $request->get('phone'),
                    'address' => $request->get('address'),
                    'other_details' => $request->get('other_details'),
                    'role_id' => $request->get('role_id'),
                    'agency_id' => $request->get('agency_id')
                ]);
                $recruiter->givePermissionTo(['roles_list', 'document_list', 'category_list', 'subcategory_list', 'country_list', 'recruiters_list']);
                $recruiterId = Crypt::encryptString($recruiter->id);
                $search = ['{{first_name}}', '{{last_name}}', '{{email}}', '{{user_id}}'];
                $replace = [$request->get('first_name'), $request->get('last_name'), $request->get('email'), $recruiterId];
                $modifiedParagraph = str_replace($search, $replace, $emailTemplate->html);

                if ($recruiter) {
                    $agency = Agency::where('id', $request->get('agency_id'))->first();
                    $details = [
                        "notification" => 'super Admin created New Recruiter Created Under Your Agency.',
                        "category" => 'recruiter',
                        "id" => $recruiter->id,
                        "first_name" => $recruiter->first_name,
                        "last_name" => $recruiter->last_name,
                        "email" => $recruiter->email,
                        "created_at" => $recruiter->created_at,
                    ];
                    $agency->notify(new NewNotification($details));
                    $recruiterdetails = [
                        "notification" => 'Your Account successfully created as a Recruiter.',
                        "category" => 'recruiter',
                        "id" => $recruiter->id,
                        "first_name" => $recruiter->first_name,
                        "last_name" => $recruiter->last_name,
                        "email" => $recruiter->email,
                        "created_at" => $recruiter->created_at,
                    ];
                    $recruiter->notify(new NewNotification($recruiterdetails));
                    Mail::to($email)->send(new SendEmailTemplate($modifiedParagraph));
                } else {
                    return response()->json([
                        'error' => "Can't sent mail.Something went wrong!!",
                        'status' => 422
                    ], 422);
                }
            }
            if (!empty($recruiter)) {
                //assign role
                $role = Role::where('id', $recruiter->role_id)->pluck('name')->first();
                $recruiter_role = Role::where(['name' => $role])->first();
                if ($recruiter_role) {
                    $recruiter->assignRole($recruiter_role);
                }
                $recruiterData = new AgencyResource($recruiter);
                // event(new Registered($recruiter));
                $payment = Payment::create([
                    'agency_id' => $recruiter->id,
                    'amount' => null,
                    'subscription_id' => 0,
                    'start_date' => $startDate,
                    'expiry_date' => $currentDate->addMonth()->format('Y-m-d'),
                    'status' => 'Active'
                ]);

                $agency = Agency::find($recruiter->id)->update([
                    'is_subscribed' => "Yes"
                ]);
                return response()->json([
                    'message' => 'New User Created successfully. You can set password using registered Email address!!',
                    'recruiterData' => $recruiterData,
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => "Sorry!! User not created.",
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

    public function updateRecruiter(RegisterRequest $request, $id)
    {
        if ($this->user->hasPermissionTo('recruiters_update')) {
            $recruiter = Agency::find($id);
            $recruiter->update($request->all());
            $role = Role::where('id', $recruiter->role_id)->pluck('name')->first();
            $recruiter_role = Role::where(['name' => $role])->first();
            if ($recruiter_role) {
                // $recruiter->assignRole($recruiter_role);
                $recruiter->syncRoles($recruiter_role);
            }
            if ($recruiter) {
                //Notification for update recruiter
                $details = [
                    "notification" => 'Your Account Details updated successfully.',
                    "category" => 'recruiter',
                    "id" => $recruiter->id,
                    "first_name" => $recruiter->first_name,
                    "last_name" => $recruiter->last_name,
                    "email" => $recruiter->email,
                    "created_at" => $recruiter->created_at
                ];
                $recruiter->notify(new NewNotification($details));

                return response()->json([
                    'message' => 'Recruiter Data Updated successfully.',
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => "Sorry!! Recruiter not upadted.",
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

    public function deleteRecruiter(Request $request)
    {
        if ($this->user->hasPermissionTo('recruiters_delete')) {
            $recruitesrIds = $request->get('recruiter_ids');
            if (!empty($recruitesrIds)) {
                foreach ($recruitesrIds as $recruiterId) {
                    $recruiter = Agency::find($recruiterId);
                    $applications = Application::where('recruiter_id', $recruiterId)->where('is_deleted', 0)->count();
                    $jobs = Job::where('recruiter_id', $recruiterId)->where('is_deleted', 0)->count();
                    if ($applications > 0 || $jobs > 0) {
                        return response()->json(['error' => 'Cannot delete this Recruiter. Applications Or Job already using this recruiter'], 403);
                    } else {
                        $recruiter->update(['is_deleted' => 1]);
                    }
                }

                return response()->json([
                    'message' => 'Recruiter Deleted successfully',
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'message' => "Sorry!! Couldn't delete recruiter.",
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

    public function recruiterList(Request $request)
    {
        if ($this->user->hasPermissionTo('recruiters_list')) {
            $perPage = $request->input('perPage');
            $page = $request->input('page');
            $search = $request->input('search');
            $columnName = $request->input('columnName') ? $request->input('columnName') : 'created_at';
            $type = $request->input('type') ? $request->input('type') : 'desc';
            if ($this->user->hasPermissionTo('enableController')) { // this is added new 
                $recruitersQuery = Agency::with('applications')->where('is_deleted', 0)
                    ->whereNotIn('role_id', [1, 2])
                    ->whereIn('created_by', [Auth::user()->agency_id, 0]);
            } else { // this is old query
                $recruitersQuery = Agency::with('applications')->where('is_deleted', 0)
                    ->whereNotIn('role_id', [1, 2])
                    ->where(function ($query) {
                        $query->whereIn('created_by', [Auth::id(), 0])
                            ->orWhereIn('created_by', [Auth::user()->agency_id, 0]);
                    });
            }

            if ($page || $perPage) {
                if (!empty($search)) {
                    $recruitersQuery->where(function ($query) use ($search) {
                        $query->where('name', 'like', "%$search%")
                            ->orWhere('email', 'like', "%$search%")
                            ->orWhere('address', 'like', "%$search%")
                            ->orWhere('phone', 'like', "%$search%");
                    });
                }
                if ($columnName === 'phone') {
                    $recruiters = $recruitersQuery->orderByRaw("CAST(phone AS UNSIGNED) $type")->paginate($perPage, ['*'], 'page', $page);
                } else {
                    $recruiters = $recruitersQuery->orderBy($columnName, $type)->paginate($perPage, ['*'], 'page', $page);
                }
            } else {
                $recruiters = $recruitersQuery->orderBy('created_at', 'desc')->get();
            }
            if ($recruiters->isNotEmpty()) {
                return response()->json([
                    'message' => 'Recruiters Data Get successfully.',
                    'recruiters' => $recruiters,
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'message' => "No recruiters found.",
                    'recruiters' => $recruiters,
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
}
