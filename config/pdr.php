<?php

declare(strict_types=1);

return [
    'profiles' => [
        'production' => ['version' => 'production-v1', 'name' => 'Production', 'employee_prompt' => 'Reflect on practical work, learning and safe working examples.', 'manager_prompt' => 'Safe working and process observations'],
        'office' => ['version' => 'office-v1', 'name' => 'Office', 'employee_prompt' => 'Reflect on service, accuracy, collaboration and learning.', 'manager_prompt' => 'Accuracy and service observations'],
        'leadership' => ['version' => 'leadership-v1', 'name' => 'Leadership', 'employee_prompt' => 'Reflect on leadership, development and team support.', 'manager_prompt' => 'Coaching and team outcomes'],
    ],
];
