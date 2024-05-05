<?php

namespace Dsewth\SimpleHRBAC\Providers;

use Dsewth\SimpleHRBAC\RBAC;
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
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/simple-hrbac.php',
            'simple-hrbac'
        );

        $this->app->bind(RBAC::class, function () {
            return new RBAC(config('simple-hrbac'));
        });
    }

    public function offerPublishing()
    {
        $this->publishes([
            __DIR__.'/../../config/simple-hrbac.php' => config_path('simple-hrbac.php'),
        ], 'config');
        $this->publishes([
            __DIR__.'/../../database/migrations/01-permissions.php.stub' => $this->getMigrationFilename('create_permissions_table.php'),
            __DIR__.'/../../database/migrations/02-subjects.php.stub' => $this->getMigrationFilename('create_subjects_table.php'),
            __DIR__.'/../../database/migrations/03-roles.php.stub' => $this->getMigrationFilename('create_roles_table.php'),
            __DIR__.'/../../database/migrations/04-role_tree.php.stub' => $this->getMigrationFilename('create_role_tree_table.php'),
            __DIR__.'/../../database/migrations/05-permission_role.php.stub' => $this->getMigrationFilename('create_permission_role_table.php'),
            __DIR__.'/../../database/migrations/06-role_subject.php.stub' => $this->getMigrationFilename('create_role_subject_table.php'),
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
