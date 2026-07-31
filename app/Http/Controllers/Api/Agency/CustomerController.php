<?php

namespace App\Http\Controllers\Api\Agency;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRequest;
use App\Http\Requests\InvoiceRequest;
use App\Models\Agency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\Payment;
use PDF;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class CustomerController extends Controller
{
    private $user;
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            return $next($request);
        });
    }
    public function customerList(Request $request)
    {
        if ($this->user->hasPermissionTo('customer_list')) {
            $recruiterIds = Agency::where('agency_id', Auth::id())->pluck('id')->toArray();

            $perPage = $request->input('perPage');
            $page = $request->input('page');
            $search = $request->input('search');
            $columnName = $request->input('columnName') ? $request->input('columnName') : 'created_at';
            $type = $request->input('type') ? $request->input('type') : 'desc';

            $customersQuery = Customer::where('is_deleted', 0)
                ->where(function ($q) use ($recruiterIds) {
                    $q->where('created_by', intval(Auth::id()))
                        ->orWhere('created_by', Auth::user()->agency_id)
                        ->orWhereIn('customers.created_by', $recruiterIds); // All recruiters under the agency
                });
            // ->where('created_by', Auth::id())
            // ->orWhere('created_by', Auth::user()->agency_id); // add this line 
            if ($page || $perPage) {
                if (!empty($search)) {
                    $customersQuery->where(function ($query) use ($search) {
                        $query->where('name', 'like', "%$search%")
                            ->orWhere('email', 'like', "%$search%")
                            ->orWhere('contract_ref_no', 'like', "%$search%")
                            ->orWhere('phone', 'like', "%$search%");
                    });
                }
                if ($columnName === 'phone') {
                    $customers = $customersQuery->orderByRaw("CAST(phone AS UNSIGNED) $type")->paginate($perPage, ['*'], 'page', $page);
                } else {
                    $customers = $customersQuery->orderBy($columnName, $type)->paginate($perPage, ['*'], 'page', $page);
                }
            } else {
                $customers = $customersQuery->orderBy('created_at', 'desc')->get();
            }
            if ($customers->isNotEmpty()) {
                return response()->json([
                    'message' => 'All customers retrieved successfully.',
                    'customers' => $customers,
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'message' => "No customers found.",
                    'customers' => [],
                    'status' => 200
                ]);
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ],  403);
        }
    }
    public function addCustomer(CustomerRequest $request)
    {
        if ($this->user->hasPermissionTo('customer_add')) {
            $agency = Auth::user();
            if (Auth::user()->agency_id != null) {
                $payment = Payment::where('agency_id', $agency->agency_id)->orderBy('created_at', 'DESC')->where('status', 'Active')->first();
            } else {
                $payment = Payment::where('agency_id', $agency->id)->orderBy('created_at', 'DESC')->where('status', 'Active')->first();
            }
            $currentDate = Carbon::now();
            $startDate = $currentDate->format('Y-m-d');
            if ($payment->subscription_id === "Free" && ($payment && $payment->expiry_date && $payment->expiry_date > $startDate)) {
                $customers = Customer::where('is_deleted', 0)->where('created_by', Auth::id())->get();
                if (count($customers) > 2) {
                    return response()->json([
                        'error' => "Sorry!! You can't create more then 3 customer in this free plan.",
                        'status' => 422
                    ], 422);
                } else {
                    $customer = Customer::create([
                        'created_by' => Auth::id(),
                        'name' => $request->get('name'),
                        'contract_ref_no' => $request->get('contract_ref_no'),
                        'email' => $request->get('email'),
                        'phone' => $request->get('phone'),
                        'contract_brief' => $request->get('contract_brief'),
                        'project_lead_name' => $request->get('project_lead_name'),
                        'project_lead_phone' => $request->get('project_lead_phone')
                    ]);
                }
            } else {
                $customer = Customer::create([
                    'created_by' => Auth::id(),
                    'name' => $request->get('name'),
                    'contract_ref_no' => $request->get('contract_ref_no'),
                    'email' => $request->get('email'),
                    'phone' => $request->get('phone'),
                    'contract_brief' => $request->get('contract_brief'),
                    'project_lead_name' => $request->get('project_lead_name'),
                    'project_lead_phone' => $request->get('project_lead_phone')
                ]);
            }

            if ($customer) {
                return response()->json([
                    'message' => 'New Customer created successfully.',
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => "Sorry!! Customer not Created.",
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
    public function updateCustomer(CustomerRequest $request, $id)
    {
        if ($this->user->hasPermissionTo('customer_update')) {
            $customer = Customer::find($id);
            $customer->update($request->all());
            if ($customer) {
                return response()->json([
                    'message' => 'Customer updated successfully.',
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => "Sorry!! Customer notupdated.",
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

    public function viewCustomer($id)
    {
        if ($this->user->hasPermissionTo('customer_view')) {
            $customerData = Customer::find($id);
            if ($customerData) {
                return response()->json([
                    'message' => 'Get customer details successfully.',
                    'customerData' => $customerData,
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => "Sorry!! Couldn't get customer view details.",
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

    public function deleteCustomer(Request $request)
    {
        if ($this->user->hasPermissionTo('customer_delete')) {
            $customer_ids = $request->get('customer_ids');
            if (!empty($customer_ids)) {
                foreach ($customer_ids as $customer_id) {
                    $customer = Customer::find($customer_id);
                    $jobs = Job::where('customer_id', $customer_id)->where('is_deleted', 0)->count();
                    if ($jobs > 0) {
                        return response()->json(['error' => 'Cannot delete this Customer. Applications Or Job already using this Customer'], 403);
                    } else {
                        $customer->update(['is_deleted' => 1]);
                        $job = Job::where('customer_id', $customer_id)->update(['is_deleted' => 1]);
                    }
                }
                return response()->json([
                    'message' => 'Customer Deleted successfully',
                    'status' => 200
                ],  200);
            } else {
                return response()->json([
                    'message' => "Sorry!! Couldn't delete Customer.",
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

    public function checkFreePlan(Request $request)
    {
        $agency = Auth::user();
        if ($agency->agency_id != "") {
            $payment = Payment::where('agency_id', $agency->agency_id)->orderBy('created_at', 'DESC')->where('status', 'Active')->first();
        } else {
            $payment = Payment::where('agency_id', $agency->id)->orderBy('created_at', 'DESC')->where('status', 'Active')->first();
        }
        $currentDate = Carbon::now();
        $startDate = $currentDate->format('Y-m-d');
        if ($payment->subscription_id === 0 && ($payment && $payment->expiry_date && $payment->expiry_date > $startDate)) {
            if ($request->type === "customers") {
                $customers = Customer::where('is_deleted', 0)->where('created_by', Auth::id())->get();
                if (count($customers) > 2) {
                    return response()->json([
                        'error' => "Sorry!! You can't create more then 3 customer in this free plan.",
                        'status' => 422
                    ]);
                }
            } else if ($request->type === "jobs") {
                $job = Job::where('is_deleted', 0)->where('created_by', Auth::id())->get();

                if (count($job) > 9) {
                    return response()->json([
                        'error' => "Sorry!! You can't create more then 10 jobs in this free plan.",
                        'status' => 422
                    ]);
                }
            } else if ($request->type === "recruiters") {
                $recruiter = Agency::where('is_deleted', 0)->where('agency_id', Auth::id())->get();
                if (count($recruiter) > 4) {
                    return response()->json([
                        'error' => "Sorry!! You can't create more then 5 Recruiters in this free plan.",
                        'status' => 422
                    ]);
                }
            }
        }
    }

    public function generateInvoice(InvoiceRequest $request, $id)
    {
        if ($this->user->hasPermissionTo('customer_view')) {
            $customerData = Customer::with('agency')->find($id);
            $invoice = Invoice::create([
                'customer_id' => $id,
                'created_by' => Auth::id(),
                'date' => $request->get('date'),
                'payment_details' => $request->get('payment_details'),
                'other_comments' => $request->get('other_comments'),
                'currency' => $request->get('currency')

            ]);
            $customerData = Customer::with('agency')->find($id);
            $invoice_date = date('jS F Y', strtotime($customerData->created_at));
            $totalAmount = 0;
            foreach ($invoice->payment_details as $item) {
                $totalAmount += (float) $item['amount'];
            }
            $pdf = PDF::loadView('emails.invoice-pdf', [
                'customerData' => $customerData,
                'invoice' => $invoice,
                'totalAmount' => $totalAmount
            ]);
            if (!Storage::exists('invoices')) {
                Storage::makeDirectory('invoices');
            }
            // return $pdf->download('Invoice_' . config('app.name') . '_Order_No # ' . $id . ' Date_' . $invoice_date . '.pdf');
            $pdfFileName = 'Invoice_' . config('app.name') . '_Order_No # ' . $id . ' Date_' . $invoice_date . '.pdf';
            $pdf->save(storage_path('app/invoices/' . $pdfFileName));
            // Send the email with the PDF attached
            $email = $customerData->email;
            if ($email) {
                $sendMail = Mail::send('emails.invoice-template', ['customerData' => $customerData, 'invoice' => $invoice, 'totalAmount' => $totalAmount], function ($message) use ($pdfFileName, $email) {
                    $message->to($email)
                        ->subject('Your Invoice')
                        ->attach(storage_path('app/invoices/' . $pdfFileName));
                });
                if ($sendMail) {
                    return response()->json([
                        'message' => 'Invoice generated and Send in Mail successfully',
                        'status' => 200
                    ], 200);
                } else {
                    return response()->json([
                        'error' => "Sorry!! Couldn't generate Invoice and send in mail.",
                        'status' => 422
                    ], 422);
                }
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ],  403);
        }
    }

    public function downloadInvoice($requestData, $id)
    {
        $customerData = Customer::with('agency')->find($id);

        $invoice_date = date('jS F Y', strtotime($customerData->created_at));

        $pdf = PDF::loadView('emails.invoice-template', ['customerData' => $customerData]);
        return $pdf->download('Invoice_' . config('app.name') . '_Order_No # ' . $id . ' Date_' . $invoice_date . '.pdf');
    }

    public function invoiceListbyCustomer($id, Request $request)
    {
        if ($this->user->hasPermissionTo('customer_view')) {
            $perPage = $request->input('perPage');
            $page = $request->input('page');
            $search = $request->input('search');
            $columnName = $request->input('columnName') ? $request->input('columnName') : 'created_at';
            $type = $request->input('type') ? $request->input('type') : 'desc';



            $invoicesQuery = Invoice::where('customer_id', $id);

            if ($page || $perPage) {
                if (!empty($search)) {
                    $invoicesQuery->where(function ($query) use ($search) {
                        $query->where('invoice_number', 'like', "%$search%")
                            ->orWhere(function ($query) use ($search) {
                                $query->whereRaw("DATE_FORMAT(date, '%b %d, %Y') like '%$search%'");
                            })->whereJsonContains('payment_details', ['amount' => $search])
                            ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(payment_details, '$[*].amount')) LIKE '%$search%'");;
                    });
                }
                if ($columnName === 'payment_details') {
                    $invoices = $invoicesQuery->orderByRaw("CAST(payment_details AS UNSIGNED) $type")->paginate($perPage, ['*'], 'page', $page);
                } else {
                    $invoices = $invoicesQuery->orderBy($columnName, $type)->paginate($perPage, ['*'], 'page', $page);
                }
            } else {
                $invoices = $invoicesQuery->orderBy('created_at', 'desc')->get();
            }

            if ($invoices->isNotEmpty()) {
                return response()->json([
                    'message' => 'Invoice List get successfully',
                    'invoices' => $invoices,
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'message' => "No invoice By this customer.",
                    'invoices' => $invoices,
                    'status' => 200
                ]);
            }
        } else {
            return response()->json([
                'error' => "This role doesn't have permission.",
                'status' => 403
            ],  403);
        }
    }

    public function addTasks(Request $request, $id)
    {
        if ($this->user->hasPermissionTo('customer_view')) {
            $customer = Customer::find($id);

            $tasks = $customer->tasks ?? [];
            $user = Auth::user();
            $fullName = $user->first_name . ' ' . $user->last_name;
            $newtask = [
                'task' => $request->get('tasks'),
                'timestamp' => now()->toDateTimeString(),
                'created_by' => $fullName
            ];

            $tasks[] = $newtask;

            $customer->update([
                'tasks' => $tasks,
            ]);
            if ($tasks) {
                return response()->json([
                    'message' => 'Tasks created successfully.',
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => "Sorry!!! Couldn't create tasks.",
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

    public function addNotes(Request $request, $id)
    {
        if ($this->user->hasPermissionTo('customer_view')) {
            $customer = Customer::find($id);

            $notes = $customer->notes ?? [];
            $user = Auth::user();
            $fullName = $user->first_name . ' ' . $user->last_name;
            $newNote = [
                'note' => $request->get('notes'),
                'timestamp' => now()->toDateTimeString(),
                'created_by' => $fullName
            ];

            $notes[] = $newNote;

            $customer->update([
                'notes' => $notes,
            ]);
            if ($notes) {
                return response()->json([
                    'message' => 'Notes created successfully.',
                    'status' => 200
                ], 200);
            } else {
                return response()->json([
                    'error' => "Sorry!!! Couldn't create notes.",
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
