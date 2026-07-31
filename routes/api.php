<?php

use App\Http\Controllers\Api\Agency\AgencyDashboardController;
use App\Http\Controllers\Api\Agency\ApplicationChecklistController;
use App\Http\Controllers\Api\Agency\ApplicationController;
use App\Http\Controllers\Api\Agency\CandidateController;
use App\Http\Controllers\Api\Agency\CustomerController;
use App\Http\Controllers\Api\Agency\JobController;
use App\Http\Controllers\Api\Agency\MastersController;
use App\Http\Controllers\Api\Agency\ReportsController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SuperAdmin\AgencyController;
use App\Http\Controllers\Api\SuperAdmin\RecruiterController;
use App\Http\Controllers\Api\Agency\RolesController;
use App\Http\Controllers\Api\Agency\SocialIntegrationController;
use App\Http\Controllers\Api\EmailTemplateController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SuperAdmin\DashboardController;
use App\Http\Controllers\Api\SuperAdmin\SubscriptionController;
use App\Http\Controllers\Api\SuperAdmin\StaffUserController;
use App\Http\Resources\AgencyResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// ==============================|| Auth Routes ||============================== //
Route::post('login', [AuthController::class, 'login'])->name('login');
Route::post('register', [AuthController::class, 'register'])->name('register');
Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgotPassword');
Route::post('reset-password/{id}', [AuthController::class, 'resetPassword'])->name('resetPassword');
Route::post('get-detail-byemail', [AuthController::class, 'getLoginDetailsByEmail'])->name('getLoginDetailsByEmail');
Route::get('/job-details/{id}', [AuthController::class, 'jobDetails'])->name('jobDetails');
Route::post('/save-applications', [AuthController::class, 'addApplication'])->name('addApplication');

