<?php

declare(strict_types=1);

namespace App\Services\GoogleCalendar;

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
        $this->initializeClient();
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

        // Try to load existing token
        if (isset($this->config['token_path']) && file_exists($this->config['token_path'])) {
            $accessToken = json_decode(file_get_contents($this->config['token_path']), true);
            $this->client->setAccessToken($accessToken);
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
        try {
            $accessToken = $this->client->fetchAccessTokenWithAuthCode($authCode);
            $this->client->setAccessToken($accessToken);

            // Save the token for future use
            if (isset($this->config['token_path'])) {
                if (!file_put_contents($this->config['token_path'], json_encode($accessToken))) {
                    throw new RuntimeException('Failed to save the token');
                }
            }
        } catch (\Exception $e) {
            throw new RuntimeException('Error handling auth callback: ' . $e->getMessage());
        }
    }

    /**
     * Checks if the client is authenticated
     *
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        return $this->client->getAccessToken() !== null;
    }

    /**
     * Refreshes the access token if it's expired
     *
     * @throws RuntimeException
     */
    private function refreshTokenIfNeeded(): void
    {
        if ($this->client->isAccessTokenExpired()) {
            if ($this->client->getRefreshToken()) {
                $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
                
                // Save the new token
                if (isset($this->config['token_path'])) {
                    file_put_contents($this->config['token_path'], json_encode($this->client->getAccessToken()));
                }
            } else {
                throw new RuntimeException('Refresh token not available. Re-authentication required.');
            }
        }
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
    }

    /**
     * Get a list of available calendars.
     *
     * @return array List of calendars
     */
    public function getCalendars(): array
    {
        $this->refreshTokenIfNeeded();
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

        return $calendars;
    }

    /**
     * Create a new event in the calendar.
     *
     * @param array $eventData Event data
     * @return string The created event ID
     * @throws InvalidArgumentException If required fields are missing
     * @throws RuntimeException If no calendar is selected
     */
    public function createEvent(array $eventData): string
    {
        $this->validateCalendarId();
        $this->validateEventData($eventData);
        $this->refreshTokenIfNeeded();

        $event = new Google_Service_Calendar_Event($eventData);
        $createdEvent = $this->service->events->insert($this->calendarId, $event);

        return $createdEvent->getId();
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
        $this->refreshTokenIfNeeded();

        $event = $this->service->events->get($this->calendarId, $eventId);
        foreach ($eventData as $key => $value) {
            $setter = 'set' . ucfirst($key);
            if (method_exists($event, $setter)) {
                $event->$setter($value);
            }
        }

        $this->service->events->update($this->calendarId, $eventId, $event);
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
        $this->refreshTokenIfNeeded();
        $this->service->events->delete($this->calendarId, $eventId);
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
        $this->refreshTokenIfNeeded();
        $event = $this->service->events->get($this->calendarId, $eventId);

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
     * List events in the calendar.
     *
     * @param string|\DateTime|null $timeMin Start time (RFC3339 timestamp or DateTime object)
     * @param string|\DateTime|null $timeMax End time (RFC3339 timestamp or DateTime object)
     * @param int $maxResults Maximum number of events to return
     * @return array List of events
     * @throws RuntimeException If no calendar is selected
     */
    public function listEvents($timeMin = null, $timeMax = null, int $maxResults = 10): array
    {
        $this->validateCalendarId();
        $this->refreshTokenIfNeeded();

        $optParams = [
            'maxResults' => $maxResults,
            'orderBy' => 'startTime',
            'singleEvents' => true
        ];

        if ($timeMin) {
            $optParams['timeMin'] = $timeMin instanceof \DateTime 
                ? $timeMin->format('c') // RFC3339 format
                : $timeMin;
        }
        if ($timeMax) {
            $optParams['timeMax'] = $timeMax instanceof \DateTime 
                ? $timeMax->format('c') // RFC3339 format
                : $timeMax;
        }

        $results = $this->service->events->listEvents($this->calendarId, $optParams);
        $events = [];

        foreach ($results->getItems() as $event) {
            $events[] = [
                'id' => $event->getId(),
                'summary' => $event->getSummary(),
                'start' => $event->getStart()->getDateTime(),
                'end' => $event->getEnd()->getDateTime()
            ];
        }

        return $events;
    }

    /**
     * Validate that a calendar ID is set.
     *
     * @throws RuntimeException If no calendar ID is set
     */
    private function validateCalendarId(): void
    {
        if (empty($this->calendarId)) {
            throw new RuntimeException('No calendar selected. Call setCalendar() first.');
        }
    }

    /**
     * Validate event data contains required fields.
     *
     * @param array $eventData The event data to validate
     * @throws InvalidArgumentException If required fields are missing
     */
    private function validateEventData(array $eventData): void
    {
        $requiredFields = ['summary', 'start', 'end'];
        foreach ($requiredFields as $field) {
            if (!isset($eventData[$field])) {
                throw new InvalidArgumentException("Missing required field: {$field}");
            }
        }
    }
}
