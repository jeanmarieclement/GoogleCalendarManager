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
