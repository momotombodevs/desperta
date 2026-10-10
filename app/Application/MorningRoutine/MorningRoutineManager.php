<?php

namespace App\Application\MorningRoutine;

use App\Models\AlarmExecution;
use App\Models\AlarmExecutionRoutineStep;
use App\Models\MorningRoutineStep;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class MorningRoutineManager
{
    /** @return Collection<int, MorningRoutineStep> */
    public function configuredSteps(): Collection
    {
        return MorningRoutineStep::query()->orderBy('position')->orderBy('id')->get();
    }

    public function addStep(string $label): MorningRoutineStep
    {
        $label = $this->validatedLabel($label);

        $lastPosition = MorningRoutineStep::query()->max('position');

        return MorningRoutineStep::query()->create([
            'label' => $label,
            'position' => $lastPosition === null ? 0 : (int) $lastPosition + 1,
        ]);
    }

    public function updateStep(MorningRoutineStep $step, string $label): void
    {
        $step->update(['label' => $this->validatedLabel($label)]);
    }

    public function deleteStep(MorningRoutineStep $step): void
    {
        DB::transaction(function () use ($step): void {
            $step->delete();
            $this->normalizePositions();
        });
    }

    public function moveStep(MorningRoutineStep $step, int $direction): void
    {
        if (! in_array($direction, [-1, 1], true)) {
            throw new InvalidArgumentException('Routine steps can only move one position at a time.');
        }

        DB::transaction(function () use ($step, $direction): void {
            $steps = $this->configuredSteps();
            $index = $steps->search(fn (MorningRoutineStep $item): bool => $item->is($step));
            $targetIndex = $index === false ? false : $index + $direction;

            if ($index === false || ! $steps->has($targetIndex)) {
                return;
            }

            $current = $steps->get($index);
            $target = $steps->get($targetIndex);
            $currentPosition = $current->position;
            $current->update(['position' => $target->position]);
            $target->update(['position' => $currentPosition]);
        });
    }

    /** @return Collection<int, AlarmExecutionRoutineStep> */
    public function executionSteps(AlarmExecution $execution): Collection
    {
        return AlarmExecutionRoutineStep::query()
            ->where('alarm_execution_id', $execution->id)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    public function beginForExecution(AlarmExecution $execution): void
    {
        if ($execution->status !== 'completed') {
            throw new InvalidArgumentException('A morning routine can only follow a completed alarm.');
        }

        DB::transaction(function () use ($execution): void {
            if (AlarmExecutionRoutineStep::query()->where('alarm_execution_id', $execution->id)->exists()) {
                return;
            }

            foreach ($this->configuredSteps() as $step) {
                AlarmExecutionRoutineStep::query()->create([
                    'alarm_execution_id' => $execution->id,
                    'morning_routine_step_id' => $step->id,
                    'label' => $step->label,
                    'position' => $step->position,
                ]);
            }
        });
    }

    public function completeStep(AlarmExecution $execution, string $stepId): void
    {
        $step = AlarmExecutionRoutineStep::query()
            ->where('alarm_execution_id', $execution->id)
            ->whereKey($stepId)
            ->firstOrFail();

        if ($step->completed_at === null) {
            $step->update(['completed_at' => now()]);
        }
    }

    public function executionStatus(AlarmExecution $execution): string
    {
        $steps = $this->executionSteps($execution);

        if ($steps->isEmpty()) {
            return 'not_configured';
        }

        return $steps->every(fn (AlarmExecutionRoutineStep $step): bool => $step->completed_at !== null)
            ? 'complete'
            : 'incomplete';
    }

    private function validatedLabel(string $label): string
    {
        $label = trim($label);
        $validator = Validator::make(['label' => $label], ['label' => ['required', 'string', 'max:80']]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $label;
    }

    private function normalizePositions(): void
    {
        foreach ($this->configuredSteps()->values() as $position => $step) {
            if ($step->position !== $position) {
                $step->update(['position' => $position]);
            }
        }
    }
}
