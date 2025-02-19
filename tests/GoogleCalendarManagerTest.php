<?php

namespace Tests;

use App\Services\GoogleCalendar\GoogleCalendarManager;
use Google_Client;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;
use Google_Service_Calendar_Events;
use Google_Service_Calendar_CalendarList;
use Google_Service_Calendar_CalendarListEntry;
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

        // Create mock Google client
        $this->mockClient = $this->createMock(Google_Client::class);
        $this->mockService = $this->createMock(Google_Service_Calendar::class);

        // Setup mock client expectations
        $this->mockClient->expects($this->any())
            ->method('setApplicationName')
            ->willReturn(null);
        $this->mockClient->expects($this->any())
            ->method('setScopes')
            ->willReturn(null);
        $this->mockClient->expects($this->any())
            ->method('setAuthConfig')
            ->willReturn(null);
        $this->mockClient->expects($this->any())
            ->method('setAccessType')
            ->willReturn(null);
        $this->mockClient->expects($this->any())
            ->method('setPrompt')
            ->willReturn(null);
        $this->mockClient->expects($this->any())
            ->method('isAccessTokenExpired')
            ->willReturn(false);

        // Create calendar manager with mocked dependencies
        $this->calendarManager = new GoogleCalendarManager(
            $this->credentialsPath,
            $this->tokenPath
        );

        // Inject mock service
        $reflection = new \ReflectionClass($this->calendarManager);
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
        $mockCalendarList = $this->createMock(Google_Service_Calendar_CalendarList::class);
        $mockCalendarEntry = $this->createMock(Google_Service_Calendar_CalendarListEntry::class);

        // Setup mock calendar entry
        $mockCalendarEntry->expects($this->once())
            ->method('getId')
            ->willReturn('test_calendar_id');
        $mockCalendarEntry->expects($this->once())
            ->method('getSummary')
            ->willReturn('Test Calendar');
        $mockCalendarEntry->expects($this->once())
            ->method('getDescription')
            ->willReturn('Test Description');
        $mockCalendarEntry->expects($this->once())
            ->method('getTimeZone')
            ->willReturn('Europe/Rome');

        // Setup mock calendar list
        $mockCalendarList->expects($this->once())
            ->method('getItems')
            ->willReturn([$mockCalendarEntry]);

        // Setup mock service
        $mockCalendarList = $this->createMock(Google_Service_Calendar_CalendarList::class);
        $this->mockService->calendarList = new \stdClass();
        $this->mockService->calendarList->listCalendarList = function() use ($mockCalendarList) {
            return $mockCalendarList;
        };

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
        $this->calendarManager->setCalendar('test_calendar_id');

        // Create mock event
        $mockEvent = $this->createMock(Google_Service_Calendar_Event::class);
        $mockEvent->expects($this->once())
            ->method('getId')
            ->willReturn('test_event_id');

        // Setup mock service
        $this->mockService->events = $this->createMock(\stdClass::class);
        $this->mockService->events->expects($this->once())
            ->method('insert')
            ->willReturn($mockEvent);

        // Test data
        $eventData = [
            'summary' => 'Test Event',
            'start' => ['dateTime' => '2025-02-20T10:00:00+01:00'],
            'end' => ['dateTime' => '2025-02-20T11:00:00+01:00']
        ];

        // Test createEvent method
        $eventId = $this->calendarManager->createEvent($eventData);

        // Assert result
        $this->assertEquals('test_event_id', $eventId);
    }

    public function testCreateEventWithoutCalendarId()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No calendar selected. Call setCalendar() first.');

        $eventData = [
            'summary' => 'Test Event',
            'start' => ['dateTime' => '2025-02-20T10:00:00+01:00'],
            'end' => ['dateTime' => '2025-02-20T11:00:00+01:00']
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
            'end' => ['dateTime' => '2025-02-20T11:00:00+01:00']
        ];

        $this->calendarManager->createEvent($eventData);
    }

    public function testUpdateEvent()
    {
        // Set calendar ID
        $this->calendarManager->setCalendar('test_calendar_id');

        // Create mock event
        $mockEvent = $this->createMock(Google_Service_Calendar_Event::class);
        
        // Setup mock service
        $this->mockService->events = $this->createMock(\stdClass::class);
        $this->mockService->events->expects($this->once())
            ->method('get')
            ->willReturn($mockEvent);
        $this->mockService->events->expects($this->once())
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
        $this->mockService->events = $this->createMock(\stdClass::class);
        $this->mockService->events->expects($this->once())
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

        // Create mock event
        $mockEvent = $this->createMock(Google_Service_Calendar_Event::class);
        $mockEvent->expects($this->once())->method('getId')->willReturn('test_event_id');
        $mockEvent->expects($this->once())->method('getSummary')->willReturn('Test Event');
        $mockEvent->expects($this->once())->method('getDescription')->willReturn('Test Description');
        $mockEvent->expects($this->once())->method('getStart')->willReturn(['dateTime' => '2025-02-20T10:00:00+01:00']);
        $mockEvent->expects($this->once())->method('getEnd')->willReturn(['dateTime' => '2025-02-20T11:00:00+01:00']);
        $mockEvent->expects($this->once())->method('getLocation')->willReturn('Test Location');
        $mockEvent->expects($this->once())->method('getCreator')->willReturn(['email' => 'test@example.com']);
        $mockEvent->expects($this->once())->method('getCreated')->willReturn('2025-02-19T19:22:39+01:00');
        $mockEvent->expects($this->once())->method('getUpdated')->willReturn('2025-02-19T19:22:39+01:00');

        // Setup mock service
        $this->mockService->events = $this->createMock(\stdClass::class);
        $this->mockService->events->expects($this->once())
            ->method('get')
            ->willReturn($mockEvent);

        // Test getEvent method
        $event = $this->calendarManager->getEvent('test_event_id');

        // Assert result
        $this->assertIsArray($event);
        $this->assertEquals('test_event_id', $event['id']);
        $this->assertEquals('Test Event', $event['summary']);
        $this->assertEquals('Test Description', $event['description']);
        $this->assertEquals('Test Location', $event['location']);
    }
}
