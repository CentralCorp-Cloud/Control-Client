<?php

namespace App\Providers;

use App\Contracts\CnameResolver;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Deployment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\NativeCnameResolver;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CnameResolver::class, NativeCnameResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Cashier::useSubscriptionModel(Subscription::class);
        Event::listen(Login::class, function (Login $event): void {
            if (! $event->user instanceof User) {
                return;
            }
            $event->user->forceFill(['last_login_at' => now()])->save();
            if ($event->user->isAdministrator()) {
                AuditLog::create(['actor_id' => $event->user->id, 'action' => 'admin.login', 'target_type' => $event->user->getMorphClass(), 'target_id' => $event->user->getKey(), 'metadata' => [], 'ip_address' => request()->ip(), 'user_agent' => mb_substr((string) request()->userAgent(), 0, 1000)]);
            }
        });
        Gate::define('access-admin', fn ($user) => $user->role->isAdministrator());
        Gate::define('manage-infrastructure', fn ($user) => in_array($user->role, [UserRole::InfraAdmin, UserRole::Admin, UserRole::SuperAdmin], true));
        Gate::define('manage-billing', fn ($user) => in_array($user->role, [UserRole::BillingAdmin, UserRole::Admin, UserRole::SuperAdmin], true));
        Gate::define('manage-users', fn ($user) => in_array($user->role, [UserRole::Support, UserRole::Admin, UserRole::SuperAdmin], true));
        Gate::define('purge-deployments', fn ($user) => $user->role === UserRole::SuperAdmin);

        RateLimiter::for('deployment-purge', function (Request $request) {
            $deployment = $request->route('deployment');
            $deploymentKey = $deployment instanceof Deployment ? $deployment->uuid : (string) $deployment;
            $actorKey = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinutes(10, 5)
                ->by($actorKey.'|'.$deploymentKey)
                ->response(function (Request $request, array $headers) {
                    return response()->view('errors.429', [
                        'retryAfter' => (int) ($headers['Retry-After'] ?? 60),
                    ], 429, $headers);
                });
        });
    }
}
