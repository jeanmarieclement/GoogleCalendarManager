<?php
// Prevent any output before headers
ob_start();

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\GoogleCalendar\GoogleCalendarManager;
use App\Services\GoogleCalendar\SessionSecurity;
use App\Services\GoogleCalendar\CSRFProtection;

// Start secure session
SessionSecurity::startSecureSession(false); // Set to true in production with HTTPS

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' cdn.jsdelivr.net; font-src 'self' cdn.jsdelivr.net; img-src 'self' data:;");

// Load configuration
$config = require __DIR__ . '/../config/calendar-config.php';

// Safe redirect function
function safeRedirect() {
    header('Location: ' . basename($_SERVER['PHP_SELF']));
    exit;
}

// Function to sanitize output
function sanitizeOutput($data) {
    if (is_array($data)) {
        return array_map('sanitizeOutput', $data);
    }
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

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
        // Validate state parameter to prevent CSRF
        if (!isset($_GET['state']) || !isset($_SESSION['oauth_state']) ||
            $_GET['state'] !== $_SESSION['oauth_state']) {
            throw new RuntimeException('Invalid OAuth state - possible CSRF attack');
        }

        // Clean up state
        unset($_SESSION['oauth_state']);

        // Handle the callback and save the token
        $calendarManager->handleAuthCallback($_GET['code']);
        $_SESSION['message'] = "Authentication successful!";
        $_SESSION['message_type'] = "success";
        safeRedirect();
    }

    // Check if we need authentication
    if (!$calendarManager->isAuthenticated()) {
        // Generate state parameter for OAuth CSRF protection
        $_SESSION['oauth_state'] = bin2hex(random_bytes(16));

        // Redirect to Google's auth page
        $authUrl = $calendarManager->getAuthUrl();

        // Add state parameter to auth URL
        $authUrl .= '&state=' . urlencode($_SESSION['oauth_state']);

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
        // Verify CSRF token for all POST requests
        try {
            CSRFProtection::verifyPostToken();
        } catch (RuntimeException $e) {
            throw new RuntimeException('Security validation failed. Please refresh the page and try again.');
        }

        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'select_calendar':
                    // Validate calendar_id
                    if (!isset($_POST['calendar_id']) || empty(trim($_POST['calendar_id']))) {
                        throw new RuntimeException('Invalid calendar selection');
                    }

                    $calendarId = trim($_POST['calendar_id']);
                    $calendarManager->setCalendar($calendarId);
                    $_SESSION['selected_calendar'] = $calendarId;
                    $_SESSION['message'] = "Calendar selected successfully!";
                    $_SESSION['message_type'] = "success";
                    safeRedirect();

                case 'add_event':
                    if (!isset($_SESSION['selected_calendar'])) {
                        throw new RuntimeException('Please select a calendar first.');
                    }

                    // Validate required fields
                    $requiredFields = ['event_title', 'event_start', 'event_end'];
                    foreach ($requiredFields as $field) {
                        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
                            throw new RuntimeException('All event fields are required');
                        }
                    }

                    // Sanitize and validate title length
                    $eventTitle = trim($_POST['event_title']);
                    if (strlen($eventTitle) > 255) {
                        throw new RuntimeException('Event title is too long (max 255 characters)');
                    }

                    // Sanitize description
                    $eventDescription = isset($_POST['event_description']) ? trim($_POST['event_description']) : '';
                    if (strlen($eventDescription) > 5000) {
                        throw new RuntimeException('Event description is too long (max 5000 characters)');
                    }

                    // Validate and format dates
                    try {
                        $startTime = new DateTime($_POST['event_start']);
                        $endTime = new DateTime($_POST['event_end']);
                    } catch (Exception $e) {
                        throw new RuntimeException('Invalid date format');
                    }

                    // Validate date logic
                    if ($startTime >= $endTime) {
                        throw new RuntimeException('End time must be after start time');
                    }

                    // Check if event is not too far in the future (10 years max)
                    $maxDate = new DateTime('+10 years');
                    if ($startTime > $maxDate) {
                        throw new RuntimeException('Event date is too far in the future');
                    }

                    // Create event datetime objects
                    $startDateTime = new \Google_Service_Calendar_EventDateTime();
                    $startDateTime->setDateTime($startTime->format('c'));
                    $startDateTime->setTimeZone($startTime->getTimezone()->getName());

                    $endDateTime = new \Google_Service_Calendar_EventDateTime();
                    $endDateTime->setDateTime($endTime->format('c'));
                    $endDateTime->setTimeZone($endTime->getTimezone()->getName());

                    // Create event data
                    $eventData = [
                        'summary' => $eventTitle,
                        'description' => $eventDescription,
                        'start' => ['dateTime' => $startDateTime->getDateTime(), 'timeZone' => $startDateTime->getTimeZone()],
                        'end' => ['dateTime' => $endDateTime->getDateTime(), 'timeZone' => $endDateTime->getTimeZone()],
                    ];

                    $calendarManager->createEvent($eventData);
                    $_SESSION['message'] = "Event created successfully!";
                    $_SESSION['message_type'] = "success";
                    safeRedirect();

                case 'delete_event':
                    if (!isset($_SESSION['selected_calendar'])) {
                        throw new RuntimeException('Please select a calendar first.');
                    }

                    // Validate event_id
                    if (!isset($_POST['event_id']) || empty(trim($_POST['event_id']))) {
                        throw new RuntimeException('Invalid event ID');
                    }

                    $eventId = trim($_POST['event_id']);

                    // Validate event_id format (basic check)
                    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $eventId)) {
                        throw new RuntimeException('Invalid event ID format');
                    }

                    $calendarManager->deleteEvent($eventId);
                    $_SESSION['message'] = "Event deleted successfully!";
                    $_SESSION['message_type'] = "success";
                    safeRedirect();
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
        <?php
        $messageType = isset($_SESSION['message_type']) ? $_SESSION['message_type'] : 'info';
        $alertClass = 'alert-' . sanitizeOutput($messageType);
        ?>
        <div class='alert <?php echo $alertClass; ?> alert-dismissible fade show' role='alert'>
            <?php
            echo sanitizeOutput($_SESSION['message']);
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
            ?>
            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
        </div>
    <?php endif; ?>

    <!-- Calendar Selection -->
    <div class='card mb-4'>
        <div class='card-header'>
            <h3>Select Calendar</h3>
        </div>
        <div class='card-body'>
            <form method='post' class='row g-3'>
                <?php echo CSRFProtection::getTokenField(); ?>
                <input type='hidden' name='action' value='select_calendar'>
                <div class='col-md-8'>
                    <select name='calendar_id' class='form-select' required>
                        <option value=''>Choose a calendar...</option>
                        <?php foreach ($calendars as $calendar): ?>
                            <option value='<?php echo sanitizeOutput($calendar['id']); ?>'
                                    <?php echo $selectedCalendarId === $calendar['id'] ? 'selected' : ''; ?>>
                                <?php echo sanitizeOutput($calendar['summary']); ?>
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
                               value='<?php echo sanitizeOutput($startDate->format('Y-m-d\TH:i')); ?>'
                               placeholder='Start Date' readonly>
                    </div>
                    <div class='col-md-5'>
                        <input type='text' name='end_date' class='form-control datepicker'
                               value='<?php echo sanitizeOutput($endDate->format('Y-m-d\TH:i')); ?>'
                               placeholder='End Date' readonly>
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
                    <?php echo CSRFProtection::getTokenField(); ?>
                    <input type='hidden' name='action' value='add_event'>
                    <div class='col-md-6'>
                        <input type='text' name='event_title' class='form-control' placeholder='Event Title' required maxlength='255'>
                    </div>
                    <div class='col-md-6'>
                        <textarea name='event_description' class='form-control' placeholder='Event Description' maxlength='5000'></textarea>
                    </div>
                    <div class='col-md-5'>
                        <input type='text' name='event_start' class='form-control datepicker' placeholder='Start Time' required readonly>
                    </div>
                    <div class='col-md-5'>
                        <input type='text' name='event_end' class='form-control datepicker' placeholder='End Time' required readonly>
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
                                        <td><?php echo sanitizeOutput($event['summary'] ?? ''); ?></td>
                                        <td><?php echo sanitizeOutput($event['description'] ?? ''); ?></td>
                                        <td><?php echo sanitizeOutput(formatEventDate($event, 'start')); ?></td>
                                        <td><?php echo sanitizeOutput(formatEventDate($event, 'end')); ?></td>
                                        <td>
                                            <form method='post' style='display: inline;' onsubmit="return confirm('Are you sure you want to delete this event?');">
                                                <?php echo CSRFProtection::getTokenField(); ?>
                                                <input type='hidden' name='action' value='delete_event'>
                                                <input type='hidden' name='event_id' value='<?php echo sanitizeOutput($event['id']); ?>'>
                                                <button type='submit' class='btn btn-danger btn-sm'>
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

    <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js'></script>
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
    // Log the detailed error server-side
    error_log('Calendar Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    // Show generic error to user
    $_SESSION['message'] = "An error occurred. Please try again or contact support if the problem persists.";
    $_SESSION['message_type'] = "danger";

    // In development, show detailed error (remove in production)
    if (isset($config['debug']) && $config['debug'] === true) {
        $_SESSION['message'] .= " (Debug: " . $e->getMessage() . ")";
    }

    safeRedirect();
}

// Flush output buffer
ob_end_flush();
?>
