# Google Calendar Manager Configuration

This directory contains the configuration files for the Google Calendar Manager.

## Setup Instructions

1. Copy `calendar-config.template.php` to `calendar-config.php`:
```bash
cp calendar-config.template.php calendar-config.php
```

2. Edit `calendar-config.php` and update the following values:
   - `credentials_path`: Path to your Google API credentials file
   - `token_path`: Path where the OAuth token will be stored
   - `default_calendar_id`: Your default calendar ID (optional)
   - `application_name`: Your application name
   - `timezone`: Your preferred timezone
   - Other optional settings as needed

## Configuration Options

### Required Settings
- `credentials_path`: Path to the credentials.json file from Google Cloud Console
- `token_path`: Path where the OAuth token will be stored
- `application_name`: Name of your application

### Optional Settings
- `default_calendar_id`: Default calendar to use (defaults to 'primary')
- `timezone`: Default timezone for events (defaults to 'Europe/Rome')
- `max_results_per_page`: Maximum number of events to return per page

### Cache Configuration
Enable caching to improve performance:
```php
'cache' => [
    'enabled' => true,
    'path' => '/path/to/cache',
    'ttl' => 3600 // Time in seconds
]
```

### Logging Configuration
Configure error and debug logging:
```php
'logging' => [
    'enabled' => true,
    'path' => '/path/to/logs/calendar.log',
    'level' => 'ERROR' // DEBUG, INFO, WARNING, ERROR
]
```

## Security Notes

1. Never commit `calendar-config.php` to version control
2. Keep your credentials.json and token.json files secure
3. Use environment variables for sensitive data in production
4. Regularly rotate OAuth tokens for security
