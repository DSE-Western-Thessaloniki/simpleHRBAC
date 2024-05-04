<?php

namespace Dsewth\SimpleHRBAC\Models;

use Dsewth\SimpleHRBAC\Helpers\ClosureTable;
use Dsewth\SimpleHRBAC\Observers\RoleObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[ObservedBy([RoleObserver::class])]
class Role extends Model
{
    /** @var array */
    protected $fillable = ['name', 'description', 'parent_id'];

    public $timestamps = false;

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'parent_id');
    }

    public function children()
    {
        $this->tree()->children();
    }

    public function tree(): ClosureTable
    {
        return new ClosureTable($this);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }
}
