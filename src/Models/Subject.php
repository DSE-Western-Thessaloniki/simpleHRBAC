<?php

namespace Dsewth\SimpleHRBAC\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Subject extends Model
{
    use HasFactory;

    /** @var array */
    protected $fillable = ['name'];

    public $timestamps = false;

    public static function newFactory(): PermissionFactory
    {
        return new SubjectFactory;
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
