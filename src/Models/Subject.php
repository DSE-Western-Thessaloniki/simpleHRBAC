<?php

namespace Dsewth\SimpleHRBAC\Models;

use Dsewth\SimpleHRBAC\Facades\RBAC;
use Dsewth\SimpleHRBAC\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Subject extends Model
{
    use HasFactory;

    /** @var array */
    protected $fillable = ['name'];

    public $timestamps = false;

    public static function newFactory(): SubjectFactory
    {
        return new SubjectFactory;
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function can(string $permission): bool
    {
        // Κάνε χρήση του memoization του RBAC για να αποφύγουμε
        // την επανάληψη εκτέλεσης ερωτημάτων στη βάση
        return RBAC::can($this->id, $permission);
    }
}
