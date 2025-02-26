<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\GoogleCalendar\GoogleCalendarManager;

// Load configuration
$config = require __DIR__ . '/../config/calendar-config.php';

try {
    // Initialize the Calendar Manager
    $calendarManager = new GoogleCalendarManager($config);

    // Check if we're handling an OAuth callback
    if (isset($_GET['code'])) {
        // Handle the callback and save the token
        $calendarManager->handleAuthCallback($_GET['code']);
        echo "Authentication successful!";
        exit;
    }

    // Check if we need authentication
    if (!$calendarManager->isAuthenticated()) {
        // Redirect to Google's auth page
        $authUrl = $calendarManager->getAuthUrl();
        echo "Please <a href='{$authUrl}'>click here</a> to authenticate with Google Calendar.";
        exit;
    }

    // Example: List upcoming events
    $events = $calendarManager->listEvents(
        new DateTime('now'),
        (new DateTime('now'))->modify('+1 week'),
        10
    );

    echo "<h2>Upcoming Events:</h2>";
    echo "<pre>";
    var_dump($events);
    echo "</pre>";
    foreach ($events as $event) {
        echo "<p>{$event['summary']} - {$event['start']}</p>";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
