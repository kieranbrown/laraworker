<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'title',
        'content',
    ];
}
