<?php

it('defines the blue brand palette consistently across native UI and Android controls', function () {
    expect(config('native-ui.theme.light'))
        ->toMatchArray([
            'primary' => '#1D4ED8',
            'on-primary' => '#FFFFFF',
            'background' => '#F8FAFC',
            'success' => '#15803D',
            'warning' => '#B45309',
            'sunrise' => '#C2410C',
        ]);

    expect(config('native-ui.theme.dark'))
        ->toMatchArray([
            'primary' => '#2563EB',
            'on-primary' => '#FFFFFF',
            'background' => '#0B1120',
            'success' => '#86EFAC',
            'warning' => '#FCD34D',
            'sunrise' => '#FDBA74',
        ]);

    expect(config('nativephp.android.theme'))
        ->toMatchArray([
            'color_primary' => '#1D4ED8',
            'color_primary_night' => '#2563EB',
            'color_on_primary' => '#FFFFFF',
        ]);
});
