<?php

namespace App\Http\Controllers\Api\Agency;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationChecklist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApplicationChecklistController extends Controller
{
    private $user;
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            return $next($request);
        });
    }

    public function saveCheckList(Request $request, $id)
    {
        if (!$this->user->hasPermissionTo('applications_view')) {
            return response()->json(['error' => "This role doesn't have permission.", 'status' => 403], 403);
        }
        $checklist = ApplicationChecklist::updateOrCreate(
            [
                'created_by' => Auth::id(),
                'application_id' => $id,
            ],
            [
                'application_id' => $id,
                'type' => $request->get('type'),
                'created_by' => Auth::id(),
                'checklist' => $request->get('checklist')
            ]
        );
        if ($checklist) {
            return response()->json([
                'message' => 'Your Application get Ready to Hire now.',
                'checklist' => $checklist,
                'status' => 200
            ], 200);
        } else {
            return response()->json([
                'message' => "No checklist found.",
                'checklist' => $checklist,
                'status' => 200
            ]);
        }
    }
}
