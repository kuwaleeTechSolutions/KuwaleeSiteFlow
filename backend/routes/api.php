<?php

use App\Http\Controllers\Api\BillPdfController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MeasurementPdfController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\BillController;
use App\Http\Controllers\Api\BoqItemController;
use App\Http\Controllers\Api\ComplianceItemController;
use App\Http\Controllers\Api\DailyReportController;
use App\Http\Controllers\Api\DailyReportPhotoController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\EquipmentController;
use App\Http\Controllers\Api\EquipmentUsageLogController;
use App\Http\Controllers\Api\FuelAlertController;
use App\Http\Controllers\Api\FuelTransactionController;
use App\Http\Controllers\Api\MaterialAlertController;
use App\Http\Controllers\Api\MaterialController;
use App\Http\Controllers\Api\MaterialStockController;
use App\Http\Controllers\Api\MaterialTransactionController;
use App\Http\Controllers\Api\MeasurementController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SiteController;
use App\Http\Controllers\Api\System\OrganizationController as SystemOrganizationController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WageComputationController;
use App\Http\Controllers\Api\WorkerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public / unauthenticated routes
|--------------------------------------------------------------------------
*/
// Rate limiting for login is handled explicitly inside AuthController::login()
// using the 'login' RateLimiter (keyed by email+IP) so that a throttled
// attempt returns a consistent 422 validation-style error rather than a
// generic 429, matching the rest of the authentication error contract.
Route::post('/login', [AuthController::class, 'login'])->name('login');