Route::middleware(['auth:sanctum', 'checkAgency', 'throttle:api'])->group(function () {
    Route::get('/users', function (Request $request) {
        return new AgencyResource($request->user());
    });
    Route::get('logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('change-password', [AuthController::class, 'changePassword'])->name('changePassword');

    // ==============================|| Dashboard Routes ||============================== //
    Route::post('/profile-update', [ProfileController::class, 'profileUpdate'])->name('profileUpdate');
    Route::get('/get-profile', [ProfileController::class, 'getProfile'])->name('getProfile');
    Route::post('/profile-pic-update', [ProfileController::class, 'profilePicUpdate'])->name('profilePicUpdate');
    Route::post('/user-pic-update/{id}', [ProfileController::class, 'userPicUpdate'])->name('userPicUpdate');
    Route::get('/payment-history', [ProfileController::class, 'paymentHistory'])->name('paymentHistory');

    // ==============================|| Dashboard Routes ||============================== //
    Route::get('/total-agency', [DashboardController::class, 'totalAgency'])->name('totalAgency');
    Route::get('/total-data', [DashboardController::class, 'totalDashboardData'])->name('totalDashboardData');
    Route::post('/subscription-plan', [DashboardController::class, 'subscriptionPlan'])->name('subscriptionPlan');
    Route::post('/twelve-month-graph', [DashboardController::class, 'twelveMonthGraphChart'])->name('twelveMonthGraphChart');
    Route::post('/signup-agency-permonth', [DashboardController::class, 'signUpAgencyPerMonth'])->name('signUpAgencyPerMonth');

    // ==============================|| Dashboard Routes ||============================== //
    Route::post('/total-application-perday', [AgencyDashboardController::class, 'totalAplicationPerDay'])->name('totalAplicationPerDay');
    Route::post('/total-jobs-customers', [AgencyDashboardController::class, 'jobTotalJobsCustomers'])->name('jobTotalJobsCustomers');
    Route::post('/total-applications-status', [AgencyDashboardController::class, 'totalApplicationStatus'])->name('totalApplicationStatus');

    // ==============================|| Agency Routes ||============================== //
    Route::get('/agency-list', [AgencyController::class, 'agencyList'])->name('agencyList');
    Route::put('/update-agency/{id}', [AgencyController::class, 'updateAgency'])->name('updateAgency');
    Route::post('/delete-agency', [AgencyController::class, 'deleteAgency'])->name('deleteAgency');
    Route::get('/view-agency/{id}', [AgencyController::class, 'viewAgency'])->name('viewAgency');
    Route::get('/agency-recruiter-list/{id}', [AgencyController::class, 'agencyRecruiterList'])->name('agencyRecruiterList');
    Route::get('/agency-payment-list/{id}', [AgencyController::class, 'agencyPaymentList'])->name('agencyPaymentList');
    Route::post('/disabled-agency/{id}', [AgencyController::class, 'disabledAgency'])->name('disabledAgency');
    Route::post('/add-payment/{id}', [AgencyController::class, 'addPayment'])->name('addPayment');
    // ==============================|| Recruiter Routes ||============================== //
    Route::post('/add-recruiter', [RecruiterController::class, 'addRecruiter'])->name('addRecruiter');
    Route::put('/update-recruiter/{id}', [RecruiterController::class, 'updateRecruiter'])->name('updateRecruiter');
    Route::post('/delete-recruiter', [RecruiterController::class, 'deleteRecruiter'])->name('deleteRecruiter');
    Route::get('/recruiter-list', [RecruiterController::class, 'recruiterList'])->name('recruiterList');

    // ==============================|| Subscription Routes ||============================== //
    Route::get('/subscription-list', [SubscriptionController::class, 'subscriptionList'])->name('subscriptionList');
    Route::post('/add-subscription', [SubscriptionController::class, 'addSubscription'])->name('addSubscription');
    Route::put('/update-subscription/{id}', [SubscriptionController::class, 'updateSubscription'])->name('updateSubscription');
    Route::post('/delete-subscription', [SubscriptionController::class, 'deleteSubscription'])->name('deleteSubscription');
    Route::post('/get-start-expiry-date', [SubscriptionController::class, 'getStartExpiryDate'])->name('getStartExpiryDate');

    // ==============================|| StaffUsers Routes ||============================== //
    Route::get('/staffuser-list', [StaffUserController::class, 'staffUserList'])->name('staffUserList');
    Route::post('/add-staffuser', [StaffUserController::class, 'addStaffUser'])->name('addStaffUser');
    Route::put('/update-staffuser/{id}', [StaffUserController::class, 'updateStaffUser'])->name('updateStaffUser');
    Route::post('/delete-staffuser', [StaffUserController::class, 'deleteStaffUser'])->name('deleteStaffUser');

    // ==============================|| Profile Routes ||============================== //
    Route::post('/profile-update', [ProfileController::class, 'profileUpdate'])->name('profileUpdate');
    Route::get('/get-profile', [ProfileController::class, 'getProfile'])->name('getProfile');
    Route::post('/profile-pic-update', [ProfileController::class, 'profilePicUpdate'])->name('profilePicUpdate');
    // ==============================|| Roles Routes ||============================== //
    Route::get('/roles-list', [RolesController::class, 'roleList'])->name('roleList');
    Route::get('/superadmin-roles-list/{id}', [RolesController::class, 'roleListSuperAdmin'])->name('roleListSuperAdmin');
    Route::post('/add-edit-roles', [RolesController::class, 'addEditRole'])->name('addEditRole');
    Route::get('/delete-role/{id}', [RolesController::class, 'roleDelete'])->name('roleDelete');

    // ==============================|| Customers Routes ||============================== //
    Route::get('/customer-list', [CustomerController::class, 'customerList'])->name('customerList');
    Route::post('/check-free-plan', [CustomerController::class, 'checkFreePlan'])->name('checkFreePlan');
    Route::post('/add-customer', [CustomerController::class, 'addCustomer'])->name('addCustomer');
    Route::put('/update-customer/{id}', [CustomerController::class, 'updateCustomer'])->name('updateCustomer');
    Route::get('/view-customer/{id}', [CustomerController::class, 'viewCustomer'])->name('viewCustomer');
    Route::post('/generate-invoice/{id}', [CustomerController::class, 'generateInvoice'])->name('generateInvoice');
    Route::post('/delete-customer', [CustomerController::class, 'deleteCustomer'])->name('deleteCustomer');
    Route::get('/download-invoice/{id}', [CustomerController::class, 'downloadInvoice'])->name('downloadInvoice');
    Route::get('/invoice-list/{id}', [CustomerController::class, 'invoiceListbyCustomer']);
    Route::post('/add-notes-customer/{id}', [CustomerController::class, 'addNotes'])->name('addNotesCustomer');
    Route::post('/add-tasks-customer/{id}', [CustomerController::class, 'addTasks'])->name('addTasksCustomer');

    // ==============================|| Masters Routes ||============================== //
    Route::get('/country-list/{type?}', [MastersController::class, 'countryList'])->name('countryList');
    Route::post('/add-country', [MastersController::class, 'addCountry'])->name('addCountry');
    Route::put('/update-country/{id}', [MastersController::class, 'updateCountry'])->name('updateCountry');
    Route::post('/delete-country', [MastersController::class, 'deleteCountry'])->name('deleteCountry');

    Route::get('/category-list/{type?}', [MastersController::class, 'categoryList'])->name('categoryList');
    Route::post('/add-category', [MastersController::class, 'addCategory'])->name('addCategory');
    Route::put('/update-category/{id}', [MastersController::class, 'updateCategory'])->name('updateCategory');
    Route::post('/delete-category', [MastersController::class, 'deleteCategory'])->name('deleteCategory');

    Route::get('/subcategory-list/{type?}', [MastersController::class, 'subCategoryList'])->name('subCategoryList');
    Route::post('/add-subcategory', [MastersController::class, 'addSubCategory'])->name('addSubCategory');
    Route::put('/update-subcategory/{id}', [MastersController::class, 'updateSubCategory'])->name('updateSubCategory');
    Route::post('/delete-subcategory', [MastersController::class, 'deleteSubCategory'])->name('deleteSubCategory');

    Route::get('/pool-list/{type?}', [MastersController::class, 'poolList'])->name('poolList');
    Route::post('/add-pool', [MastersController::class, 'addPool'])->name('addPool');
    Route::put('/update-pool/{id}', [MastersController::class, 'updatePool'])->name('updatePool');
    Route::post('/delete-pool', [MastersController::class, 'deletePool'])->name('deletePool');

    // ==============================|| Job Routes ||============================== //
    Route::get('/job-list/{customer_id?}', [JobController::class, 'jobList'])->name('jobList');
    Route::post('/add-job', [JobController::class, 'addJob'])->name('addJob');
    Route::post('/update-job/{id}', [JobController::class, 'updateJob'])->name('updateJob');
    Route::post('/delete-job', [JobController::class, 'deleteJob'])->name('deleteJob');
    Route::post('/share-job-in-social-media/{id}', [JobController::class, 'shareJobInSocialMedia'])->name('shareJobInSocialMedia');
    Route::post('/uploadcv/{filename}', [JobController::class, 'upload'])->name('upload');
    Route::get('/close-job/{id}', [JobController::class, 'closeJob'])->name('closeJob');
    Route::get('/reopen-job/{id}', [JobController::class, 'reOpenJob'])->name('reopenJob');
    Route::get('/view-job/{id}', [JobController::class, 'viewJob'])->name('viewJob');
    // ==============================|| Applications Routes ||============================== //
    Route::get('/applications-list', [ApplicationController::class, 'applicationList'])->name('applicationList');
    Route::post('/add-applications', [ApplicationController::class, 'addApplication'])->name('addApplication');
    Route::post('/update-applications/{id}', [ApplicationController::class, 'updateApplication'])->name('updateApplication');
    Route::post('/delete-applications', [ApplicationController::class, 'deleteApplication'])->name('deleteApplication');
    Route::get('/view-applications/{id}', [ApplicationController::class, 'viewApplication'])->name('viewApplication');
    Route::post('/application-status-change/{id}', [ApplicationController::class, 'applicationStatusChange'])->name('applicationStatusChange');
    Route::post('/reschedule-applications/{id}', [ApplicationController::class, 'rescheduleApplication'])->name('rescheduleApplication');
    Route::post('/share-experience/{id}', [ApplicationController::class, 'shareExperience'])->name('shareExperience');
    Route::post('/add-notes/{id}', [ApplicationController::class, 'addNotes'])->name('addNotes');
    Route::post('/add-tasks/{id}', [ApplicationController::class, 'addTasks'])->name('addTasks');
    Route::post('/apply-on-another-job/{id?}', [ApplicationController::class, 'ApplyOnAnotherJob'])->name('ApplyOnAnotherJob');
    Route::post('/application-multiple-actions/{action?}', [ApplicationController::class, 'applicationMultipleActions'])->name('applicationMultipleActions');
    Route::get('/source-list', [ApplicationController::class, 'sourceList'])->name('sourceList');

    Route::get('/candidate-list', [CandidateController::class, 'candidateList'])->name('candidateList');
    Route::post('/add-candidate', [CandidateController::class, 'addCandidate'])->name('addCandidate');
    Route::post('/update-candidate/{id}', [CandidateController::class, 'updateCandidate'])->name('updateCandidate');
    Route::post('/delete-candidate', [CandidateController::class, 'deleteCandidate'])->name('deleteCandidate');
    Route::post('/candidate-app-details/{id}', [CandidateController::class, 'candidateApplicationdetails'])->name('candidateApplicationdetails');
    Route::post('/candidate-assign-vacancies', [CandidateController::class, 'candidateMultipleActions'])->name('candidateMultipleActions');

    Route::get('/document-list', [ApplicationController::class, 'documentList'])->name('documentList');
    Route::post('/add-document/{id}', [ApplicationController::class, 'addDocument'])->name('addDocument');
    Route::post('/update-document/{id}', [ApplicationController::class, 'updateDocument'])->name('updateDocument');
    Route::post('/delete-document/{id}', [ApplicationController::class, 'deleteDocument'])->name('deleteDocument');

    Route::post('/saveCheckList/{id}', [ApplicationChecklistController::class, 'saveCheckList'])->name('saveCheckList');
    // ==============================|| Candidate Pool ||============================== //
    Route::get('/candidate-pool-list', [ApplicationController::class, 'candidatePoolList'])->name('candidatePoolList');

    Route::get('/reports-list/{reportType}', [ReportsController::class, 'reportsList'])->name('reportsList');
    Route::get('/download-report/{reportType}', [ReportsController::class, 'downloadReport'])->name('downloadReport');

    // ==============================|| Email Template ||============================== //
    Route::post('/email-template-generate', [EmailTemplateController::class, 'emailTemplateGenerate'])->name('emailTemplateGenerate');
    Route::post('/add-template', [EmailTemplateController::class, 'addTemplate'])->name('addTemplate');
    Route::get('/template-list', [EmailTemplateController::class, 'listTemplate'])->name('listTemplate');
    Route::post('/add-edit-email-template', [EmailTemplateController::class, 'addEditEmailTemplate'])->name('addEditEmailTemplate');
    Route::get('/get-email-template/{id}', [EmailTemplateController::class, 'getEmailTemplate'])->name('getEmailTemplate');

    Route::post('/save-token/{type}', [SocialIntegrationController::class, 'saveToken'])->name('saveToken');
    Route::get('/get-token/{type}', [SocialIntegrationController::class, 'getToken'])->name('getToken');

    // =================================|| Notifications ||============================= //
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::put('/notifications/{id}/mark-as-read', [NotificationController::class, 'markAsRead']);
    Route::put('/notifications/{id}/mark-as-unread', [NotificationController::class, 'markAsUnread']);
    Route::put('/notifications/mark-all-as-read', [NotificationController::class, 'markAllAsRead']);
});
