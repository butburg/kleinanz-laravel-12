<?php

return [
    'validation' => [
        'title_max_length' => 80,
        'description_min_length' => 50,
        'description_max_length' => 1000,
        'prompt_max_length' => 1000,
        'conditions' => ['Neu', 'Sehr gut', 'Gut', 'In Ordnung', 'Defekt'],
        'shipping_options' => ['klein', 'mittel'],
    ],

    'image' => [
        'max_files' => 10,
        'max_file_kb' => 5120,
        'supported_formats' => ['jpg', 'jpeg', 'png', 'webp', 'avif'],
    ],

    'status' => [
        'default' => 'Entwurf',
        'options' => ['Entwurf', 'Online', 'Archiviert'],
    ],

    'openai' => [
        'model' => 'gpt-5-nano',
        'temperature' => 0.7,
        'max_tokens' => 1000,
        'timeout' => 30,
    ],
];
