<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        // Back-office abilities are role-based and configured in one place
        // (config/aroma.php -> admin.abilities); read at check time so a
        // runtime config change (or a test) takes effect immediately. The
        // ability names contain dots, so the map is indexed directly rather
        // than through config("...abilities.{$ability}"), which would treat
        // the dot as a nested path and never find the key.
        foreach (array_keys((array) config('aroma.admin.abilities', [])) as $ability) {
            Gate::define($ability, function (User $user) use ($ability) {
                $roles = (array) (config('aroma.admin.abilities', [])[$ability] ?? []);

                return in_array($user->role, $roles, true);
            });
        }
    }
}
