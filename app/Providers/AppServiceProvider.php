<?php

namespace App\Providers;

use App\Models\AdverseEventReport;
use App\Models\Barangay;
use App\Models\ChildProfile;
use App\Models\ClinicAnnouncement;
use App\Models\PopulationBackground;
use App\Models\User;
use App\Models\VaccinationRecord;
use App\Models\VaccineInventoryItem;
use App\Models\VaccineInventoryTransaction;
use App\Models\VaccineSchedule;
use App\Models\VaccineScheduleVersion;
use App\Models\VaccineType;
use App\Observers\AuditObserver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Passport::tokensCan([
            'sync:pull' => 'Pull central master data',
            'sync:push' => 'Push facility data',
            'sync:status' => 'Read synchronization status',
        ]);

        foreach ([
            User::class,
            Barangay::class,
            ChildProfile::class,
            VaccinationRecord::class,
            AdverseEventReport::class,
            ClinicAnnouncement::class,
            VaccineInventoryItem::class,
            VaccineInventoryTransaction::class,
            VaccineSchedule::class,
            VaccineScheduleVersion::class,
            VaccineType::class,
            PopulationBackground::class,
        ] as $model) {
            $model::observe(AuditObserver::class);
        }

        if (! $this->app->runningInConsole()) {
            // Use the address the browser used to reach this local server. This
            // keeps generated links reachable from phones on the same network.
            URL::forceRootUrl(request()->getSchemeAndHttpHost());
        }

        if (request()->header('x-forwarded-proto') === 'https') {
            URL::forceScheme('https');
        }

        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
