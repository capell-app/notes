<?php

declare(strict_types=1);

return [
    'notifications' => [
        'channels' => ['database'],
    ],
    'reminders' => [
        'schedule_enabled' => true,
        // Enable only after every reminder producer and scheduler uses version-3-compatible code.
        'timezone_schedules_enabled' => env('CAPELL_NOTES_TIMEZONE_SCHEDULES_ENABLED', false),
    ],
];
