<?php

declare(strict_types=1);

namespace App\Services\GoogleCalendar;

use DateTime;
use Exception;
use Google_Client;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;
use InvalidArgumentException;
use RuntimeException;

/**
 * Class GoogleCalendarManager
 * 
 * A class for managing Google Calendar events using the official Google API.
 * Uses OAuth 2.0 Client ID authentication method.
 */
class GoogleCalendarManager
{
    /** @var Google_Client */
    private $client;

    /** @var Google_Service_Calendar */
    private $service;

    /** @var string|null */
    private $calendarId;

    /** @var array */
    private $config;

    /** @var TokenEncryption|null */
    private $tokenEncryption = null;

    /**
     * @var resource|null Log file handle
     */
    private $logHandle = null;

    /**
     * @var string Log level
     */
    private $logLevel = 'ERROR';

    /**
     * @var array Log level priorities
     */
    private $logLevels = [
        'DEBUG' => 0,
        'INFO' => 1,
        'WARNING' => 2,
        'ERROR' => 3
    ];

    /**
     * GoogleCalendarManager constructor.
     *
     * @param array $config Configuration array containing client_id, client_secret, etc.
     * @param string|null $defaultCalendarId Optional default calendar ID
     * @throws RuntimeException If configuration is invalid
     */
    public function __construct(array $config, ?string $defaultCalendarId = null)
    {
        $this->validateConfig($config);
        $this->config = $config;
        $this->calendarId = $defaultCalendarId ?? 'primary';
        $this->initializeLogging();
        $this->initializeClient();
        $this->log('INFO', 'GoogleCalendarManager initialized');
    }

    /**
     * Validates the configuration array
     *
     * @param array $config
     * @throws RuntimeException
     */
    private function validateConfig(array $config): void
    {
        $requiredKeys = ['client_id', 'client_secret', 'redirect_uri'];
        foreach ($requiredKeys as $key) {
            if (!isset($config[$key]) || empty($config[$key])) {
                throw new RuntimeException("Missing required configuration: {$key}");
            }
        }
    }

