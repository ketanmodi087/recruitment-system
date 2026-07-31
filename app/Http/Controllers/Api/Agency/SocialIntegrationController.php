<?php

namespace App\Http\Controllers\Api\Agency;

use App\Http\Controllers\Controller;
use App\Models\SocialIntegration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class SocialIntegrationController extends Controller
{
    private $user;
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            return $next($request);
        });
    }
    public function saveToken(Request $request, $type)
    {
        if ($this->user->hasPermissionTo('integration_savetoken')) {
            $integration = SocialIntegration::where('agency_id', Auth::id())->where('type', $type)->first();

            if ($type === "facebook") {
                $response = Http::get('https://graph.facebook.com/v19.0/oauth/access_token?grant_type=fb_exchange_token&client_id=' . $request->client_id . '&client_secret=' . $request->client_secret . '&fb_exchange_token=' . $request->token);
                if ($response->successful()) {
                    $user_id = 122122187564168374;
                    $accessToken = $response->json()['access_token'];
                    $accounts = Http::get('https://graph.facebook.com/' . $user_id . '/accounts?access_token=' .  $accessToken);
                    $live_long_token = $accounts->json()['data'][0]['access_token'];
                    $token = $live_long_token;
                } else {
                    return response()->json([
                        'error' => "Sorry!! Token is not Valid.",
                        'status' => 422
                    ], 422);
                }
            } elseif ($type === "linkedin") {
                $response = Http::post('https://www.linkedin.com/oauth/v2/accessToken?code=' . $request->code . '&client_id=' . $request->client_id . '&client_secret=' . $request->client_secret . '&grant_type=authorization_code&redirect_uri=' . env("REACT_APP_URL") . '/linkedin');
                if ($response->successful()) {
                    $token = $response->json()['access_token'];
                } else {
                    return response()->json([
                        'error' => "Sorry!! Token is not Valid.",
                        'status' => 422
                    ], 422);
                }
            }
            if ($integration) {
                $integration->update([
                    'client_id' => $request->client_id,
                    'client_secret' => $request->client_secret,
                    'token' => $token ? $token : "",
                    'type' => $type
                ]);
            } else {
                $integration = SocialIntegration::create([
                    'client_id' => $request->client_id,
                    'client_secret' => $request->client_secret,
                    'token' => $token ? $token : "",
                    'agency_id' => Auth::id(),
                    'type' => $type
                ]);
            }

            if ($integration) {
                return response()->json([
                    'message' => 'Token saved successfully.',
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => 'Sorry!! Token not saved.',
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

    public function getToken($type)
    {
        if ($this->user->hasPermissionTo('integration_gettoken')) {
            $tokenData = SocialIntegration::where('type', $type)
                ->where('agency_id', Auth::id())
                ->first();
            if ($tokenData) {
                return response()->json([
                    'message' => 'Token get successfully.',
                    'tokenData' => $tokenData,
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => "Sorry!! Couldn't get token.",
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
