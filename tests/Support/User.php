<?php

namespace Tests\Support;

use EloquentWorks\RatingKit\Traits\HasRatings;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasRatings;

    /** @var list<string> */
    protected $fillable = ['name'];
}
