<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Google Calendar API Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration settings for the Google Calendar API
    | integration. Make a copy of this file as 'calendar-config.php' and update
    | the values according to your setup.
    |
    */

    // Path to the credentials.json file downloaded from Google Cloud Console
    'credentials_path' => __DIR__ . '/../credentials.json',

    // Path where the OAuth token will be stored
    'token_path' => __DIR__ . '/../token.json',

    // Default calendar ID (optional)
    // Use 'primary' for the user's primary calendar, or a specific calendar ID
    'default_calendar_id' => 'primary',

    // Application name as shown to Google and users
    'application_name' => 'My Calendar Application',

    // Timezone for events (default to Europe/Rome)
    'timezone' => 'Europe/Rome',

    // Maximum results per page when listing events
    'max_results_per_page' => 10,

    // Cache configuration (optional)
    'cache' => [
        'enabled' => false,
        'path' => __DIR__ . '/../cache',
        'ttl' => 3600 // Time in seconds
    ],

    // Logging configuration (optional)
    'logging' => [
        'enabled' => true,
        'path' => __DIR__ . '/../logs/calendar.log',
        'level' => 'ERROR' // DEBUG, INFO, WARNING, ERROR
    ]
];
