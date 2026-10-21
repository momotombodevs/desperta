<?php

return [
    'types' => [
        'trivia' => ['name' => 'Trivia', 'instruction' => 'Elegí la respuesta correcta.'],
        'memory' => [
            'name' => 'Memoria',
            'instruction' => 'Memorizá la secuencia y después respondé sin verla.',
            'question' => '¿Qué número estaba en la posición :position?',
            'explanation' => 'La secuencia era :sequence.',
        ],
        'sequence' => [
            'name' => 'Secuencias',
            'instruction' => 'Encontrá el patrón y elegí el siguiente número.',
            'prompt' => '¿qué sigue?',
            'explanation' => 'Cada número suma :step al anterior.',
        ],
        'mental_math' => ['name' => 'Cálculo mental', 'instruction' => 'Resolvé el cálculo mentalmente.'],
    ],
    'nicaragua' => require __DIR__.'/challenges/nicaragua.php',
    'math' => require __DIR__.'/challenges/math.php',
    'general_knowledge' => require __DIR__.'/challenges/general_knowledge.php',
    'world_geography' => require __DIR__.'/challenges/world_geography.php',
    'latin_america' => require __DIR__.'/challenges/latin_america.php',
    'football' => require __DIR__.'/challenges/football.php',
    'wizarding_world' => require __DIR__.'/challenges/wizarding_world.php',
    'science_space' => require __DIR__.'/challenges/science_space.php',
    'animals' => require __DIR__.'/challenges/animals.php',
    'history' => require __DIR__.'/challenges/history.php',
    'languages' => require __DIR__.'/challenges/languages.php',
    'mix' => ['name' => 'Mezcla de todo'],
];
