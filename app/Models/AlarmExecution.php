<?php

namespace App\Models;

use Database\Factories\AlarmExecutionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlarmExecution extends Model
{
    /** @use HasFactory<AlarmExecutionFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'id', 'alarm_id', 'alarm_label', 'alarm_time', 'status', 'scheduled_for',
        'started_at', 'snoozed_at', 'finished_at', 'snooze_count', 'challenge_progress',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'snoozed_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'snooze_count' => 'integer',
            'challenge_progress' => 'array',
        ];
    }

    public function alarm(): BelongsTo
    {
        return $this->belongsTo(Alarm::class);
    }

    public function displayStatus(): string
    {
        return __('app.alarm_execution_status.'.$this->status);
    }

    public function displayLabel(): string
    {
        $label = trim((string) $this->alarm_label);

        return $label !== '' ? $label : __('app.untitled_alarm');
    }

    public function displayTimestamp(): string
    {
        return $this->scheduled_for->locale(app()->getLocale())->isoFormat('D MMM, h:mm a');
    }
}
