# Google Calendar API Configuration Guide

This directory contains the configuration files for the Google Calendar Manager.

## Getting Started with Google Calendar API

Follow these steps to set up your Google Calendar API credentials:

1. **Create a Google Cloud Project**
   - Go to the [Google Cloud Console](https://console.cloud.google.com)
   - Click on the project dropdown and select "New Project"
   - Enter a name for your project and click "Create"

2. **Enable the Google Calendar API**
   - In your project, go to "APIs & Services" > "Library"
   - Search for "Google Calendar API"
   - Click on the API and then click "Enable"

3. **Configure OAuth Consent Screen**
   - Go to "APIs & Services" > "OAuth consent screen"
   - Select the appropriate user type (External or Internal)
   - Fill in the required application information:
     - App name
     - User support email
     - Developer contact information
   - Click "Save and Continue"
   - Add the necessary scopes (at minimum `https://www.googleapis.com/auth/calendar` for full access)
   - Click "Save and Continue"
   - Add test users if you selected External user type
   - Click "Save and Continue" and then "Back to Dashboard"

4. **Create OAuth 2.0 Credentials**
   - Go to "APIs & Services" > "Credentials"
   - Click "Create Credentials" > "OAuth client ID"
   - Select "Desktop application" as the application type
   - Enter a name for the OAuth client
   - Click "Create"
   - Download the credentials by clicking the download icon (JSON)
   - Save the downloaded file as `credentials.json` in a secure location

## Setting Up Configuration File

1. Copy `calendar-config.template.php` to `calendar-config.php`:
```bash
cp calendar-config.template.php calendar-config.php
```

2. Edit `calendar-config.php` and update the following values:
   - `client_id`: Your OAuth 2.0 client ID (from credentials.json)
   - `client_secret`: Your OAuth 2.0 client secret (from credentials.json)
   - `redirect_uri`: Your redirect URI (typically set to "urn:ietf:wg:oauth:2.0:oob" for desktop applications)
   - `token_path`: Path where the OAuth token will be stored
   - `application_name`: Your application name
   - `default_calendar_id`: Your default calendar ID (optional, defaults to 'primary')
   - `timezone`: Your preferred timezone
   - Other optional settings as needed

## Configuration Options

### Required Settings
- `client_id`: Your OAuth 2.0 client ID
- `client_secret`: Your OAuth 2.0 client secret
- `redirect_uri`: Your redirect URI
- `application_name`: Name of your application
- `token_path`: Path where the OAuth token will be stored

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

1. Never commit `calendar-config.php` or your credential files to version control
2. Keep your credentials.json and token.json files secure
3. Use environment variables for sensitive data in production
4. Regularly rotate OAuth tokens for security
5. Apply the principle of least privilege when selecting API scopes
