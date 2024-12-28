<?php

use Dsewth\SimpleHRBAC\Providers\SimpleHRBACServiceProvider;

it('should publish config and migrations', function () {
    $provider = new SimpleHRBACServiceProvider($this->app);
    $config = $provider->pathsToPublish(group: 'config');
    expect(array_key_first($config))->toBe(getcwd().'/src/Providers/../../config/simple-hrbac.php');
    expect($config[array_key_first($config)])->toBe(config_path('simple-hrbac.php'));

    $migrations = $provider->pathsToPublish(group: 'migrations');
    $dir = dir(getcwd().'/database/migrations');
    while (false !== ($entry = $dir->read())) {
        if (str_ends_with($entry, '.stub') !== false) {
            $stubs[] = $entry;
        }
    }
    foreach (array_keys($migrations) as $key) {
        expect($key)->toStartWith(getcwd().'/src/Providers/../../database/migrations');
        expect(in_array(basename($key), $stubs))->toBeTrue();
        unset($stubs[array_search(basename($key), $stubs)]);
    }

    expect(count($stubs))->toBe(0);
});
