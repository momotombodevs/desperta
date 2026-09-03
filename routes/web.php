<?php

use App\NativeComponents\AlarmEditor;
use App\NativeComponents\Challenge;
use App\NativeComponents\Home;
use Illuminate\Support\Facades\Route;

Route::native('/', Home::class);
Route::native('/alarms/new', AlarmEditor::class);
Route::native('/alarms/{alarm}/edit', AlarmEditor::class);
Route::native('/challenge', Challenge::class);
