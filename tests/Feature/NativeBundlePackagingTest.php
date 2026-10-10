<?php

it('loads the alarm plugin from its packaged source path', function () {
    $composer = json_decode(file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);
    $lock = json_decode(file_get_contents(base_path('composer.lock')), true, flags: JSON_THROW_ON_ERROR);
    $classmap = require base_path('vendor/composer/autoload_classmap.php');
    $alarmRepository = collect($composer['repositories'])->first(
        fn (array $repository): bool => ($repository['type'] ?? null) === 'path'
            && ($repository['url'] ?? null) === 'packages/momotombo/nativephp-alarms'
    );
    $lockedAlarmPackage = collect($lock['packages'])->first(
        fn (array $package): bool => ($package['name'] ?? null) === 'momotombo/nativephp-alarms'
    );

    expect($alarmRepository)->not->toBeNull()
        ->and($alarmRepository['options']['symlink'] ?? null)->toBeFalse()
        ->and($lockedAlarmPackage['transport-options']['symlink'] ?? null)->toBeFalse()
        ->and($classmap['Momotombo\\NativePHPAlarms\\AlarmServiceProvider'])->toBe(
            base_path('packages/momotombo/nativephp-alarms/src/AlarmServiceProvider.php')
        );
});
