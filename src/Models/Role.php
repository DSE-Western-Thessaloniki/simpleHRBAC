<?php

namespace Dsewth\SimpleHRBAC\Models;

use Dsewth\SimpleHRBAC\Factories\RoleFactory;
use Dsewth\SimpleHRBAC\Helpers\ClosureTable;
use Dsewth\SimpleHRBAC\Helpers\DataHelper;
use Dsewth\SimpleHRBAC\Observers\RoleObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'parent_id');
    }

    /**
     * @return Collection<Role>
     */
    public function children($with = []): Collection
    {
        return $this->tree()->children($with);
    }

    /**
     * @return Collection<Role>
     */
    public function immediateChildren($with = []): Collection
    {
        return $this->tree()->immediateChildren($with);
    }

    /**
     * @return Collection<Role>
     */
    public function parents($with = []): Collection
    {
        return $this->tree()->parents($with);
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
        return $this->belongsToMany(DataHelper::getUserModelClass())
            ->using(RoleUser::class)
            ->withPivot('role_id');
    }
}
