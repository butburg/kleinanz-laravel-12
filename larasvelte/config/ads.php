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
        'max_size' => 1000,
        'thumbnail_width' => 220,
        'thumbnail_max_height' => 880,
        'jpeg_quality' => 85,
        'progressive' => true,
        'supported_formats' => ['jpg', 'jpeg', 'png', 'webp', 'avif'],
        'client' => [
            'max_dimension' => 1000,
            'quality' => 90,
            'output_mime' => 'image/jpeg',
        ],
    ],

    'status' => [
        'default' => 'Entwurf',
        'options' => ['Entwurf', 'Online', 'Archiviert'],
    ],

    'openai' => [
        'model' => 'gpt-4o-mini',
        'temperature' => 0.7,
        'max_tokens' => 1000,
        'timeout' => 30,
    ],

    'auto_crop' => [
        'enabled' => true,
        'method' => 'python_subprocess',
        'detection_threshold' => 0.7,
        'closeup_threshold' => 0.80, // Crop only if main clothing item fills less than x area of the image
        'margin_percent' => 2,
        'script_path' => base_path('scripts/auto_crop.py'),
        'timeout' => 60,
    ],
];
