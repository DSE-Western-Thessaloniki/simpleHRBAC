<?php

namespace Workbench\App\Models;

use Dsewth\SimpleHRBAC\Traits\HasRoles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as AuthUser;
use Workbench\Database\Factories\UserFactory;

class User extends AuthUser
{
    use HasFactory, HasRoles;

    protected $fillable = ['id', 'name', 'email', 'password'];

    public static function newFactory(): UserFactory
    {
        return new UserFactory;
    }
}
