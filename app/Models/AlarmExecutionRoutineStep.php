<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlarmExecutionRoutineStep extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = ['alarm_execution_id', 'morning_routine_step_id', 'label', 'position', 'completed_at'];

    protected function casts(): array
    {
        return ['position' => 'integer', 'completed_at' => 'immutable_datetime'];
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(AlarmExecution::class, 'alarm_execution_id');
    }

    public function routineStep(): BelongsTo
    {
        return $this->belongsTo(MorningRoutineStep::class, 'morning_routine_step_id');
    }
}
