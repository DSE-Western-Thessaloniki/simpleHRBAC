<?php

namespace Dsewth\SimpleHRBAC\Models;

use Dsewth\SimpleHRBAC\Factories\RoleFactory;
use Dsewth\SimpleHRBAC\Helpers\ClosureTable;
use Dsewth\SimpleHRBAC\Helpers\DataHelper;
use Dsewth\SimpleHRBAC\Observers\RoleObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

#[ObservedBy([RoleObserver::class])]
class Role extends Model
{
    use HasFactory;

    /** @var array */
    protected $fillable = ['name', 'description', 'parent_id'];

    public $timestamps = false;

    public static function newFactory(): RoleFactory
    {
        return new RoleFactory;
    }

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
    public function immediateChildren(): Collection
    {
        return $this->tree()->immediateChildren();
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

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(DataHelper::getUserModelClass());
    }
}
