<?php

return [
    'validation' => [
        'title_max_length' => 80,
        'description_min_length' => 50,
        'description_max_length' => 1000,
        'conditions' => ['Neu', 'Sehr gut', 'Gut', 'In Ordnung', 'Defekt'],
        'shipping_options' => ['klein', 'mittel'],
    ],

    'status' => [
        'default' => 'Entwurf',
        'options' => ['Entwurf', 'Online', 'Archiviert'],
    ],
];
