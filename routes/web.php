<?php

use App\NativeComponents\AlarmEditor;
use App\NativeComponents\Challenge;
use App\NativeComponents\Habits;
use App\NativeComponents\History;
use App\NativeComponents\Home;
use App\NativeComponents\Settings;
use Illuminate\Support\Facades\Route;

Route::native('/', Home::class);
Route::native('/settings', Settings::class);
Route::native('/settings/habits', Habits::class);
Route::native('/settings/history', History::class);
Route::native('/alarms/new', AlarmEditor::class);
Route::native('/alarms/{alarm}/edit', AlarmEditor::class);
Route::native('/challenge', Challenge::class);
Route::native('/challenge/{alarmId}/{executionId}/{scheduledFor}', Challenge::class);
