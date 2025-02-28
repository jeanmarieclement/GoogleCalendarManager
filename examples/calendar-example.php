<?php
// Prevent any output before headers
ob_start();

// Start session before qualsiasi output
session_start();

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\GoogleCalendar\GoogleCalendarManager;

// Load configuration
$config = require __DIR__ . '/../config/calendar-config.php';

// Function to safely format event date
function formatEventDate($event, $type = 'start') {
    if (!isset($event[$type])) {
        return 'N/A';
    }

    if (isset($event[$type]['dateTime'])) {
        $date = new DateTime($event[$type]['dateTime']);
        return $date->format('Y-m-d H:i');
    } 
    
    if (isset($event[$type]['date'])) {
        $date = new DateTime($event[$type]['date']);
        return $date->format('Y-m-d');
    }
    
    return 'N/A';
}

try {
    // Initialize the Calendar Manager
    $calendarManager = new GoogleCalendarManager($config);

    // Check if we're handling an OAuth callback
    if (isset($_GET['code'])) {
        // Handle the callback and save the token
        $calendarManager->handleAuthCallback($_GET['code']);
        $_SESSION['message'] = "Authentication successful!";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    // Check if we need authentication
    if (!$calendarManager->isAuthenticated()) {
        // Redirect to Google's auth page
        $authUrl = $calendarManager->getAuthUrl();
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Google Calendar Manager</title>
            <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
        </head>
        <body class='container mt-5'>
            <div class='card'>
                <div class='card-body text-center'>
                    <h2>Authentication Required</h2>
                    <p>Please authenticate with Google Calendar to continue.</p>
                    <a href='{$authUrl}' class='btn btn-primary'>Authenticate with Google</a>
                </div>
            </div>
        </body>
        </html>";
        exit;
    }

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'select_calendar':
                    $calendarManager->setCalendar($_POST['calendar_id']);
                    $_SESSION['selected_calendar'] = $_POST['calendar_id'];
                    $_SESSION['message'] = "Calendar selected successfully!";
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;

                case 'add_event':
                    if (!isset($_SESSION['selected_calendar'])) {
                        throw new RuntimeException('Please select a calendar first.');
                    }

                    // Validate and format dates
                    $startTime = new DateTime($_POST['event_start']);
                    $endTime = new DateTime($_POST['event_end']);

                    // Create event datetime objects
                    $startDateTime = new \Google_Service_Calendar_EventDateTime();
                    $startDateTime->setDateTime($startTime->format('c')); // Use ISO 8601 format
                    $startDateTime->setTimeZone($startTime->getTimezone()->getName());
                    
                    $endDateTime = new \Google_Service_Calendar_EventDateTime();
                    $endDateTime->setDateTime($endTime->format('c')); // Use ISO 8601 format
                    $endDateTime->setTimeZone($endTime->getTimezone()->getName());

                    // Create event data
                    $eventData = [
                        'summary' => trim($_POST['event_title']),
                        'description' => trim($_POST['event_description'] ?? ''),
                        'start' => ['dateTime' => $startDateTime->getDateTime(), 'timeZone' => $startDateTime->getTimeZone()],
                        'end' => ['dateTime' => $endDateTime->getDateTime(), 'timeZone' => $endDateTime->getTimeZone()],
                    ];

                    // Validate event data
                    if (empty($eventData['summary'])) {
                        throw new RuntimeException('Event title is required.');
                    }

                    if ($startTime > $endTime) {
                        throw new RuntimeException('End time must be after start time.');
                    }

                    $calendarManager->createEvent($eventData);
                    $_SESSION['message'] = "Event created successfully!";
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;

                case 'delete_event':
                    if (!isset($_SESSION['selected_calendar'])) {
                        throw new RuntimeException('Please select a calendar first.');
                    }
                    $calendarManager->deleteEvent($_POST['event_id']);
                    $_SESSION['message'] = "Event deleted successfully!";
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
            }
        }
    }

    // Get available calendars
    $calendars = $calendarManager->getCalendars();
    
    // Get selected calendar ID
    $selectedCalendarId = $_SESSION['selected_calendar'] ?? null;
    if ($selectedCalendarId) {
        $calendarManager->setCalendar($selectedCalendarId);
    }

    // Get date range from query parameters or use defaults
    $startDate = isset($_GET['start_date']) ? new DateTime($_GET['start_date']) : new DateTime('now');
    $endDate = isset($_GET['end_date']) ? new DateTime($_GET['end_date']) : (new DateTime('now'))->modify('+1 month');

    // Get events if calendar is selected
    $events = [];
    if ($selectedCalendarId) {
        $events = $calendarManager->listEvents($startDate, $endDate);
    }

    // Output the interface
    ?>
