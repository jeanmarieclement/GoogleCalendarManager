<?php

namespace Tests;

use App\Services\GoogleCalendar\GoogleCalendarManager;
use Google_Client;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;
use Google_Service_Calendar_Events;
use Google_Service_Calendar_CalendarList;
use Google_Service_Calendar_CalendarListEntry;
use Google_Service_Calendar_EventDateTime;
use Google_Service_Calendar_EventCreator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class GoogleCalendarManagerTest extends TestCase
{
    private $credentialsPath = __DIR__ . '/fixtures/credentials.json';
    private $tokenPath = __DIR__ . '/fixtures/token.json';
    private $calendarManager;
    private $mockClient;
    private $mockService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mock credentials file
        if (!file_exists(dirname($this->credentialsPath))) {
            mkdir(dirname($this->credentialsPath), 0777, true);
        }
        file_put_contents($this->credentialsPath, json_encode([
            'client_id' => 'test_client_id',
            'client_secret' => 'test_client_secret',
            'redirect_uris' => ['http://localhost']
        ]));

        // Create mock token file
        if (!file_exists(dirname($this->tokenPath))) {
            mkdir(dirname($this->tokenPath), 0777, true);
        }
        file_put_contents($this->tokenPath, json_encode([
            'access_token' => 'test_access_token',
            'refresh_token' => 'test_refresh_token'
        ]));

        // Create and configure mock client
        $this->mockClient = $this->getMockBuilder(Google_Client::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->mockClient->method('setClientId')->willReturnSelf();
        $this->mockClient->method('setClientSecret')->willReturnSelf();
        $this->mockClient->method('setRedirectUri')->willReturnSelf();
        $this->mockClient->method('setScopes')->willReturnSelf();
        $this->mockClient->method('setAccessType')->willReturnSelf();
        $this->mockClient->method('setPrompt')->willReturnSelf();
        $this->mockClient->method('setAccessToken')->willReturnSelf();
        $this->mockClient->method('getAccessToken')->willReturn(['access_token' => 'test_token']);
        $this->mockClient->method('isAccessTokenExpired')->willReturn(false);

        // Create and configure mock service
        $this->mockService = $this->getMockBuilder(Google_Service_Calendar::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Setup mock events service
        $mockEventsService = $this->getMockBuilder(\Google_Service_Calendar_Resource_Events::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $this->mockService->events = $mockEventsService;

        // Setup mock calendar list service
        $mockCalendarListService = $this->getMockBuilder(\Google_Service_Calendar_Resource_CalendarList::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $this->mockService->calendarList = $mockCalendarListService;

        // Create calendar manager with test configuration
        $config = [
            'client_id' => 'test_client_id',
            'client_secret' => 'test_client_secret',
            'redirect_uris' => ['http://localhost'],
            'token_path' => $this->tokenPath,
            'scopes' => [Google_Service_Calendar::CALENDAR],
            'redirect_uri' => 'http://localhost',
            'verify_ssl' => false
        ];

        $this->calendarManager = new GoogleCalendarManager($config);

        // Inject mock client and service
        $reflection = new \ReflectionClass($this->calendarManager);
        
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($this->calendarManager, $this->mockClient);

        $serviceProperty = $reflection->getProperty('service');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($this->calendarManager, $this->mockService);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Clean up test files
        if (file_exists($this->credentialsPath)) {
            unlink($this->credentialsPath);
        }
        if (file_exists($this->tokenPath)) {
            unlink($this->tokenPath);
        }
        if (file_exists(dirname($this->credentialsPath))) {
            rmdir(dirname($this->credentialsPath));
        }
    }

    public function testGetCalendars()
    {
        // Create mock calendar list
        $mockCalendarList = new Google_Service_Calendar_CalendarList();
        $mockCalendarEntry = new Google_Service_Calendar_CalendarListEntry();
        
        // Setup mock calendar entry data
        $mockCalendarEntry->setId('test_calendar_id');
        $mockCalendarEntry->setSummary('Test Calendar');
        $mockCalendarEntry->setDescription('Test Description');
        $mockCalendarEntry->setTimeZone('Europe/Rome');
        
        $mockCalendarList->setItems([$mockCalendarEntry]);

        // Setup mock calendar list service expectations
        $this->mockService->calendarList
            ->expects($this->once())
            ->method('listCalendarList')
            ->willReturn($mockCalendarList);

        // Test getCalendars method
        $calendars = $this->calendarManager->getCalendars();

        // Assert results
        $this->assertIsArray($calendars);
        $this->assertCount(1, $calendars);
        $this->assertEquals('test_calendar_id', $calendars[0]['id']);
        $this->assertEquals('Test Calendar', $calendars[0]['summary']);
        $this->assertEquals('Test Description', $calendars[0]['description']);
        $this->assertEquals('Europe/Rome', $calendars[0]['timeZone']);
    }

    public function testCreateEvent()
    {
        // Set calendar ID
        $calendarId = 'test_calendar_id';
        $this->calendarManager->setCalendar($calendarId);

        // Create mock event
        $mockEvent = new Google_Service_Calendar_Event();
        $mockEvent->setId('test_event_id');

        // Create event data with proper DateTime objects
        $startDateTime = new Google_Service_Calendar_EventDateTime();
        $startDateTime->setDateTime('2025-02-20T10:00:00+01:00');
        
        $endDateTime = new Google_Service_Calendar_EventDateTime();
        $endDateTime->setDateTime('2025-02-20T11:00:00+01:00');

        $eventData = [
            'summary' => 'Test Event',
            'start' => $startDateTime,
            'end' => $endDateTime
        ];

        // Setup mock events service expectations
        $this->mockService->events
            ->expects($this->once())
            ->method('insert')
            ->with(
                $this->equalTo($calendarId),
                $this->callback(function($event) {
                    return $event instanceof Google_Service_Calendar_Event;
                })
            )
            ->willReturn($mockEvent);

        // Test createEvent method
        $eventId = $this->calendarManager->createEvent($eventData);

        // Assert result
        $this->assertEquals('test_event_id', $eventId);
    }

    public function testCreateEventWithoutCalendarId()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No calendar selected. Call setCalendar() first.');

        // Reset calendar ID to null
        $reflection = new \ReflectionClass($this->calendarManager);
        $calendarIdProperty = $reflection->getProperty('calendarId');
        $calendarIdProperty->setAccessible(true);
        $calendarIdProperty->setValue($this->calendarManager, null);

        // Create event data with proper DateTime objects
        $startDateTime = new Google_Service_Calendar_EventDateTime();
        $startDateTime->setDateTime('2025-02-20T10:00:00+01:00');
        
        $endDateTime = new Google_Service_Calendar_EventDateTime();
        $endDateTime->setDateTime('2025-02-20T11:00:00+01:00');

        $eventData = [
            'summary' => 'Test Event',
            'start' => $startDateTime,
            'end' => $endDateTime
        ];

        $this->calendarManager->createEvent($eventData);
    }

    public function testCreateEventWithInvalidData()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: start');

        $this->calendarManager->setCalendar('test_calendar_id');
        $eventData = [
            'summary' => 'Test Event',
            'end' => new Google_Service_Calendar_EventDateTime()
        ];

        $this->calendarManager->createEvent($eventData);
    }

    public function testUpdateEvent()
    {
        // Set calendar ID
        $this->calendarManager->setCalendar('test_calendar_id');

        // Create mock event
        $mockEvent = new Google_Service_Calendar_Event();
        
        // Setup mock service
        $this->mockService->events
            ->expects($this->once())
            ->method('get')
            ->willReturn($mockEvent);
        $this->mockService->events
            ->expects($this->once())
            ->method('update')
            ->willReturn($mockEvent);

        // Test data
        $eventData = [
            'summary' => 'Updated Test Event'
        ];

        // Test updateEvent method
        $result = $this->calendarManager->updateEvent('test_event_id', $eventData);

        // Assert result
        $this->assertTrue($result);
    }

    public function testDeleteEvent()
    {
        // Set calendar ID
        $this->calendarManager->setCalendar('test_calendar_id');

        // Setup mock service
        $this->mockService->events
            ->expects($this->once())
            ->method('delete');

        // Test deleteEvent method
        $result = $this->calendarManager->deleteEvent('test_event_id');

        // Assert result
        $this->assertTrue($result);
    }

    public function testGetEvent()
    {
        // Set calendar ID
        $this->calendarManager->setCalendar('test_calendar_id');

        // Create mock event with all required properties
        $mockEvent = new Google_Service_Calendar_Event();
        $mockEvent->setId('test_event_id');
        $mockEvent->setSummary('Test Event');
        $mockEvent->setDescription('Test Description');

        // Set start and end times
        $startDateTime = new Google_Service_Calendar_EventDateTime();
        $startDateTime->setDateTime('2025-02-20T10:00:00+01:00');
        $mockEvent->setStart($startDateTime);

        $endDateTime = new Google_Service_Calendar_EventDateTime();
        $endDateTime->setDateTime('2025-02-20T11:00:00+01:00');
        $mockEvent->setEnd($endDateTime);

        $mockEvent->setLocation('Test Location');

        // Create and set creator
        $creator = new Google_Service_Calendar_EventCreator();
        $creator->setEmail('test@example.com');
        $mockEvent->setCreator($creator);

        $mockEvent->setCreated('2025-02-19T19:22:39+01:00');
        $mockEvent->setUpdated('2025-02-19T19:22:39+01:00');

        // Setup mock service
        $this->mockService->events
            ->expects($this->once())
            ->method('get')
            ->willReturn($mockEvent);

        // Test getEvent method
        $event = $this->calendarManager->getEvent('test_event_id');

        // Assert event properties
        $this->assertEquals('test_event_id', $event['id']);
        $this->assertEquals('Test Event', $event['summary']);
        $this->assertEquals('Test Description', $event['description']);
        $this->assertEquals('2025-02-20T10:00:00+01:00', $event['start']['dateTime']);
        $this->assertEquals('2025-02-20T11:00:00+01:00', $event['end']['dateTime']);
        $this->assertEquals('Test Location', $event['location']);
        $this->assertEquals('test@example.com', $event['creator']['email']);
        $this->assertEquals('2025-02-19T19:22:39+01:00', $event['created']);
        $this->assertEquals('2025-02-19T19:22:39+01:00', $event['updated']);
    }
}
