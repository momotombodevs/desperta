<?php

it('loads the alarm plugin from its Packagist release in vendor', function () {
    $composer = json_decode(file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);
    $lock = json_decode(file_get_contents(base_path('composer.lock')), true, flags: JSON_THROW_ON_ERROR);
    $localAlarmRepository = collect($composer['repositories'])->first(
        fn (array $repository): bool => ($repository['url'] ?? null) === 'packages/momotombo/nativephp-alarms'
    );
    $lockedAlarmPackage = collect($lock['packages'])->first(
        fn (array $package): bool => ($package['name'] ?? null) === 'momotombo/nativephp-alarms'
    );
    $providerPath = (new ReflectionClass(\Momotombo\NativePHPAlarms\AlarmServiceProvider::class))->getFileName();

    // Installed as real files in vendor/, the plugin is bundled into the native app
    // like any other dependency; no local path repository or classmap override is needed.
    expect($localAlarmRepository)->toBeNull()
        ->and($lockedAlarmPackage['dist']['type'] ?? null)->toBe('zip')
        ->and(realpath($providerPath))->toBe(
            realpath(base_path('vendor/momotombo/nativephp-alarms/src/AlarmServiceProvider.php'))
        );
});
