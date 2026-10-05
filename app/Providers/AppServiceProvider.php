<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        // Authorisation gates based on roles (see docs/PLAN.md section 7).
        Gate::define('edit-all-sections', fn (User $user) => $user->canEditAllSections());
        Gate::define('approve-reports', fn (User $user) => $user->canApprove());
        Gate::define('manage-settings', fn (User $user) => $user->isAdmin());
    }
}
