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
 */
class GoogleCalendarManager
{
    /** @var Google_Client */
    private $client;

    /** @var Google_Service_Calendar */
    private $service;

    /** @var string|null */
    private $calendarId;

    /** @var string[] */
    private const SCOPES = [
        Google_Service_Calendar::CALENDAR,
        Google_Service_Calendar::CALENDAR_EVENTS
    ];

    /**
     * GoogleCalendarManager constructor.
     *
     * @param string $credentialsPath Path to the credentials.json file
     * @param string $tokenPath Path where the token will be stored
     * @param string|null $defaultCalendarId Optional default calendar ID
     * @throws RuntimeException If credentials file doesn't exist or is invalid
     */
    public function __construct(
        string $credentialsPath,
        string $tokenPath,
        ?string $defaultCalendarId = null
    ) {
        if (!file_exists($credentialsPath)) {
            throw new RuntimeException('Credentials file not found');
        }

        $this->client = new Google_Client();
        $this->client->setApplicationName('Google Calendar Manager');
        $this->client->setScopes(self::SCOPES);
        $this->client->setAuthConfig($credentialsPath);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('select_account consent');

        // Load previously authorized token from a file, if it exists
        if (file_exists($tokenPath)) {
            $accessToken = json_decode(file_get_contents($tokenPath), true);
            $this->client->setAccessToken($accessToken);
        }

        // If there is no previous token or it's expired
        if ($this->client->isAccessTokenExpired()) {
            // Refresh the token if possible, otherwise fetch a new one
            if ($this->client->getRefreshToken()) {
                $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
            } else {
                throw new RuntimeException('No valid access token found. Authentication required.');
            }

            // Save the token to a file
            if (!file_exists(dirname($tokenPath))) {
                mkdir(dirname($tokenPath), 0700, true);
            }
            file_put_contents($tokenPath, json_encode($this->client->getAccessToken()));
        }

        $this->service = new Google_Service_Calendar($this->client);
        $this->calendarId = $defaultCalendarId;
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
     * @param string|null $timeMin Start time (RFC3339 timestamp)
     * @param string|null $timeMax End time (RFC3339 timestamp)
     * @param int $maxResults Maximum number of events to return
     * @return array List of events
     * @throws RuntimeException If no calendar is selected
     */
    public function listEvents(?string $timeMin = null, ?string $timeMax = null, int $maxResults = 10): array
    {
        $this->validateCalendarId();

        $optParams = [
            'maxResults' => $maxResults,
            'orderBy' => 'startTime',
            'singleEvents' => true
        ];

        if ($timeMin) {
            $optParams['timeMin'] = $timeMin;
        }
        if ($timeMax) {
            $optParams['timeMax'] = $timeMax;
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
