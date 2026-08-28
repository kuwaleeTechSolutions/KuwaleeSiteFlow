<?php

namespace App\Providers;

use App\Models\Bill;
use App\Models\BoqItem;
use App\Models\ComplianceItem;
use App\Models\DailyReport;
use App\Models\DailyReportPhoto;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\EquipmentUsageLog;
use App\Models\FuelTransaction;
use App\Models\Material;
use App\Models\MaterialTransaction;
use App\Models\Measurement;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\WageComputation;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use App\Policies\BillPolicy;
use App\Policies\BoqItemPolicy;
use App\Policies\ComplianceItemPolicy;
use App\Policies\DailyReportPhotoPolicy;
use App\Policies\DailyReportPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\EquipmentPolicy;
use App\Policies\EquipmentUsageLogPolicy;
use App\Policies\FuelTransactionPolicy;
use App\Policies\MaterialPolicy;
use App\Policies\MaterialTransactionPolicy;
use App\Policies\MeasurementPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\RolePolicy;
use App\Policies\SitePolicy;
use App\Policies\UserPolicy;
use App\Policies\WageComputationPolicy;
use App\Policies\WorkerAttendancePolicy;
use App\Policies\WorkerPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Explicit policy registrations. We intentionally do NOT rely on
     * Laravel's naming-convention auto-discovery alone, to keep the
     * authorization map explicit and reviewable in one place.
     */
    protected array $policies = [
        User::class => UserPolicy::class,
        Role::class => RolePolicy::class,
        Organization::class => OrganizationPolicy::class,
        Project::class => ProjectPolicy::class,
        Site::class => SitePolicy::class,
        DailyReport::class => DailyReportPolicy::class,
        DailyReportPhoto::class => DailyReportPhotoPolicy::class,
        Worker::class => WorkerPolicy::class,
        WorkerAttendance::class => WorkerAttendancePolicy::class,
        WageComputation::class => WageComputationPolicy::class,
        Material::class => MaterialPolicy::class,
        MaterialTransaction::class => MaterialTransactionPolicy::class,
        Equipment::class => EquipmentPolicy::class,
        EquipmentUsageLog::class => EquipmentUsageLogPolicy::class,
        FuelTransaction::class => FuelTransactionPolicy::class,
        BoqItem::class => BoqItemPolicy::class,
        Measurement::class => MeasurementPolicy::class,
        Bill::class => BillPolicy::class,
        Payment::class => PaymentPolicy::class,
        Document::class => DocumentPolicy::class,
        ComplianceItem::class => ComplianceItemPolicy::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // Super Admin bypass — ONLY for the /api/system/* surface. Ordinary
        // tenant policies still separately verify organization_id even for
        // a super admin who is impersonating/inspecting, since Gate::before
        // is intentionally NOT used here to avoid blanket bypass of
        // tenant-isolation checks. Super admin elevated access is instead
        // handled explicitly inside each Policy method where applicable.

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        
    // REQUIRED: bootstrap/app.php calls $middleware->throttleApi(), which
    // applies `throttle:api` to every API route by default. Without this
    // registration, Laravel throws "Rate limiter [api] is not defined."
    RateLimiter::for('api', function (Request $request) {
        return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
    });

   
        
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = strtolower((string) $request->input('email')).'|'.$request->ip();

            return Limit::perMinute((int) env('LOGIN_THROTTLE_ATTEMPTS', 5))
                ->by($throttleKey)
                ->response(function () {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many login attempts. Please try again in a minute.',
                    ], 429);
                });
        });

        RateLimiter::for('document-download', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        foreach ([
            'api-read' => (int) config('security.rate_limits.read_per_minute', 120),
            'api-mutation' => (int) config('security.rate_limits.mutation_per_minute', 60),
            'api-upload' => (int) config('security.rate_limits.upload_per_minute', 20),
            'api-export' => (int) config('security.rate_limits.export_per_minute', 20),
        ] as $name => $limit) {
            RateLimiter::for($name, fn (Request $request) =>
                Limit::perMinute($limit)->by($request->user()?->id ?: $request->ip())
            );
        }
    }
}
