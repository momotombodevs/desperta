<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppPreference extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'key',
        'value',
    ];
}
