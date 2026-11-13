<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Menjalankan/meninjau pemeriksaan (admin & pemeriksa); role 'user' hanya melihat.
        // Dipakai di route (middleware can:periksa) dan view (@can('periksa')).
        Gate::define('periksa', fn (User $user) => $user->canPeriksa());
        // memeriksa satu publikasi BPS (pub_id): ditugaskan ke dia, tidak ditugaskan, atau admin
        Gate::define('periksa-pub', fn (User $user, ?string $pubId = null) => \App\Models\PenugasanPemeriksaan::boleh($user, $pubId));
    }
}
