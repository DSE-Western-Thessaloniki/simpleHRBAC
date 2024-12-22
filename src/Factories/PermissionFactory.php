<?php

namespace Dsewth\SimpleHRBAC\Factories;

use Dsewth\SimpleHRBAC\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    public function definition()
    {
        return [
            'name' => $this->faker->unique->word,
        ];
    }
}
