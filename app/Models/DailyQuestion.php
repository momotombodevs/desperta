<?php

namespace App\Models;

use Database\Factories\DailyQuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyQuestion extends Model
{
    /** @use HasFactory<DailyQuestionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['date', 'challenge_theme', 'question', 'selected_option', 'correct', 'answered_at'];

    protected function casts(): array
    {
        return [
            'question' => 'array',
            'correct' => 'boolean',
            'answered_at' => 'immutable_datetime',
        ];
    }

    public function isAnswered(): bool
    {
        return $this->answered_at !== null;
    }
}
