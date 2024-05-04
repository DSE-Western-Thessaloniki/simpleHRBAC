<?php

namespace Dsewth\SimpleHRBAC\Models;

use Dsewth\SimpleHRBAC\Helpers\ClosureTable;
use Dsewth\SimpleHRBAC\Observers\RoleObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

#[ObservedBy([RoleObserver::class])]
class Role extends Model
{
    /** @var array */
    protected $fillable = ['name', 'description', 'parent_id'];

    public $timestamps = false;

    public function parent(): ?Role
    {
        return Role::find($this->parent_id);
    }

    /**
     * @return Collection<Role>
     */
    public function children(): Collection
    {
        return $this->tree()->children();
    }

    /**
     * @return Collection<Role>
     */
    public function parents(): Collection
    {
        return $this->tree()->parents();
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
