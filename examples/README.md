# Google Calendar Manager Examples

This directory contains example scripts demonstrating how to use the Google Calendar Manager library.

## Prerequisites

Before running the examples:

1. Install dependencies:
```bash
composer install
```

2. Set up your configuration:
```bash
cp ../config/calendar-config.template.php ../config/calendar-config.php
```

3. Edit `calendar-config.php` with your Google Calendar API credentials

## Available Examples

### calendar-example.php

A comprehensive example that demonstrates:
- Listing available calendars
- Creating a new event with attendees and reminders
- Retrieving event details
- Updating an existing event
- Listing upcoming events
- Deleting an event

## Running the Example

### Using Docker (Recommended)

1. Start the Docker container:
```bash
docker-compose -f ../compose.yml up -d
```

2. Access the example at:
```
http://localhost:8080/calendar-example.php
```

3. Authentication:
   - On first access, you'll be redirected to Google's authentication page
   - After authorizing the application, you'll receive an authorization code
   - Add the code to the URL as a query parameter:
   ```
   http://localhost:8080/calendar-example.php?code=YOUR_AUTHORIZATION_CODE
   ```
   - The application will use this code to obtain and store an access token for future requests

### Using PHP CLI

To run the example directly with PHP:
```bash
php calendar-example.php
```

## Expected Output

The example will output something like:
```
Available Calendars:
- My Calendar (ID: primary)
- Team Calendar (ID: team@group.calendar.google.com)

Creating new event...
Event created with ID: abc123xyz

Event details:
- Title: Team Meeting
- Description: Weekly team sync-up meeting
- Location: Conference Room A
- Start: 2025-02-20T10:00:00+01:00
- End: 2025-02-20T11:00:00+01:00

Updating event...
Event updated successfully

Upcoming events:
- Updated: Team Meeting + Project Review (2025-02-20T10:00:00+01:00)
- Other Event (2025-02-21T15:00:00+01:00)

Deleting event...
Event deleted successfully
```

## Error Handling

The example includes proper error handling and will display meaningful error messages if something goes wrong, such as:
- Invalid credentials
- Network connectivity issues
- Invalid calendar or event IDs
- Missing required fields
- Authentication failures

## Google Calendar Manager Example Application

This example demonstrates how to use the Google Calendar Manager library to create a web application that interacts with Google Calendar.

### Features

- 🔐 OAuth 2.0 Authentication with Google Calendar API
- 📅 Calendar Selection from user's available calendars
- 📆 Event Management:
  - View events in a date range
  - Create new events (with date/time or all-day)
  - Delete existing events
- 🎨 Modern UI with Bootstrap 5
- 📱 Responsive design for all devices
- ⚡ Real-time updates
- 🔄 Automatic token refresh

### Requirements

- PHP 7.4 or higher
- Composer for dependency management
- Google Cloud Platform account with Calendar API enabled
- OAuth 2.0 credentials (client ID and secret)
- SSL recommended for production use

### Installation

1. Install dependencies:
```bash
composer install
```

2. Configure OAuth credentials:
   - Copy `config/calendar-config.template.php` to `config/calendar-config.php`
   - Add your Google OAuth credentials:
     - Client ID
     - Client Secret
     - Redirect URI (use `urn:ietf:wg:oauth:2.0:oob` for testing)

3. Set up directories:
```bash
mkdir -p token
chmod 755 token
mkdir -p logs
chmod 755 logs
```

### Usage

1. Start a PHP development server:
```bash
php -S localhost:8080 -t examples/
```

2. Open your browser and navigate to:
```
http://localhost:8080/calendar-example.php
```

3. Follow the OAuth authentication flow:
   - Click "Authenticate with Google"
   - Grant the required permissions
   - The token will be automatically saved

### Code Structure

- `calendar-example.php`: Main application file
  - OAuth flow handling
  - Calendar selection
  - Event management interface
  - Date formatting utilities

### Security Considerations

- Always validate user input
- Store tokens securely
- Use HTTPS in production
- Keep OAuth credentials confidential
- Implement proper session management

### Customization

You can customize the example by:

1. Modifying the UI:
   - Edit the Bootstrap classes
   - Change the date picker configuration
   - Adjust the layout structure

2. Adding features:
   - Event editing
   - Recurring events
   - Calendar sharing
   - Event filtering
   - Advanced search

3. Enhancing security:
   - Add user authentication
   - Implement CSRF protection
   - Add request rate limiting

### Troubleshooting

1. Authentication Issues:
   - Verify OAuth credentials
   - Check redirect URI configuration
   - Ensure proper token storage permissions

2. Event Display Problems:
   - Check date format compatibility
   - Verify timezone settings
   - Review API quota limits

3. General Issues:
   - Check PHP error logs
   - Verify file permissions
   - Ensure all dependencies are installed

### Best Practices

1. Error Handling:
   - Implement proper exception handling
   - Show user-friendly error messages
   - Log errors for debugging

2. Performance:
   - Cache API responses when possible
   - Limit the number of events fetched
   - Use pagination for large datasets

3. User Experience:
   - Provide clear feedback
   - Implement loading indicators
   - Add confirmation dialogs for destructive actions

### Support

For issues and questions:
- Check the [GitHub repository](https://github.com/yourusername/google-calendar-manager)
- Review the [Google Calendar API documentation](https://developers.google.com/calendar)
- Submit issues through the project's issue tracker

### License

This example is part of the Google Calendar Manager library and is released under the MIT License. See the LICENSE file for details.
