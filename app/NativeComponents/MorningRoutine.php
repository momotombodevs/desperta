<?php

namespace App\NativeComponents;

use App\Application\MorningRoutine\MorningRoutineManager;
use App\Application\Preferences\AppPreferences;
use App\Models\AlarmExecution;
use App\Models\AlarmExecutionRoutineStep;
use App\Models\MorningRoutineStep;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;

final class MorningRoutine extends NativeComponent
{
    public string $executionId = '';

    public string $stepLabel = '';

    public string $editingStepId = '';

    public string $editingLabel = '';

    public string $validationMessage = '';

    public function mount(): void
    {
        app(AppPreferences::class)->applyLanguage();
        $this->executionId = (string) $this->param('executionId', $this->data('executionId', $this->executionId));

        if ($this->executionId !== '') {
            abort_unless($this->execution()?->status === 'completed', 404);
        }
    }

    /** @return Collection<int, MorningRoutineStep> */
    #[Computed]
    public function configuredSteps(): Collection
    {
        return app(MorningRoutineManager::class)->configuredSteps();
    }

    /** @return Collection<int, AlarmExecutionRoutineStep> */
    #[Computed]
    public function executionSteps(): Collection
    {
        $execution = $this->execution();

        return $execution === null
            ? new Collection
            : app(MorningRoutineManager::class)->executionSteps($execution);
    }

    #[Computed]
    public function routineStatus(): string
    {
        $execution = $this->execution();

        return $execution === null
            ? 'not_configured'
            : app(MorningRoutineManager::class)->executionStatus($execution);
    }

    public function addStep(): void
    {
        $this->stepLabel = trim($this->stepLabel);
        $this->validationMessage = '';

        try {
            app(MorningRoutineManager::class)->addStep($this->stepLabel);
        } catch (ValidationException $exception) {
            $this->validationMessage = $exception->validator->errors()->first('label');

            return;
        }

        $this->stepLabel = '';
    }

    public function editStep(string $stepId): void
    {
        $step = MorningRoutineStep::query()->findOrFail($stepId);
        $this->editingStepId = $step->id;
        $this->editingLabel = $step->label;
    }

    public function saveStep(): void
    {
        $this->editingLabel = trim($this->editingLabel);
        $this->validationMessage = '';

        try {
            $step = MorningRoutineStep::query()->findOrFail($this->editingStepId);
            app(MorningRoutineManager::class)->updateStep($step, $this->editingLabel);
        } catch (ValidationException $exception) {
            $this->validationMessage = $exception->validator->errors()->first('label');

            return;
        }

        $this->cancelEditing();
    }

    public function cancelEditing(): void
    {
        $this->editingStepId = '';
        $this->editingLabel = '';
    }

    public function deleteStep(string $stepId): void
    {
        $step = MorningRoutineStep::query()->findOrFail($stepId);
        app(MorningRoutineManager::class)->deleteStep($step);
        $this->cancelEditing();
    }

    public function moveStepUp(string $stepId): void
    {
        $this->moveStep($stepId, -1);
    }

    public function moveStepDown(string $stepId): void
    {
        $this->moveStep($stepId, 1);
    }

    public function startRoutine(): void
    {
        $execution = $this->requireExecution();
        app(MorningRoutineManager::class)->beginForExecution($execution);
    }

    public function completeRoutineStep(string $stepId): void
    {
        $execution = $this->requireExecution();
        app(MorningRoutineManager::class)->completeStep($execution, $stepId);
    }

    public function navTitle(): string
    {
        return $this->executionId === '' ? __('app.morning_routine') : __('app.morning_routine_title');
    }

    public function render(): View
    {
        return view('native.morning-routine');
    }

    private function moveStep(string $stepId, int $direction): void
    {
        $step = MorningRoutineStep::query()->findOrFail($stepId);
        app(MorningRoutineManager::class)->moveStep($step, $direction);
    }

    private function execution(): ?AlarmExecution
    {
        return $this->executionId === ''
            ? null
            : AlarmExecution::query()->find($this->executionId);
    }

    private function requireExecution(): AlarmExecution
    {
        $execution = $this->execution();
        abort_unless($execution?->status === 'completed', 404);

        return $execution;
    }
}
