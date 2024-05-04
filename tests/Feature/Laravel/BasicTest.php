<?php

use Dsewth\SimpleHRBAC\RBAC;
use Tests\TestCase;

it('should be automatically loaded by laravel', function () {
    /** @var TestCase $this */
    $this->assertTrue(app()->getLoadedProviders()["Dsewth\SimpleHRBAC\Providers\SimpleHRBACServiceProvider"]);
    expect(app(RBAC::class))->toBeInstanceOf(RBAC::class);
});

it('should be able to read the default configuration file', function () {
    $config = config('simple-hrbac');
    /** @var TestCase $this */
    $this->assertNotNull($config);
    $this->assertArrayHasKey('database', $config);
});