/*
|--------------------------------------------------------------------------
| Authenticated routes (any logged-in user — tenant or super admin)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    /*
    |----------------------------------------------------------------------
    | Tenant-scoped routes — every route here additionally requires an
    | active organization membership (org.context) and, per-action, the
    | relevant granular permission (permission:<name>). Controllers further
    | authorize the specific resource instance via Policies.
    |----------------------------------------------------------------------
    */
    Route::middleware('org.context')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.view');
        Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.create');
        Route::get('/users/{user}', [UserController::class, 'show']); // policy allows self-view without permission
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::post('/users/{user}/roles', [UserController::class, 'assignRoles'])->middleware('permission:users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete');

        Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:roles.view');
        Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:roles.create');
        Route::get('/roles/{role}', [RoleController::class, 'show'])->middleware('permission:roles.view');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete');

        Route::get('/projects', [ProjectController::class, 'index'])->middleware('permission:projects.view');
        Route::post('/projects', [ProjectController::class, 'store'])->middleware('permission:projects.create');
        Route::get('/projects/{project}', [ProjectController::class, 'show'])->middleware('permission:projects.view');
        Route::put('/projects/{project}', [ProjectController::class, 'update'])->middleware('permission:projects.update');
        Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->middleware('permission:projects.delete');
        Route::get('/projects/{project}/users', [ProjectController::class, 'assignedUsers'])->middleware('permission:projects.view');
        Route::post('/projects/{project}/users', [ProjectController::class, 'assignUsers'])->middleware('permission:projects.update');

        Route::get('/projects/{project}/sites', [SiteController::class, 'index'])->middleware('permission:sites.view');
        Route::post('/projects/{project}/sites', [SiteController::class, 'store'])->middleware('permission:sites.create');
        Route::get('/sites/{site}', [SiteController::class, 'show'])->middleware('permission:sites.view');
        Route::put('/sites/{site}', [SiteController::class, 'update'])->middleware('permission:sites.update');
        Route::delete('/sites/{site}', [SiteController::class, 'destroy'])->middleware('permission:sites.delete');
        Route::post('/sites/{site}/users', [SiteController::class, 'assignUsers'])->middleware('permission:sites.update');

        // NOTE: route parameter names below are camelCase ({dailyReport},
        // {dailyReportPhoto}) purely to match the controller method
        // argument names for Laravel's implicit route-model-binding — the
        // URI path text itself (e.g. "daily-reports") is unaffected.
        Route::get('/daily-reports', [DailyReportController::class, 'index'])->middleware('permission:daily_reports.view');
        Route::post('/daily-reports', [DailyReportController::class, 'store'])->middleware('permission:daily_reports.create');
        Route::get('/daily-reports/{dailyReport}', [DailyReportController::class, 'show'])->middleware('permission:daily_reports.view');
        Route::put('/daily-reports/{dailyReport}', [DailyReportController::class, 'update'])->middleware('permission:daily_reports.update');
        Route::delete('/daily-reports/{dailyReport}', [DailyReportController::class, 'destroy'])->middleware('permission:daily_reports.delete');
        Route::post('/daily-reports/{dailyReport}/submit', [DailyReportController::class, 'submit'])->middleware('permission:daily_reports.update');
        Route::post('/daily-reports/{dailyReport}/approve', [DailyReportController::class, 'approve'])->middleware('permission:daily_reports.approve');
        Route::post('/daily-reports/{dailyReport}/return', [DailyReportController::class, 'returnForCorrection'])->middleware('permission:daily_reports.approve');

        Route::post('/daily-reports/{dailyReport}/photos', [DailyReportPhotoController::class, 'store'])->middleware(['permission:documents.upload', 'throttle:api-upload']);
        Route::get('/daily-report-photos/{dailyReportPhoto}/download', [DailyReportPhotoController::class, 'download'])
            ->middleware('permission:daily_reports.view')
            ->name('daily-report-photos.download');
        Route::delete('/daily-report-photos/{dailyReportPhoto}', [DailyReportPhotoController::class, 'destroy'])->middleware('permission:documents.delete');

        Route::get('/workers', [WorkerController::class, 'index'])->middleware('permission:labour.view');
        Route::post('/workers', [WorkerController::class, 'store'])->middleware('permission:labour.create');
        Route::get('/workers/{worker}', [WorkerController::class, 'show'])->middleware('permission:labour.view');
        Route::put('/workers/{worker}', [WorkerController::class, 'update'])->middleware('permission:labour.update');

        Route::get('/attendance', [AttendanceController::class, 'index'])->middleware('permission:labour.view');
        Route::post('/attendance', [AttendanceController::class, 'store'])->middleware('permission:labour.attendance');
        Route::post('/attendance/bulk', [AttendanceController::class, 'storeBulk'])->middleware('permission:labour.attendance');

        Route::get('/projects/{project}/wage-computations', [WageComputationController::class, 'index'])->middleware('permission:labour.wages');
        Route::get('/projects/{project}/wage-computations/summary', [WageComputationController::class, 'summary'])->middleware('permission:labour.wages');
        Route::post('/projects/{project}/wage-computations/generate', [WageComputationController::class, 'generate'])->middleware('permission:labour.wages');

        Route::get('/materials', [MaterialController::class, 'index'])->middleware('permission:materials.view');
        Route::post('/materials', [MaterialController::class, 'store'])->middleware('permission:materials.create');
        Route::get('/materials/{material}', [MaterialController::class, 'show'])->middleware('permission:materials.view');
        Route::put('/materials/{material}', [MaterialController::class, 'update'])->middleware('permission:materials.update');

        // NOTE: {materialTransaction} is camelCase to match the controller's
        // $materialTransaction argument (see Phase 4 route-binding note).
        // The `store` route intentionally has NO route-level `permission:`
        // middleware: the required permission depends on `transaction_type`
        // in the request body (materials.create for inward/return/
        // adjustment, materials.issue, or materials.transfer) — that
        // type-aware check happens in StoreMaterialTransactionRequest via
        // MaterialTransactionPolicy::createForSite(), which is the actual
        // authority here, not a coarse route-level gate.
        Route::get('/material-transactions', [MaterialTransactionController::class, 'index'])->middleware('permission:materials.view');
        Route::post('/material-transactions', [MaterialTransactionController::class, 'store']);
        Route::get('/material-transactions/{materialTransaction}', [MaterialTransactionController::class, 'show'])->middleware('permission:materials.view');

        Route::get('/projects/{project}/material-stocks', [MaterialStockController::class, 'index'])->middleware('permission:materials.view');
        Route::get('/projects/{project}/material-alerts/high-consumption', [MaterialAlertController::class, 'highConsumption'])->middleware('permission:materials.view');

        Route::get('/equipment', [EquipmentController::class, 'index'])->middleware('permission:equipment.view');
        Route::post('/equipment', [EquipmentController::class, 'store'])->middleware('permission:equipment.create');
        Route::get('/equipment/{equipment}', [EquipmentController::class, 'show'])->middleware('permission:equipment.view');
        Route::put('/equipment/{equipment}', [EquipmentController::class, 'update'])->middleware('permission:equipment.update');
        Route::delete('/equipment/{equipment}', [EquipmentController::class, 'destroy'])->middleware('permission:equipment.delete');

        Route::get('/equipment-usage-logs', [EquipmentUsageLogController::class, 'index'])->middleware('permission:equipment.view');
        Route::post('/equipment-usage-logs', [EquipmentUsageLogController::class, 'store'])->middleware('permission:equipment.log_usage');

        // fuel-transactions 'store'/'update' have NO route-level permission
        // middleware — see StoreFuelTransactionRequest/UpdateFuelTransactionRequest,
        // which defer to FuelTransactionPolicy for the actual authorization
        // (site access + fuel.create/update, with immutability once reviewed).
        Route::get('/fuel-transactions', [FuelTransactionController::class, 'index'])->middleware('permission:fuel.view');
        Route::post('/fuel-transactions', [FuelTransactionController::class, 'store']);
        Route::get('/fuel-transactions/{fuelTransaction}', [FuelTransactionController::class, 'show'])->middleware('permission:fuel.view');
        Route::put('/fuel-transactions/{fuelTransaction}', [FuelTransactionController::class, 'update']);
        Route::post('/fuel-transactions/{fuelTransaction}/review', [FuelTransactionController::class, 'review'])->middleware('permission:fuel.approve');

        Route::get('/projects/{project}/fuel-alerts', [FuelAlertController::class, 'index'])->middleware('permission:fuel.view');

        Route::get('/projects/{project}/boq-items', [BoqItemController::class, 'index'])->middleware('permission:billing.view');
        Route::post('/projects/{project}/boq-items/revisions', [BoqItemController::class, 'createRevision'])->middleware('permission:billing.create');

        Route::get('/measurements', [MeasurementController::class, 'index'])->middleware('permission:measurements.view');
        Route::post('/measurements', [MeasurementController::class, 'store'])->middleware('permission:measurements.create');
        Route::get('/measurements/{measurement}', [MeasurementController::class, 'show'])->middleware('permission:measurements.view');
        Route::post('/measurements/{measurement}/submit', [MeasurementController::class, 'submit'])->middleware('permission:measurements.update');
        Route::post('/measurements/{measurement}/approve', [MeasurementController::class, 'approve'])->middleware('permission:measurements.approve');
        Route::post('/measurements/{measurement}/reject', [MeasurementController::class, 'reject'])->middleware('permission:measurements.approve');

        Route::get('/projects/{project}/bills', [BillController::class, 'index'])->middleware('permission:billing.view');
        Route::post('/projects/{project}/bills', [BillController::class, 'store'])->middleware('permission:billing.create');
        Route::get('/bills/{bill}', [BillController::class, 'show'])->middleware('permission:billing.view');
        Route::post('/bills/{bill}/submit', [BillController::class, 'submit'])->middleware('permission:billing.update');
        Route::post('/bills/{bill}/certify', [BillController::class, 'certify'])->middleware('permission:billing.approve');

        Route::get('/bills/{bill}/payments', [PaymentController::class, 'index'])->middleware('permission:payments.view');
        Route::post('/bills/{bill}/payments', [PaymentController::class, 'store'])->middleware('permission:payments.create');

        Route::get('/documents', [DocumentController::class, 'index'])->middleware('permission:documents.view');
        Route::post('/documents', [DocumentController::class, 'store'])->middleware(['permission:documents.upload', 'throttle:api-upload']);
        Route::get('/documents/{document}', [DocumentController::class, 'show'])->middleware('permission:documents.view');
        Route::get('/documents/{document}/download', [DocumentController::class, 'download'])
            ->middleware(['permission:documents.download', 'throttle:api-export'])
            ->name('documents.download');
        Route::post('/documents/{document}/share', [DocumentController::class, 'share'])->middleware('permission:documents.share');
        Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->middleware('permission:documents.delete');

        Route::get('/compliance-items', [ComplianceItemController::class, 'index'])->middleware('permission:compliance.view');
        Route::post('/compliance-items', [ComplianceItemController::class, 'store'])->middleware('permission:compliance.create');
        Route::get('/compliance-items/{complianceItem}', [ComplianceItemController::class, 'show'])->middleware('permission:compliance.view');
        Route::put('/compliance-items/{complianceItem}', [ComplianceItemController::class, 'update'])->middleware('permission:compliance.update');
        Route::delete('/compliance-items/{complianceItem}', [ComplianceItemController::class, 'destroy'])->middleware('permission:compliance.delete');

        // Dashboards and exports are tenant resources. They must remain in
        // the organization-context group so normal organization users can
        // access only their assigned data; super-admin accounts never enter
        // this group because they do not have an organization context.
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->middleware('throttle:api-read');
        Route::get('/projects/{project}/dashboard', [DashboardController::class, 'project'])
            ->middleware(['permission:projects.view', 'throttle:api-read']);
        Route::get('/bills/{bill}/pdf', BillPdfController::class)
            ->middleware(['permission:billing.view', 'throttle:api-export']);
        Route::get('/measurements/{measurement}/pdf', MeasurementPdfController::class)
            ->middleware(['permission:measurements.view', 'throttle:api-export']);
    });

    /*
    |----------------------------------------------------------------------
    | Super Admin only — physically separate prefix + middleware, never
    | reachable by a tenant-scoped session regardless of permissions held.
    |----------------------------------------------------------------------
    */
    Route::prefix('system')->middleware('super_admin')->group(function () {
        Route::get('/organizations', [SystemOrganizationController::class, 'index']);
        Route::post('/organizations', [SystemOrganizationController::class, 'store']);
        Route::get('/organizations/{organization}', [SystemOrganizationController::class, 'show']);
        Route::put('/organizations/{organization}', [SystemOrganizationController::class, 'update']);

    });
});
