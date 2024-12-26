<?php

use Dsewth\SimpleHRBAC\Providers\SimpleHRBACServiceProvider;

it('should publish config and migrations', function () {
    $provider = new SimpleHRBACServiceProvider($this->app);
    $provider->boot();
    $config = $provider->pathsToPublish(group: 'config');
    expect(array_key_first($config))->toEndWith('config/simple-hrbac.php');
    expect($config[array_key_first($config)])->toEndWith('config/simple-hrbac.php');
})->only();