    /**
     * Initialize logging
     */
    private function initializeLogging()
    {
        if (!isset($this->config['logging']) || !$this->config['logging']['enabled']) {
            return;
        }

        $this->logLevel = strtoupper($this->config['logging']['level'] ?? 'ERROR');

        if (!in_array($this->logLevel, array_keys($this->logLevels))) {
            $this->logLevel = 'ERROR';
        }

        $logPath = $this->config['logging']['path'] ?? dirname(__DIR__, 2) . '/logs/calendar.log';

        // Validate and sanitize log path to prevent path traversal
        $logPath = $this->validateFilePath($logPath, 'logs');

        $logDir = dirname($logPath);

        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0750, true)) {
                throw new RuntimeException('Failed to create log directory');
            }
        }

        $this->logHandle = fopen($logPath, 'a');
        if ($this->logHandle === false) {
            throw new RuntimeException('Failed to open log file');
        }

        // Set restrictive permissions on log file
        chmod($logPath, 0640);

        $this->log('DEBUG', 'Logging initialized with level: ' . $this->logLevel);
    }

    /**
     * Log a message
     *
     * @param string $level Log level (DEBUG, INFO, WARNING, ERROR)
     * @param string $message Message to log
     * @param array $context Additional context data
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if (!$this->logHandle) {
            return;
        }

        $level = strtoupper($level);
        
        // Check if we should log this level
        if ($this->logLevels[$level] < $this->logLevels[$this->logLevel]) {
            return;
        }

        $timestamp = (new \DateTime())->format('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logMessage = "[$timestamp] [$level] $message$contextStr" . PHP_EOL;
        
        fwrite($this->logHandle, $logMessage);
    }

    /**
     * Initializes the Google Client
     */
    private function initializeClient(): void
    {
        $this->client = new Google_Client();
        $this->client->setClientId($this->config['client_id']);
        $this->client->setClientSecret($this->config['client_secret']);
        $this->client->setRedirectUri($this->config['redirect_uri']);
        $this->client->setScopes($this->config['scopes'] ?? [Google_Service_Calendar::CALENDAR]);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');

        // Initialize token encryption if key is provided
        if (isset($this->config['encryption_key'])) {
            try {
                $this->tokenEncryption = new TokenEncryption($this->config['encryption_key']);
            } catch (\Exception $e) {
                $this->log('WARNING', 'Token encryption initialization failed: ' . $e->getMessage());
            }
        }

        // Try to load existing token
        if (isset($this->config['token_path'])) {
            $tokenPath = $this->validateFilePath($this->config['token_path'], 'token');

            if (file_exists($tokenPath)) {
                $tokenData = file_get_contents($tokenPath);
                if ($tokenData === false) {
                    throw new RuntimeException('Failed to read token file');
                }

                // Decrypt token if encryption is enabled
                if ($this->tokenEncryption !== null) {
                    try {
                        $tokenData = $this->tokenEncryption->decrypt($tokenData);
                    } catch (\Exception $e) {
                        $this->log('ERROR', 'Failed to decrypt token: ' . $e->getMessage());
                        throw new RuntimeException('Failed to decrypt token');
                    }
                }

                $accessToken = json_decode($tokenData, true);
                if ($accessToken === null) {
                    throw new RuntimeException('Invalid token format');
                }

                $this->client->setAccessToken($accessToken);
                $this->log('DEBUG', 'Loaded existing token');
            }
        }

        $this->service = new Google_Service_Calendar($this->client);
    }

    /**
     * Gets the authorization URL for the OAuth flow
     *
     * @return string
     */
    public function getAuthUrl(): string
    {
        $this->log('DEBUG', 'Generating auth URL');
        return $this->client->createAuthUrl();
    }

    /**
     * Handles the OAuth callback and saves the token
     *
     * @param string $authCode
     * @throws RuntimeException
     */
    public function handleAuthCallback(string $authCode): void
    {
        $this->log('DEBUG', 'Handling auth callback');

        try {
            $accessToken = $this->client->fetchAccessTokenWithAuthCode($authCode);

            if (isset($accessToken['error'])) {
                throw new RuntimeException('OAuth error: ' . ($accessToken['error_description'] ?? 'Unknown error'));
            }

            $this->client->setAccessToken($accessToken);

            // Save the token for future use
            if (isset($this->config['token_path'])) {
                $this->saveToken($accessToken);
            }
        } catch (\Exception $e) {
            $this->log('ERROR', 'Authentication failed: ' . $e->getMessage());
            throw new RuntimeException('Authentication failed');
        }
        $this->log('INFO', 'Authentication successful');
    }

    /**
     * Checks if the client is authenticated
     *
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        if (!$this->client->getAccessToken()) {
            $this->log('DEBUG', 'Not authenticated: No access token');
            return false;
        }

        if ($this->client->isAccessTokenExpired()) {
            $this->log('DEBUG', 'Access token expired');

            // Try to refresh the token
            if ($this->client->getRefreshToken()) {
                $this->log('DEBUG', 'Attempting to refresh token');
                try {
                    $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
                    // Save the new token
                    if (isset($this->config['token_path'])) {
                        $this->saveToken($this->client->getAccessToken());
                    }
                    $this->log('INFO', 'Token refreshed successfully');
                    return true;
                } catch (Exception $e) {
                    $this->log('ERROR', 'Failed to refresh token');
                    return false;
                }
            }

            $this->log('DEBUG', 'No refresh token available');
            return false;
        }

        $this->log('DEBUG', 'Already authenticated');
        return true;
    }

    /**
     * Set the calendar to operate on.
     *
     * @param string $calendarId The calendar ID to use
     * @return void
     */
    public function setCalendar(string $calendarId): void
    {
        $this->calendarId = $calendarId;
        $this->log('INFO', 'Calendar set', ['calendarId' => $calendarId]);
    }

    /**
     * Get a list of available calendars.
     *
     * @return array List of calendars
     */
    public function getCalendars(): array
    {
        $this->log('DEBUG', 'Getting available calendars');
        
        try {
            $calendars = [];
            $calendarList = $this->service->calendarList->listCalendarList();

            foreach ($calendarList->getItems() as $calendar) {
                $calendars[] = [
                    'id' => $calendar->getId(),
                    'summary' => $calendar->getSummary(),
                    'description' => $calendar->getDescription(),
                    'timeZone' => $calendar->getTimeZone()
                ];
            }

            $this->log('INFO', 'Retrieved calendars', ['count' => count($calendars)]);
            return $calendars;
        } catch (\Exception $e) {
            $this->log('ERROR', 'Failed to get calendars: ' . $e->getMessage());
            throw new RuntimeException('Failed to get calendars: ' . $e->getMessage());
        }
    }

    /**
     * Create a new event in the selected calendar
     *
     * @param array $eventData The event data
     * @return array The created event
     * @throws \Exception If calendar is not selected or event creation fails
     */
    public function createEvent(array $eventData): array
    {
        if (!$this->calendarId) {
            $this->log('ERROR', 'No calendar selected');
            throw new \RuntimeException('No calendar selected. Please select a calendar first.');
        }

        try {
            // Ensure required fields are present
            if (!isset($eventData['start']) || !isset($eventData['end'])) {
                $this->log('ERROR', 'Missing required fields', ['missing' => 'start/end']);
                throw new \InvalidArgumentException('Event must have start and end times');
            }

            if (!isset($eventData['summary'])) {
                $this->log('ERROR', 'Missing required fields', ['missing' => 'summary']);
                throw new \InvalidArgumentException('Event must have a summary');
            }

            $this->log('DEBUG', 'Creating event', [
                'calendarId' => $this->calendarId,
                'summary' => $eventData['summary']
            ]);

            // Create the event
            $event = new \Google_Service_Calendar_Event($eventData);
            $createdEvent = $this->service->events->insert($this->calendarId, $event);

            $this->log('INFO', 'Event created successfully', [
                'eventId' => $createdEvent->getId(),
                'summary' => $createdEvent->getSummary()
            ]);

            // Convert the response to an array
            return [
                'id' => $createdEvent->getId(),
                'summary' => $createdEvent->getSummary(),
                'description' => $createdEvent->getDescription(),
                'start' => [
                    'dateTime' => $createdEvent->getStart()->getDateTime(),
                    'timeZone' => $createdEvent->getStart()->getTimeZone()
                ],
                'end' => [
                    'dateTime' => $createdEvent->getEnd()->getDateTime(),
                    'timeZone' => $createdEvent->getEnd()->getTimeZone()
                ],
                'status' => $createdEvent->getStatus(),
                'htmlLink' => $createdEvent->getHtmlLink()
            ];
        } catch (\Exception $e) {
            $this->log('ERROR', 'Failed to create event: ' . $e->getMessage());
            throw new \RuntimeException('Failed to create event: ' . $e->getMessage());
        }
    }

    /**
     * Update an existing event.
     *
     * @param string $eventId The ID of the event to update
     * @param array $eventData The new event data
     * @return bool True if successful
     * @throws RuntimeException If no calendar is selected
     */
    public function updateEvent(string $eventId, array $eventData): bool
    {
        $this->validateCalendarId();
        $this->log('DEBUG', 'Updating event', [
            'calendarId' => $this->calendarId,
            'eventId' => $eventId
        ]);

        $event = $this->service->events->get($this->calendarId, $eventId);
        foreach ($eventData as $key => $value) {
            $setter = 'set' . ucfirst($key);
            if (method_exists($event, $setter)) {
                $event->$setter($value);
            }
        }

        $this->service->events->update($this->calendarId, $eventId, $event);
        $this->log('INFO', 'Event updated successfully', [
            'eventId' => $eventId
        ]);
        return true;
    }

    /**
     * Delete an event from the calendar.
     *
     * @param string $eventId The ID of the event to delete
     * @return bool True if successful
     * @throws RuntimeException If no calendar is selected
     */
    public function deleteEvent(string $eventId): bool
    {
        $this->validateCalendarId();
        $this->log('DEBUG', 'Deleting event', [
            'calendarId' => $this->calendarId,
            'eventId' => $eventId
        ]);

        $this->service->events->delete($this->calendarId, $eventId);
        $this->log('INFO', 'Event deleted successfully', [
            'eventId' => $eventId
        ]);
        return true;
    }

    /**
     * Get details of a specific event.
     *
     * @param string $eventId The ID of the event
     * @return array Event details
     * @throws RuntimeException If no calendar is selected
     */
    public function getEvent(string $eventId): array
    {
        $this->validateCalendarId();
        $this->log('DEBUG', 'Getting event', [
            'calendarId' => $this->calendarId,
            'eventId' => $eventId
        ]);

        $event = $this->service->events->get($this->calendarId, $eventId);

        $this->log('INFO', 'Event retrieved successfully', [
            'eventId' => $eventId
        ]);

        return [
            'id' => $event->getId(),
            'summary' => $event->getSummary(),
            'description' => $event->getDescription(),
            'start' => $event->getStart(),
            'end' => $event->getEnd(),
            'location' => $event->getLocation(),
            'creator' => $event->getCreator(),
            'created' => $event->getCreated(),
            'updated' => $event->getUpdated()
        ];
    }

    /**
     * List events in the selected calendar
     *
     * @param DateTime $startDate Start date
     * @param DateTime $endDate End date
     * @return array List of events
     * @throws RuntimeException If no calendar is selected or if the request fails
     */
    public function listEvents(\DateTime $startDate, \DateTime $endDate): array
    {
        if (!$this->calendarId) {
            $this->log('ERROR', 'No calendar selected');
            throw new \RuntimeException('No calendar selected. Please select a calendar first.');
        }

        try {
            $optParams = [
                'orderBy' => 'startTime',
                'singleEvents' => true,
                'timeMin' => $startDate->format('c'),
                'timeMax' => $endDate->format('c')
            ];

            $this->log('DEBUG', 'Listing events', [
                'calendarId' => $this->calendarId,
                'timeMin' => $optParams['timeMin'],
                'timeMax' => $optParams['timeMax']
            ]);

            $results = $this->service->events->listEvents($this->calendarId, $optParams);
            $events = [];

            foreach ($results->getItems() as $event) {
                $eventArray = [
                    'id' => $event->getId(),
                    'summary' => $event->getSummary(),
                    'description' => $event->getDescription(),
                    'start' => [
                        'dateTime' => $event->getStart()->getDateTime(),
                        'date' => $event->getStart()->getDate(),
                        'timeZone' => $event->getStart()->getTimeZone()
                    ],
                    'end' => [
                        'dateTime' => $event->getEnd()->getDateTime(),
                        'date' => $event->getEnd()->getDate(),
                        'timeZone' => $event->getEnd()->getTimeZone()
                    ],
                    'status' => $event->getStatus(),
                    'htmlLink' => $event->getHtmlLink()
                ];

                // Remove null values
                $eventArray['start'] = array_filter($eventArray['start']);
                $eventArray['end'] = array_filter($eventArray['end']);
                
                $events[] = $eventArray;
            }

            $this->log('INFO', 'Events retrieved', ['count' => count($events)]);
            return $events;
        } catch (\Exception $e) {
            $this->log('ERROR', 'Failed to list events: ' . $e->getMessage());
            throw new \RuntimeException('Failed to list events: ' . $e->getMessage());
        }
    }

    /**
     * Validate that a calendar ID is set.
     *
     * @throws RuntimeException If no calendar ID is set
     */
    private function validateCalendarId(): void
    {
        if (empty($this->calendarId)) {
            $this->log('ERROR', 'No calendar selected');
            throw new RuntimeException('No calendar selected. Call setCalendar() first.');
        }
    }

    /**
     * Validate and sanitize file path to prevent path traversal
     *
     * @param string $path The file path to validate
     * @param string $expectedSubdir Expected subdirectory (e.g., 'logs', 'token')
     * @return string Validated absolute path
     * @throws RuntimeException If path is invalid or dangerous
     */
    private function validateFilePath(string $path, string $expectedSubdir): string
    {
        // Get the absolute path
        $realPath = realpath(dirname($path));
        $fileName = basename($path);

        // If directory doesn't exist yet, use the provided path but validate it
        if ($realPath === false) {
            $realPath = dirname($path);
        }

        // Get the base directory of the application
        $baseDir = realpath(dirname(__DIR__));

        // Ensure the path is within the application directory
        if (strpos($realPath, $baseDir) !== 0) {
            throw new RuntimeException('File path must be within application directory');
        }

        // Ensure no directory traversal attempts
        if (strpos($path, '..') !== false) {
            throw new RuntimeException('Path traversal detected');
        }

        // Validate that it's in the expected subdirectory
        if (strpos($realPath, $expectedSubdir) === false) {
            throw new RuntimeException('File must be in ' . $expectedSubdir . ' directory');
        }

        return $realPath . DIRECTORY_SEPARATOR . $fileName;
    }

    /**
     * Save token to file with optional encryption
     *
     * @param array $accessToken The access token to save
     * @throws RuntimeException If save fails
     */
    private function saveToken(array $accessToken): void
    {
        $tokenPath = $this->validateFilePath($this->config['token_path'], 'token');
        $tokenData = json_encode($accessToken);

        if ($tokenData === false) {
            throw new RuntimeException('Failed to encode token');
        }

        // Encrypt token if encryption is enabled
        if ($this->tokenEncryption !== null) {
            try {
                $tokenData = $this->tokenEncryption->encrypt($tokenData);
            } catch (\Exception $e) {
                $this->log('ERROR', 'Failed to encrypt token');
                throw new RuntimeException('Failed to encrypt token');
            }
        }

        // Ensure token directory exists with secure permissions
        $tokenDir = dirname($tokenPath);
        if (!is_dir($tokenDir)) {
            if (!mkdir($tokenDir, 0750, true)) {
                throw new RuntimeException('Failed to create token directory');
            }
        }

        // Save token with restrictive permissions
        if (file_put_contents($tokenPath, $tokenData) === false) {
            throw new RuntimeException('Failed to save token');
        }

        chmod($tokenPath, 0640);
        $this->log('DEBUG', 'Token saved securely');
    }

    /**
     * Destructor to close log file handle
     */
    public function __destruct()
    {
        if ($this->logHandle) {
            fclose($this->logHandle);
        }
    }
}
