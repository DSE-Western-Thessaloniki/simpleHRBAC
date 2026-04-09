<?php

namespace Dsewth\SimpleHRBAC\Providers;

use App\Models\User;
use Dsewth\SimpleHRBAC\Helpers\DataHelper;
use Dsewth\SimpleHRBAC\Services\RBACService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;

class SimpleHRBACServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->offerPublishing();
        }

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->setUserModel();
    }

    public function setUserModel(): void
    {
        if (class_exists(config('simple-hrbac.user_model', User::class))) {
            DataHelper::useUserModel(config('simple-hrbac.user_model', User::class));
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/simple-hrbac.php',
            'simple-hrbac'
        );

        $this->app->bind('RBAC', function () {
            return new RBACService;
        });
    }

    public function offerPublishing()
    {
        $this->publishes([
            __DIR__.'/../../config/simple-hrbac.php' => config_path('simple-hrbac.php'),
        ], 'config');
        $this->publishes([
            __DIR__.'/../../database/migrations/01_permissions.php' => $this->getMigrationFilename('create_permissions_table.php'),
            __DIR__.'/../../database/migrations/02_roles.php' => $this->getMigrationFilename('create_roles_table.php'),
            __DIR__.'/../../database/migrations/03_role_tree.php' => $this->getMigrationFilename('create_role_tree_table.php'),
            __DIR__.'/../../database/migrations/04_permission_role.php' => $this->getMigrationFilename('create_permission_role_table.php'),
            __DIR__.'/../../database/migrations/05_role_user.php' => $this->getMigrationFilename('create_role_user_table.php'),
        ], 'migrations');
    }

    protected function getMigrationFilename(string $migrationFileName): string
    {
        $timestamp = date('Y_m_d_His');

        $filesystem = $this->app->make(Filesystem::class);

        return Collection::make($this->app->databasePath().'/migrations/')
            ->flatMap(function ($path) use ($filesystem, $migrationFileName) {
                return $filesystem->glob($path.'*_'.$migrationFileName);
            })
            ->push($this->app->databasePath()."/migrations/{$timestamp}_{$migrationFileName}")
            ->first();
    }
}
