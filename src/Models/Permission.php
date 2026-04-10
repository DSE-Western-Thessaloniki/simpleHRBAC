<?php

namespace Dsewth\SimpleHRBAC\Models;

use Dsewth\SimpleHRBAC\Factories\PermissionFactory;
use Dsewth\SimpleHRBAC\Observers\PermissionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[ObservedBy(PermissionObserver::class)]
class Permission extends Model
{
    use HasFactory;

    /** @var array */
    protected $fillable = ['name'];

    public $timestamps = false;

    public static function newFactory(): PermissionFactory
    {
        return new PermissionFactory;
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
