<?php

namespace Dsewth\SimpleHRBAC\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    /** @var array */
    protected $fillable = ['name'];

    public $timestamps = false;
}
