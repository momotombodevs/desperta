<?php

return [
    'types' => [
        'trivia' => ['name' => 'Trivia', 'instruction' => 'Choose the correct answer.'],
        'memory' => [
            'name' => 'Memory',
            'instruction' => 'Memorize the sequence, then answer from memory.',
            'question' => 'Which number was in position :position?',
            'explanation' => 'The sequence was :sequence.',
        ],
        'sequence' => [
            'name' => 'Sequences',
            'instruction' => 'Find the pattern and choose the next number.',
            'prompt' => 'what comes next?',
            'explanation' => 'Each number adds :step to the previous one.',
        ],
        'mental_math' => ['name' => 'Mental math', 'instruction' => 'Solve the calculation in your head.'],
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
    'mix' => ['name' => 'Mix of everything'],
];
