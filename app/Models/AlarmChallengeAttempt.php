<?php

namespace App\Models;

use Database\Factories\AlarmChallengeAttemptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlarmChallengeAttempt extends Model
{
    /** @use HasFactory<AlarmChallengeAttemptFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'alarm_id',
        'alarm_execution_id',
        'challenge_theme',
        'attempt_number',
        'correct_answers',
        'question_count',
        'required_correct_answers',
        'passed',
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'correct_answers' => 'integer',
            'question_count' => 'integer',
            'required_correct_answers' => 'integer',
            'passed' => 'boolean',
        ];
    }

    public function alarm(): BelongsTo
    {
        return $this->belongsTo(Alarm::class);
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(AlarmExecution::class, 'alarm_execution_id');
    }
}
