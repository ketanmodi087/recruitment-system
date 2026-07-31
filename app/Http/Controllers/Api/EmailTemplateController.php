<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EmailTemplateController extends Controller
{
    private $user;
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            return $next($request);
        });
    }
    public function emailTemplateGenerate(Request $request)
    {
        if ($this->user->hasPermissionTo('email_template_add')) {
            $email = $request->email;
            Mail::send('emails.email-template', ['html' => $request->get('html')], function ($message, $email) {
                $message->to($email)->subject('Email Template');
            });
            return response()->json(['message' => 'Email sent']);
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ], 403);
        }
    }

    public function addTemplate(Request $request)
    {
        if ($this->user->hasPermissionTo('email_template_add')) {
            $template = Template::create([
                'name' => $request->get('name'),
                'created_by' => Auth::id()
            ]);
            if ($template) {
                return response()->json([
                    'message' => 'Template Added successfully.',
                ]);
            } else {
                return response()->json([
                    'error' => "Sorry!! Couldn't create Template.",
                    'status' => 422
                ]);
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ], 403);
        }
    }

    public function listTemplate()
    {
        if ($this->user->hasPermissionTo('email_template_list')) {
            $templates = Template::all();
            if ($templates->isNotEmpty()) {
                return response()->json([
                    'message' => 'Templates get successfully.',
                    'templates' => $templates,
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'message' => "No templates found.",
                    'templates' => $templates,
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

    public function addEditEmailTemplate(Request $request)
    {
        if ($this->user->hasPermissionTo('email_template_add')) {
            $template = EmailTemplate::where('template_id', $request->get('template_id'))->first();
            if ($template) {
                if ($request->hasFile('logo')) {
                    $image = $request->file('logo');
                    $validator = Validator::make($request->all(), [
                        'logo' => 'image|mimes:jpeg,png,jpg,gif|max:2048', // Modify the max file size as needed
                    ]);
                    // Check if validation fails
                    if ($validator->fails()) {
                        return response()->json(['error' => $validator->errors()], 400);
                    }
                    // Generate a unique name for the image
                    $imageName = 'logo_' . time() . '.' . $image->getClientOriginalExtension();

                    // Store the image in the storage folder (you might need to configure storage in Laravel)
                    $image->storePubliclyAs('public/images/email_template', $imageName);
                    $template->update([
                        'logo' => 'images/email_template/' . $imageName
                    ]);
                }
                $template->update([
                    'template_id' => $request->get('template_id'),
                    'title' => $request->get('title'),
                    'description' => $request->get('description'),
                    'addition_texts' => $request->get('addition_texts'),
                    'buttons' => $request->get('buttons'),
                    'html' => $request->get('html'),
                ]);
            } else {
                $imageName = "";
                if ($request->hasFile('logo')) {
                    $image = $request->file('logo');
                    $validator = Validator::make($request->all(), [
                        'logo' => 'image|mimes:jpeg,png,jpg,gif|max:2048', // Modify the max file size as needed
                    ]);
                    // Check if validation fails
                    if ($validator->fails()) {
                        return response()->json(['error' => $validator->errors()], 400);
                    }
                    // Generate a unique name for the image
                    $imageName = 'logo_' . time() . '.' . $image->getClientOriginalExtension();

                    // Store the image in the storage folder (you might need to configure storage in Laravel)
                    $image->storePubliclyAs('public/images/email_template', $imageName);
                }
                $template = EmailTemplate::create([
                    'template_id' => $request->get('template_id'),
                    'title' => $request->get('title'),
                    'description' => $request->get('description'),
                    'logo' => $imageName ? 'images/email_template/' . $imageName : "",
                    'addition_texts' => $request->get('addition_texts'),
                    'buttons' => $request->get('buttons'),
                    'html' => $request->get('html'),
                    'created_by' => Auth::id()
                ]);
            }


            if ($template) {
                return response()->json([
                    'message' => 'Email Template Added successfully.',
                ]);
            } else {
                return response()->json([
                    'error' => "Sorry!! Couldn't create Template.",
                    'status' => 422
                ]);
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ], 403);
        }
    }

    public function getEmailTemplate($id)
    {
        if ($this->user->hasPermissionTo('email_template_list')) {
            $template = EmailTemplate::where('template_id', $id)->first();
            if ($template) {
                return response()->json([
                    'message' => 'Email Template get successfully.',
                    'template' => $template
                ]);
            } else {
                return response()->json([
                    'error' => "Sorry!! Couldn't get Template.",
                    'status' => 422
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
