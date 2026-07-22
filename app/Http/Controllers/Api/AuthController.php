<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Helper;
use App\Http\Requests\ApplicationRequest;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Resources\AgencyResource;
use App\Mail\SendEmailTemplate;
use App\Mail\SendForgotPasswordEmail;
use App\Mail\SetPasswordForNewSignup;
use App\Models\Agency;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\Country;
use App\Models\EmailTemplate;
use App\Models\Job;
use App\Models\Payment;
use App\Models\Template;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use App\Notifications\NewNotification;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $agency = Agency::create([
            'name' => $request->name,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'other_details' => $request->other_details,
            'address' => $request->address,
            'phone' => $request->phone,
            'email' => $request->email,
            'role_id' => 2
        ]);
        $currentDate = Carbon::now();
        $startDate = $currentDate->format('Y-m-d');

        $payment = Payment::create([
            'agency_id' => $agency->id,
            'amount' => null,
            'subscription_id' => 0,
            'type' => 'subscription',
            'start_date' => $startDate,
            'expiry_date' => $currentDate->addMonth()->format('Y-m-d'),
            'status' => 'Active'
        ]);

        Agency::find($agency->id)->update([
            'is_subscribed' => "Yes"
        ]);
        if ($agency) {
            //assign role
            $agency_role = Role::where(['name' => 'agency'])->first();
            if ($agency_role) {
                $agency->assignRole($agency_role);
            }
            $agencyData = new AgencyResource($agency);
            // event(new Registered($agency));
            $data = ['agency' => $agency, 'eid' => Crypt::encryptString($agency->id)];
            if ($agency) {
                Mail::to($request->email)->send(new SetPasswordForNewSignup($data));
            } else {
                return response()->json([
                    'error' => "Can't sent mail.Something went wrong!!!",
                ]);
            }

            $details = [
                "notification" => 'New agency created successfully.',
                "category" => 'agency',
                "id" => $agency->id,
                "first_name" => $agency->first_name,
                "last_name" => $agency->last_name,
                "email" => $agency->email,
                "created_at" => $agency->created_at,
            ];
            $superadmins = Agency::where('role_id', 1)->get();

            foreach ($superadmins as $superadmin) {
                $superadmin->notify(new NewNotification($details));
            }

            return response()->json([
                'message' => 'New User Created successfully. You can set password using registered Email address!!',
                'agencyData' => $agencyData,
                'payment' => $payment
            ]);
        } else {
            return response()->json([
                'error' => "Sorry!! User not created.",
            ]);
        }
    }

    public function login(LoginRequest $request)
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            Helper::sendError('Email Or Password is wrong !!!');
        }
        $agency = Auth::user();
        $payment = Payment::where('agency_id', $agency->agency_id === null ? $agency->id : $agency->agency_id)->where('status', 'Active')->orderBy('created_at', 'DESC')->first();
        $currentDate = Carbon::now();
        $startDate = $currentDate->format('Y-m-d');
        if (($payment && $payment->expiry_date && $payment->expiry_date > $startDate) || $agency->role_id === 1) {
            if ($agency->is_disabled || $agency->is_deleted) {
                Helper::sendError('Account is disabled or deleted. Please contact support. !!!');
            }
            if ($agency->password === NULL) {
                Helper::sendError('Account is registered but not set password, please set password !!!');
            }
            $agencyData = new AgencyResource($agency);
            $token = $agency->createToken('Token')->plainTextToken;

            return response()->json([
                'message' => 'Agency logged in successfully.',
                'token' => $token,
                'agencyData' => $agencyData,
                'payment' => $payment
            ]);
        } else {
            return response()->json([
                'error' => 'Your Plan is Expired. You need to contact to Admin.',
            ]);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        Auth::guard('web')->logout();
        return response()->json(['message' => 'User logged out successfully.']);
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $email = $request->email;
        $agency = Agency::where('email', $email)->first();
        $data = ['agency' => $agency, 'eid' => Crypt::encryptString($agency->id)];
        if ($data) {
            Mail::to($email)->send(new SendForgotPasswordEmail($data));
            return response()->json(['message' => 'Forgot password link send to your registered email address..!! Please check on mail.']);
        }
        return response()->json(['error' => 'Entered Email is not registered as agency.']);
    }

    public function resetPassword(ResetPasswordRequest $request, $encid)
    {
        $password = $request->password;
        $id = Crypt::decryptString($encid);
        $agency = Agency::find($id);
        $agency->update(['password' => bcrypt($password)]);
        if ($agency) {
            return response()->json(['message' => 'Your password set successfully.']);
        } else {
            return response()->json(['error' => "Couldn't Reset your password."]);
        }
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $auth = Auth::user();

        // The passwords matches
        if (!Hash::check($request->get('old_password'), $auth->password)) {
            return response()->json(['error' => 'Current Password is Invalid.']);
        }

        // Current password and new password same
        if (strcmp($request->get('old_password'), $request->new_password) == 0) {
            return response()->json(['error' => 'New Password cannot be same as your current password.']);
        }

        $agency =  Agency::find($auth->id);
        $agency->password =  bcrypt($request->new_password);
        $agency->save();
        if ($agency) {
            return response()->json(['message' => 'Password changed successfully.']);
        } else {
            return response()->json(['error' => "Sorry!! Couldn't Change your password."]);
        }
    }

    public function getLoginDetailsByEmail(Request $request)
    {
        if ($request->has('email')) {
            $agency = Agency::where('email', $request->input('email'))->first();
            if ($agency) {
                $agencyData = new AgencyResource($agency);
                return response()->json([
                    'message' => 'Agency Details retrieved successfully.',
                    'agencyData' => $agencyData,
                ]);
            } else {
                return response()->json([
                    'error' => 'This Email Account Doesn\'t Exist.',
                ]);
            }
        } else {
            return response()->json(['error' => 'Please Enter Email']);
        }
    }

    //Application and job save in linkedin post share
    public function jobDetails($encid)
    {
        $id = Crypt::decryptString($encid);

        $jobDetails = Job::find($id);
        $countryList = Country::where('created_by', $jobDetails->created_by)->orderBy('created_at', 'desc')->get();
        if ($jobDetails) {
            return response()->json([
                'message' => 'Jobs get successfully.',
                'jobDetails' => $jobDetails,
                'countryList' => $countryList,
                'status' => 200
            ], 200);
        } else {
            return response()->json([
                'error' => "Sorry!! Couldn't get this job.",
                'status' => 422
            ], 422);
        }
    }

    public function addApplication(ApplicationRequest $request)
    {

        $alreadyApply = Application::where('email', $request->get('email'))->where('job_id', $request->get('job_id'))->first();
        if ($alreadyApply) {
            return response()->json(['error' => "This User already applied for this Job.", 'status' => 403], 403);
        }
        $imageName = null;
        if ($request->hasFile('cv')) {
            $image = $request->file('cv');
            $imageName = 'cv_' . time() . '.' . $image->getClientOriginalExtension();
            $image->storePubliclyAs('public/images/cv', $imageName);
        }

        $candidateData = [
            'first_name' => $request->get('first_name'),
            'last_name' => $request->get('last_name'),
            'email' => $request->get('email'),
            'phone' => $request->get('phone'),
            'cv' => $imageName ? 'images/cv/' . $imageName : null,
            'country_id' => $request->get('country_id'),
            'created_by' => 0,
            'experience' => $request->get('experience'),
            'address' => $request->get('address'),
        ];

        $candidate = Candidate::updateOrCreate(
            ['email' => $request->get('email')],
            $candidateData
        );

        $applicationData = array_merge($candidateData, [
            'job_id' => $request->get('job_id'),
            'recruiter_id' => 0,
            'contract_brief' => $request->get('contract_brief'),
            'source' => $request->get('source'),
            'status' => $request->get('status') ? $request->get('status') : "New",
            'candidate_id' => $candidate->id,
        ]);

        $application = Application::create($applicationData);

        if ($imageName) {
            $response = Http::attach(
                'resume',
                file_get_contents($request->file('cv')->path()),
                'resume.pdf'
            )->post('http://74.207.230.150:8000/Matching_Analysis_and_Vacancies', [
                'applied_job_id' => $request->get('job_id')
            ]);

            if ($response->successful()) {
                $matchData = $response->json();
                $candidate->update(['match_payload' => $matchData]);
                $application->update(['match_payload' => $matchData]);
            }
        }

        $recruiterEmail = Agency::find($request->get('recruiter_id'));

        $temp = Template::where('name', 'Application Registration Success Email')->first();
        $emailTemplate = EmailTemplate::where('template_id', $temp->id)->first();
        $search = ['{{first_name}}', '{{last_name}}', '{{email}}'];
        $replace = [$request->get('first_name'), $request->get('last_name'), $request->get('email')];
        $modifiedParagraph = str_replace($search, $replace, $emailTemplate->html);

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
}
