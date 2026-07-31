<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubscriptionRequest;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{
    private $user;
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            return $next($request);
        });
    }
    public function subscriptionList(Request $request)
    {
        if ($this->user->hasPermissionTo('subscriptionplans_list')) {
            $perPage = $request->input('perPage');
            $page = $request->input('page');
            $search = $request->input('search');
            $columnName = $request->input('columnName') ? $request->input('columnName') : 'created_at';
            $type = $request->input('type') ? $request->input('type') : 'desc';
            $subscriptionsQuery = Subscription::where('is_deleted', 0);

            if ($page || $perPage) {
                if (!empty($search)) {
                    $subscriptionsQuery->where(function ($query) use ($search) {
                        $query->where('plan_duration', 'like', "%$search%")
                            ->orWhere('amount', 'like', "%$search%")
                            ->orWhere('tags', 'like', "%$search%")
                            ->orWhere('status', 'like', "%$search%");
                    });
                }
                if ($columnName === 'amount') {
                    $subscriptions = $subscriptionsQuery->orderByRaw("CAST(amount AS UNSIGNED) $type")
                        ->paginate($perPage, ['*'], 'page', $page);
                } else {
                    $subscriptions = $subscriptionsQuery->orderBy($columnName, $type)
                        ->paginate($perPage, ['*'], 'page', $page);
                }
            } else {
                $subscriptions = $subscriptionsQuery->orderBy($columnName, $type)
                    ->get();
            }
            if ($subscriptions->isNotEmpty()) {
                return response()->json([
                    'message' => 'Subscription List get successfully',
                    'subscriptions' => $subscriptions,
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'message' => "No Subscription Plan.",
                    'subscriptions' => $subscriptions,
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

    public function addSubscription(SubscriptionRequest $request)
    {
        if ($this->user->hasPermissionTo('subscriptionplans_add')) {
            $subscription = Subscription::create([
                'plan_duration' => $request->get('plan_duration'),
                'amount' => $request->get('amount'),
                'tags' => $request->get('tags'),
                'status' => $request->get('status'),
            ]);
            if ($subscription) {
                return response()->json([
                    'message' => 'New subscription Added successfully',
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => "Sorry!! Couldn't add new subscription ",
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

    public function updateSubscription(SubscriptionRequest $request, $id)
    {
        if ($this->user->hasPermissionTo('subscriptionplans_update')) {
            $subscription = Subscription::find($id);
            $subscription->update($request->all());
            if ($subscription) {
                return response()->json([
                    'message' => 'New subscription Updated successfully',
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => "Sorry!! Couldn't Updated subscription ",
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

    public function deleteSubscription(Request $request)
    {
        if ($this->user->hasPermissionTo('subscriptionplans_delete')) {
            $subscriptionIds = $request->get('subscription_ids');
            if (!empty($subscriptionIds)) {
                foreach ($subscriptionIds as $subscriptionId) {
                    $subscription = Subscription::find($subscriptionId);
                    $subscription->update(['is_deleted' => 1]);
                }
                return response()->json([
                    'message' => 'subscription Deleted successfully',
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'message' => "Sorry!! Couldn't delete subscription.",
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

    public function getStartExpiryDate(Request $request)
    {
        $expiry_date = "";
        $currentDate = Carbon::now();
        $startDate = $currentDate->format('Y-m-d');
        $subscription = Subscription::find($request->subscription_id);
        if ($subscription->plan_duration === "1 Month") {
            $expiry_date = $currentDate->addMonth()->format('Y-m-d');
        } elseif ($subscription->plan_duration === "3 Months") {
            $expiry_date = $currentDate->addMonths(3)->format('Y-m-d');
        } elseif ($subscription->plan_duration === "6 Months") {
            $expiry_date = $currentDate->addMonths(6)->format('Y-m-d');
        } elseif ($subscription->plan_duration === "1 Year") {
            $expiry_date = $currentDate->addYear()->format('Y-m-d');
        }

        if ($startDate && $expiry_date) {
            return response()->json([
                'message' => 'Subscription Plan start & expiry date successfully',
                'startDate' => $startDate,
                'expiryDate' => $expiry_date,
                'status' => 200
            ], 200);
        } else {
            return response()->json([
                'message' => "Please Select Valid plan duration",
                'status' => 422
            ], 422);
        }
    }
}
