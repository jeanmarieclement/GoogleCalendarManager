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

    // OAuth 2.0 Client Credentials (from credentials.json)
    'client_id' => 'YOUR_CLIENT_ID',
    'client_secret' => 'YOUR_CLIENT_SECRET',
    'redirect_uri' => 'urn:ietf:wg:oauth:2.0:oob', // For desktop applications

    // Optional: Default calendar ID
    'default_calendar_id' => 'primary',

    // OAuth 2.0 Scopes
    'scopes' => [
        'https://www.googleapis.com/auth/calendar',
        // Add additional scopes as needed
        // 'https://www.googleapis.com/auth/calendar.readonly', // Read-only access
        // 'https://www.googleapis.com/auth/calendar.events', // Manage events only
    ],

    // Token Storage
    'token_path' => __DIR__ . '/../token.json',

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
