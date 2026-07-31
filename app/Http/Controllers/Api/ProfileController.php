<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileRequest;
use App\Http\Resources\AgencyResource;
use App\Models\Agency;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{

    private $user;
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            return $next($request);
        });
    }

    public function profileUpdate(ProfileRequest $request)
    {
        $profile = Agency::find(Auth::id())->update([
            'first_name'   => $request->get('first_name'),
            'last_name'    => $request->get('last_name'),
            'phone'    => $request->get('phone'),
            'address'    => $request->get('address'),
            'other_details'    => $request->get('other_details'),
            'country' => $request->get('country')
        ]);
        if ($profile) {
            return response()->json([
                'message' => 'Profile Details updated successfully!!',
                'status' => 200
            ], 200);
        } else {
            return response()->json([
                'error' => "Sorry!! Couldn't update profile.",
                'status' => 422
            ], 422);
        }
    }

    public function profilePicUpdate(Request $request)
    {
        $agency = Agency::find(Auth::id());
        if ($request->hasFile('profile')) {
            $image = $request->file('profile');
            $validator = Validator::make($request->all(), [
                'profile' => 'image|mimes:jpeg,png,jpg,gif|max:2048', // Modify the max file size as needed
            ]);
            // Check if validation fails
            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 400);
            }

            $file = $request->file('profile');
            $filename = 'profile_' . time() . '.' . $image->getClientOriginalExtension();
            $file->storePubliclyAs('public/images/profiles/' . $filename);
            $agency->update([
                'profile' => 'images/profiles/' . $filename,
            ]);
            return response()->json([
                'message' => 'Profile Details updated successfully!!',
                'status' => 200
            ], 200);
        } else {
            $agency->update([
                'profile' => null,
            ]);
            return response()->json([
                'error' => 'Profile has been removed successfully!!',
                'status' => 200
            ], 200);
        }
        return response()->json([
            'error' => 'Something went wrong!! Please select valid image.',
            'status' => 422
        ], 422);
    }

    public function userPicUpdate(Request $request, $id)
    {
        if ($request->hasFile('profile')) {
            $image = $request->file('profile');
            $validator = Validator::make($request->all(), [
                'profile' => 'image|mimes:jpeg,png,jpg,gif|max:2048', // Modify the max file size as needed
            ]);
            // Check if validation fails
            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 400);
            }
            // Generate a unique name for the image
            $imageName = 'profile_' . time() . '.' . $image->getClientOriginalExtension();

            // Store the image in the storage folder (you might need to configure storage in Laravel)
            $file = $request->file('profile');
            $file->storePubliclyAs('public/images/profiles/' . $imageName);
            Agency::find($id)->update([
                'profile' => 'images/profiles/' . $imageName,
            ]);
            return response()->json([
                'message' => 'Profile Details updated successfully!!',
                'status' => 200
            ], 200);
        } else {
            Agency::find(Auth::id())->update([
                'profile' => null,
            ]);
            return response()->json([
                'error' => 'Profile has been removed successfully!!',
                'status' => 200
            ], 200);
        }
        return response()->json([
            'error' => 'Something went wrong!! Please select valid image.',
            'status' => 422
        ], 422);
    }

    public function paymentHistory(Request $request)
    {
        if ($this->user->hasPermissionTo('payment_history')) {
            $perPage = $request->input('perPage');
            $page = $request->input('page');
            $search = $request->input('search');
            $columnName = $request->input('columnName') ? $request->input('columnName') : 'created_at';
            $type = $request->input('type') ? $request->input('type') : 'desc';
            if (Auth::user()->agency_id != null) {
                $paymentQuery = Payment::where('agency_id', Auth::user()->agency_id);
            } else {
                $paymentQuery = Payment::where('agency_id', Auth::id());
            }

            if ($page || $perPage) {
                if (!empty($search)) {
                    $paymentQuery->where(function ($query) use ($search) {
                        $query->where('amount', 'like', "%$search%")
                            ->orWhere('status', 'like', "%$search%")
                            ->orWhere(function ($query) use ($search) {
                                $query->whereRaw("DATE_FORMAT(start_date, '%b %d, %Y') like '%$search%'");
                            })
                            ->orWhere(function ($query) use ($search) {
                                $query->whereRaw("DATE_FORMAT(expiry_date, '%b %d, %Y') like '%$search%'");
                            });
                    });
                }
                $payments = $paymentQuery->orderByRaw("$columnName $type")->paginate($perPage, ['*'], 'page', $page);
            } else {
                $payments = $paymentQuery
                    ->orderBy('created_at', 'DESC')->get();
            }
            if (Auth::user()->role_id == 3) {
                $currentPlan = Payment::with('subscription')->where('agency_id', Auth::user()->agency_id)->orderBy('created_at', 'DESC')->first();
            } else {
                $currentPlan = Payment::with('subscription')->where('agency_id', Auth::id())->orderBy('created_at', 'DESC')->first();
            }
            if (!empty($payments)) {
                return response()->json([
                    'message' => 'Payments get successfully',
                    'payments' => $payments,
                    'currentPlan' => $currentPlan,
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => "Sorry!! Couldn't found payments.",
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

    public function getProfile()
    {
        $agencyUser = auth()->user();
        $agencyData = new AgencyResource($agencyUser);
        $agency = Agency::find(Auth::id());
        if ($agency) {
            return response()->json([
                'message' => 'Get Profile Details successfully!!',
                'agency' => $agency,
                'agencyData' => $agencyData,
                'status' => 200
            ], 200);
        } else {
            return response()->json([
                'message' => 'Sorry!! User not found.',
                'status' => 422
            ], 422);
        }
    }
}
