<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MorningRoutineStep extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = ['label', 'position'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }
}
