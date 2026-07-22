<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Events\AgencyDisabled;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\Agency;
use App\Models\Payment;
use App\Models\Recruiter;
use App\Models\Subscription;
use Carbon\Carbon;
use Hamcrest\Arrays\IsArray;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AgencyController extends Controller
{
    private $user;
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            return $next($request);
        });
    }
    public function agencyList(Request $request)
    {
        if ($this->user->hasPermissionTo('agency_list')) {
            $perPage = $request->input('perPage');
            $page = $request->input('page');
            $search = $request->input('search');
            $columnName = $request->input('columnName') ? $request->input('columnName') : 'created_at';
            $type = $request->input('type') ? $request->input('type') : 'desc';

            $agencyQuery = Agency::select('agencies.*', \DB::raw('(SELECT COUNT(*) FROM agencies AS recruiters_count WHERE recruiters_count.is_deleted = 0 AND recruiters_count.created_by = agencies.id  OR recruiters_count.created_by = 0 ) as recruiters_count'))
                ->where('is_deleted', 0)
                ->where('role_id', 2)
                ->where('id', '!=', Auth::id());
            if ($page || $perPage) {
                if (!empty($search)) {
                    $agencyQuery->where(function ($query) use ($search) {
                        $query->where('first_name', 'like', "%$search%")
                            ->orWhere('last_name', 'like', "%$search%")
                            ->orWhere('email', 'like', "%$search%")
                            ->orWhere('phone', 'like', "%$search%");
                    });
                }
                if ($columnName === 'phone') {
                    $agencies = $agencyQuery->orderByRaw("CAST(phone AS UNSIGNED) $type")->paginate($perPage, ['*'], 'page', $page);
                } else {
                    $agencies = $agencyQuery->orderBy($columnName, $type)->paginate($perPage, ['*'], 'page', $page);
                }
            } else {
                $agencies = $agencyQuery->orderBy('created_at', 'desc')->get();
            }


            if ($agencies->isNotEmpty()) {
                return response()->json([
                    'message' => 'Agency List get successfully.',
                    'agencies' => $agencies,
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'message' => "No Agency Found.",
                    'agencies' => $agencies,
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

    public function viewAgency($id, Request $request)
    {
        if ($this->user->hasPermissionTo('agency_view')) {
            $agency = Agency::find($id);
            if ($agency) {
                return response()->json([
                    'message' => 'Agency List get successfully.',
                    'agency' => $agency,
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => "Sorry!! Couldn't get agency view details.",
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

    public function agencyRecruiterList($id, Request $request)
    {
        if ($this->user->hasPermissionTo('agency_view')) {
            $perPage = $request->input('perPage');
            $page = $request->input('page');
            $search = $request->input('search');
            $columnName = $request->input('columnName') ? $request->input('columnName') : 'created_at';
            $type = $request->input('type') ? $request->input('type') : 'desc';
            $agency = Agency::find($id);
            $recruitersQuery = Agency::where('is_deleted', 0)
                ->whereIn('created_by', [$agency->id, 0]);

            if ($page || $perPage) {
                if (!empty($search)) {
                    $recruitersQuery->where(function ($query) use ($search) {
                        $query->where('first_name', 'like', "%$search%")
                            ->orWhere('last_name', 'like', "%$search%")
                            ->orWhere('email', 'like', "%$search%")
                            ->orWhere('phone', 'like', "%$search%")
                            ->orWhere('name', 'like', "%$search%");
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
            if ($recruiters) {
                return response()->json([
                    'message' => 'Agency Recruiter List get successfully.',
                    'recruiters' => $recruiters,
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => "Sorry!! Couldn't get agency view details.",
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

    public function agencyPaymentList($id, Request $request)
    {
        if ($this->user->hasPermissionTo('agency_view')) {
            $perPage = $request->input('perPage');
            $page = $request->input('page');
            $search = $request->input('search');
            $columnName = $request->input('columnName') ? $request->input('columnName') : 'created_at';
            $type = $request->input('type') ? $request->input('type') : 'desc';
            $agency = Agency::find($id);
            $paymentQuery = Payment::where('agency_id', $agency->id);

            if ($page || $perPage) {
                if (!empty($search)) {
                    $paymentQuery->where(function ($query) use ($search) {
                        $query->where('amount', 'like', "%$search%")
                            ->orWhere(function ($query) use ($search) {
                                $query->whereRaw("DATE_FORMAT(start_date, '%b %d, %Y') like '%$search%'");
                            })
                            ->orWhere(function ($query) use ($search) {
                                $query->whereRaw("DATE_FORMAT(expiry_date, '%b %d, %Y') like '%$search%'");
                            })
                            ->orWhere('status', 'like', "%$search%");
                    });
                }

                if ($columnName === 'amount') {
                    $payments = $paymentQuery->orderByRaw("CAST(amount AS UNSIGNED) $type")->paginate($perPage, ['*'], 'page', $page);
                } else {
                    $payments = $paymentQuery->orderBy($columnName, $type)->paginate($perPage, ['*'], 'page', $page);
                }
            } else {
                $payments = $paymentQuery->orderBy('created_at', 'desc')->get();
            }
            if ($payments) {
                return response()->json([
                    'message' => 'Agency Payment List get successfully.',
                    'payments' => $payments,
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => "Sorry!! Couldn't get agency view details.",
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

    public function updateAgency(RegisterRequest $request, $id)
    {
        if ($this->user->hasPermissionTo('agency_update')) {
            $agency = Agency::find($id);
            $agency->update($request->all());
            if ($agency) {
                return response()->json([
                    'message' => 'Update agency details successfully.',
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'message' => "Sorry!! Couldn't update agency data.",
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

    public function disabledAgency(Request $request, $id)
    {

        if ($this->user->hasPermissionTo('agency_disable')) {
            $agency = Agency::find($id);

            $agency->update([
                'is_disabled' => $request->get('is_disabled')
            ]);
            if ($request->get('is_disabled') == '1') {
                // event(new AgencyDisabled($agency));
                return response()->json([
                    'message' => 'Agency disabled successfully.',
                    'status' => 200
                ], 200);
            } else if ($request->get('is_disabled') == '0') {
                return response()->json([
                    'message' => 'Agency enabled successfully.',
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'message' => "Sorry!! Couldn't update agency data.",
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

    public function deleteAgency(Request $request)
    {
        if ($this->user->hasPermissionTo('agency_delete')) {
            $agencyIds = $request->get('agency_ids');
            if (!empty($agencyIds)) {
                foreach ($agencyIds as $agencyId) {
                    $agency = Agency::find($agencyId);
                    $agency->update(['is_deleted' => 1]);
                }
                return response()->json([
                    'message' => 'Agencies  deleted successfully.',
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Error to delete agencies',
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

    public function addPayment(PaymentRequest $request, $id)
    {
        if ($this->user->hasPermissionTo('agency_view')) {
            $lastpayments = Payment::where('agency_id', $id)->get();
            foreach ($lastpayments as $pay) {
                $pay->update([
                    'status' => 'Inactive'
                ]);
            }
            $payment = Payment::create([
                'agency_id' => $id,
                'start_date' => $request->start_date,
                'expiry_date' => $request->expiry_date,
                'subscription_id' => $request->subscription_id ? $request->subscription_id : null,
                'type' => $request->type,
                'amount' => $request->amount,
                'other_details' => $request->other_details,
                'transaction_id' => $request->transaction_id,
                'status' => "Active"
            ]);

            $agency = Agency::find($id)->update([
                'is_subscribed' => "Yes"
            ]);

            if ($payment) {
                return response()->json([
                    'message' => 'Payment Added successfully.',
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => "Sorry!! Couldn't add payment.",
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