<!DOCTYPE html>
<html>
<head>
    <title>Google Calendar Manager</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css' rel='stylesheet'>
</head>
<body class='container mt-4'>
    <?php if (isset($_SESSION['message'])): ?>
        <div class='alert alert-success'>
            <?php 
            echo htmlspecialchars($_SESSION['message']);
            unset($_SESSION['message']);
            ?>
        </div>
    <?php endif; ?>

    <!-- Calendar Selection -->
    <div class='card mb-4'>
        <div class='card-header'>
            <h3>Select Calendar</h3>
        </div>
        <div class='card-body'>
            <form method='post' class='row g-3'>
                <input type='hidden' name='action' value='select_calendar'>
                <div class='col-md-8'>
                    <select name='calendar_id' class='form-select' required>
                        <option value=''>Choose a calendar...</option>
                        <?php foreach ($calendars as $calendar): ?>
                            <option value='<?php echo htmlspecialchars($calendar['id']); ?>'
                                    <?php echo $selectedCalendarId === $calendar['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($calendar['summary']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class='col-md-4'>
                    <button type='submit' class='btn btn-primary'>Select Calendar</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selectedCalendarId): ?>
        <!-- Date Range Selection -->
        <div class='card mb-4'>
            <div class='card-header'>
                <h3>Select Date Range</h3>
            </div>
            <div class='card-body'>
                <form method='get' class='row g-3'>
                    <div class='col-md-5'>
                        <input type='text' name='start_date' class='form-control datepicker' 
                               value='<?php echo htmlspecialchars($startDate->format('Y-m-d\TH:i')); ?>' 
                               placeholder='Start Date'>
                    </div>
                    <div class='col-md-5'>
                        <input type='text' name='end_date' class='form-control datepicker' 
                               value='<?php echo htmlspecialchars($endDate->format('Y-m-d\TH:i')); ?>' 
                               placeholder='End Date'>
                    </div>
                    <div class='col-md-2'>
                        <button type='submit' class='btn btn-primary'>Update Range</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add New Event -->
        <div class='card mb-4'>
            <div class='card-header'>
                <h3>Add New Event</h3>
            </div>
            <div class='card-body'>
                <form method='post' class='row g-3'>
                    <input type='hidden' name='action' value='add_event'>
                    <div class='col-md-6'>
                        <input type='text' name='event_title' class='form-control' placeholder='Event Title' required>
                    </div>
                    <div class='col-md-6'>
                        <textarea name='event_description' class='form-control' placeholder='Event Description'></textarea>
                    </div>
                    <div class='col-md-5'>
                        <input type='text' name='event_start' class='form-control datepicker' placeholder='Start Time' required>
                    </div>
                    <div class='col-md-5'>
                        <input type='text' name='event_end' class='form-control datepicker' placeholder='End Time' required>
                    </div>
                    <div class='col-md-2'>
                        <button type='submit' class='btn btn-success'>Add Event</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Events List -->
        <div class='card'>
            <div class='card-header'>
                <h3>Events</h3>
            </div>
            <div class='card-body'>
                <?php if (empty($events)): ?>
                    <p class='text-muted'>No events found in the selected date range.</p>
                <?php else: ?>
                    <div class='table-responsive'>
                        <table class='table'>
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Description</th>
                                    <th>Start</th>
                                    <th>End</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($events as $event): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($event['summary'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($event['description'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars(formatEventDate($event, 'start')); ?></td>
                                        <td><?php echo htmlspecialchars(formatEventDate($event, 'end')); ?></td>
                                        <td>
                                            <form method='post' style='display: inline;'>
                                                <input type='hidden' name='action' value='delete_event'>
                                                <input type='hidden' name='event_id' value='<?php echo htmlspecialchars($event['id']); ?>'>
                                                <button type='submit' class='btn btn-danger btn-sm' 
                                                        onclick="return confirm('Are you sure you want to delete this event?')">
                                                    Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <script src='https://cdn.jsdelivr.net/npm/flatpickr'></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            flatpickr('.datepicker', {
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                time_24hr: true
            });
        });
    </script>
</body>
</html>
<?php
} catch (Exception $e) {
    $_SESSION['message'] = "Error: " . $e->getMessage();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Flush output buffer
ob_end_flush();
?>
