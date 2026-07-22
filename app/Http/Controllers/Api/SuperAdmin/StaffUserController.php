<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\AgencyResource;
use App\Mail\SetPasswordForNewSignup;
use App\Models\Agency;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

class StaffUserController extends Controller
{

    private $user;
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            return $next($request);
        });
    }

    public function staffUserList(Request $request)
    {
        if ($this->user->hasPermissionTo('staffusers_list')) {
            $perPage = $request->input('perPage');
            $page = $request->input('page');
            $search = $request->input('search');
            $columnName = $request->input('columnName') ? $request->input('columnName') : 'created_at';
            $type = $request->input('type') ? $request->input('type') : 'desc';

            $staffUserQuery = Agency::where('is_deleted', 0)
                ->where('role_id', 1)
                ->where('id', '!=', Auth::id());

            if ($page || $perPage) {
                if (!empty($search)) {
                    $staffUserQuery->where(function ($query) use ($search) {
                        $query->where('first_name', 'like', "%$search%")
                            ->orWhere('last_name', 'like', "%$search%")
                            ->orWhere('name', 'like', "%$search%")
                            ->orWhere('email', 'like', "%$search%")
                            ->orWhere('phone', 'like', "%$search%");
                    });
                }
                if ($columnName === 'phone') {
                    $staffUser = $staffUserQuery->orderByRaw("CAST(phone AS UNSIGNED) $type")
                        ->paginate($perPage, ['*'], 'page', $page);
                } else {
                    $staffUser = $staffUserQuery->orderBy($columnName, $type)->paginate($perPage, ['*'], 'page', $page);
                }
            } else {
                $staffUser = $staffUserQuery
                    ->orderBy('created_at', 'DESC')->get();
            }
            if ($staffUser->isNotEmpty()) {
                return response()->json([
                    'message' => 'StaffUser List get successfully',
                    'staffUser' => $staffUser,
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'message' => "No staffusers found.",
                    'staffUser' => $staffUser,
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

    public function addStaffUser(RegisterRequest $request)
    {
        if ($this->user->hasPermissionTo('staffusers_add')) {
            $staffUser = Agency::create([
                'name' => $request->get('name'),
                'first_name' => $request->get('first_name'),
                'last_name' => $request->get('last_name'),
                'email' => $request->get('email'),
                'phone' => $request->get('phone'),
                'other_details' => $request->get('other_details'),
                'created_by' => Auth::id(),
                'role_id' => 1
            ]);

            if (!empty($staffUser)) {
                //assign role 
                $superadmin_role = Role::where(['name' => 'superadmin'])->first();
                if ($superadmin_role) {
                    $staffUser->assignRole($superadmin_role);
                }
                $staffUserData = new AgencyResource($staffUser);
                // event(new Registered($staffUser));
                $data = ['agency' => $staffUser, 'eid' => Crypt::encryptString($staffUser->id)];
                if ($staffUser) {
                    Mail::to($request->email)->send(new SetPasswordForNewSignup($data));
                } else {
                    return response()->json([
                        'error' => "Can't sent mail.Something went wrong!!",
                    ]);
                }

                return response()->json([
                    'message' => 'New User Created successfully. You can set password using registered Email address!!',
                    'staffUserData' => $staffUserData,
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

    public function updateStaffUser(RegisterRequest $request, $id)
    {
        if ($this->user->hasPermissionTo('staffusers_update')) {
            $staffUser = Agency::find($id);
            $staffUser->update($request->all());
            if ($staffUser) {
                return response()->json([
                    'message' => 'StaffUser Data Updated successfully.',
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => "Sorry!! Couldn't update recruiter.",
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

    public function deleteStaffUser(Request $request)
    {
        if ($this->user->hasPermissionTo('staffusers_delete')) {
            $staffUserIds = $request->get('staffuser_ids');
            if (!empty($staffUserIds)) {
                foreach ($staffUserIds as $staffUserId) {
                    $staffUser = Agency::find($staffUserId);
                    $staffUser->update(['is_deleted' => 1]);
                }
                return response()->json([
                    'message' => 'StaffUsers Deleted successfully',
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => "Sorry!! Couldn't delete staffUsers.",
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
}
