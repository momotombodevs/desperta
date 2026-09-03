<?php

it('keeps application translation keys aligned across supported locales', function () {
    $spanish = require lang_path('es_NI/app.php');
    $english = require lang_path('en/app.php');

    expect(array_keys($spanish))->toBe(array_keys($english));
});

it('keeps native accessibility copy in translation catalogs', function () {
    $nativeViews = implode("\n", array_map(
        fn (string $view): string => file_get_contents($view),
        glob(resource_path('views/native/*.blade.php')),
    ));

    expect($nativeViews)
        ->not->toContain('a11y-label="Opciones de respuesta"')
        ->not->toContain('a11y-label="Alarma activa"')
        ->not->toContain('a11y-label="Lunes"');
});
