# Docker Setup for Google Calendar Manager

This document explains how to use Docker to test the Google Calendar Manager library.

## Prerequisites

- Docker installed on your system
- Docker Compose installed on your system

## Starting the Test Environment

1. Make sure you are in the project's root directory:

```bash
cd /path/to/classGoogleCalendar
```

2. Start the Docker containers:

```bash
docker-compose -f compose.yml up -d
```

3. Access the example application through your browser:

```
http://localhost:8080
```

## Docker Environment Structure

- **App Container**: An Apache server with PHP 7.4 running the application
- The container mounts the project directory as a volume, so file changes are immediately visible
- The `examples` directory is configured as Apache's document root

## Configuration

- The `config/calendar-config.php` file is automatically created from the template if it doesn't exist
- Directories for tokens, logs, and cache are automatically created with the correct permissions

## Troubleshooting

If you encounter OAuth authentication issues:

1. Verify that the OAuth credentials in `config/calendar-config.php` are correct
2. Make sure the redirect URI in the Google Cloud project settings is set to `urn:ietf:wg:oauth:2.0:oob`
3. Check Apache logs:

```bash
docker logs google-calendar-manager
```

## Stopping the Environment

To stop the containers:

```bash
docker-compose -f compose.yml down
```

To stop and remove all containers, networks, and volumes:

```bash
docker-compose -f compose.yml down -v
