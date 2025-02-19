# Google Calendar Manager

A PHP class for managing Google Calendar events using the official Google API.

## Requirements

- PHP 7.2 or higher
- Composer
- Google Calendar API credentials

## Installation

1. Install via Composer:
```bash
composer require app/google-calendar-manager
```

2. Set up Google Calendar API:
   - Go to the [Google Cloud Console](https://console.cloud.google.com)
   - Create a new project or select an existing one
   - Enable the Google Calendar API
   - Create credentials (OAuth 2.0 Client ID)
   - Download the credentials file as `credentials.json`

## Usage

```php
// Initialize the manager
$calendar = new GoogleCalendarManager(
    '/path/to/credentials.json',
    '/path/to/token.json',
    'primary' // Optional default calendar ID
);

// List available calendars
$calendars = $calendar->getCalendars();

// Create an event
$eventId = $calendar->createEvent([
    'summary' => 'Meeting',
    'description' => 'Team weekly meeting',
    'start' => [
        'dateTime' => '2025-02-20T10:00:00',
        'timeZone' => 'Europe/Rome',
    ],
    'end' => [
        'dateTime' => '2025-02-20T11:00:00',
        'timeZone' => 'Europe/Rome',
    ],
]);

// Update an event
$calendar->updateEvent($eventId, [
    'summary' => 'Updated Meeting Title'
]);

// Get event details
$event = $calendar->getEvent($eventId);

// List events
$events = $calendar->listEvents(
    '2025-02-20T00:00:00Z', // Start time
    '2025-02-21T00:00:00Z', // End time
    10 // Max results
);

// Delete an event
$calendar->deleteEvent($eventId);
```

## Security

- Store your credentials and token files securely
- Never commit these files to version control
- Use environment variables for sensitive data

## Error Handling

The class throws exceptions in the following cases:
- `RuntimeException`: When credentials are invalid or missing
- `InvalidArgumentException`: When required event data is missing
- `RuntimeException`: When no calendar is selected

## Contributing

Feel free to submit issues and enhancement requests.

## License

This project is licensed under the MIT License.
