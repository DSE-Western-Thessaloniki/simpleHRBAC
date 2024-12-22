<?php

namespace Dsewth\SimpleHRBAC\Factories;

use Dsewth\SimpleHRBAC\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition()
    {
        return [
            'name' => $this->faker->unique->word,
            'description' => $this->faker->sentence,
        ];
    }
}
