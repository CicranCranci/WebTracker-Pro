<?php 

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$conn = new mysqli("localhost", "cicrancr_cicran1", "Spike2005.", "cicrancr_visitor_info");

if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error);
    die("Connection failed: " . $conn->connect_error);
}

file_put_contents('data_received_log.txt', 'Data Received: ' . file_get_contents('php://input') . PHP_EOL, FILE_APPEND);

$data = json_decode(file_get_contents('php://input'), true);

if (is_null($data) || !isset($data['eventType'])) {
    error_log("No data received or eventType is missing");
    die("Invalid request");
}

$userId = $data['userId'];
$ipAddress = $_SERVER['REMOTE_ADDR'];
$deviceType = getDeviceType($_SERVER['HTTP_USER_AGENT']); // Determine device type

// Ensure user_id is in users table before tracking events
$stmt = $conn->prepare("INSERT IGNORE INTO users (user_id, first_visit_time, device_type, user_ip) VALUES (?, NOW(), ?, ?)");
$stmt->bind_param("sss", $userId, $deviceType, $ipAddress);
$stmt->execute();

// User and IP address handling
$stmt = $conn->prepare("INSERT INTO user_ips (user_id, ip_address) VALUES (?, ?)");
$stmt->bind_param("ss", $userId, $ipAddress);
$stmt->execute();

switch ($data['eventType']) {
    case 'pageview':
        $stmt = $conn->prepare("INSERT INTO page_views (url, user_id, timestamp) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $data['pageUrl'], $userId, $data['timestamp']);
        $stmt->execute();
        break;
    
    case 'click':
        $stmt = $conn->prepare("INSERT INTO clicks (element_clicked, page_url, user_id, timestamp) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $data['element'], $data['pageUrl'], $userId, $data['timestamp']);
        $stmt->execute();
        break;

    case 'timespent':
        $stmt = $conn->prepare("INSERT INTO time_spent (url, duration, user_id, timestamp) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("siss", $data['pageUrl'], $data['duration'], $userId, $data['timestamp']);
        $stmt->execute();
        break;

    case 'formsubmit':
        $stmt = $conn->prepare("INSERT INTO form_submissions (form_id, user_id, submission_time) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $data['formId'], $userId, $data['timestamp']);
        $stmt->execute();
        break;

    case 'sessionStart':
        $stmt = $conn->prepare("INSERT INTO user_sessions (user_id, session_start) VALUES (?, ?)");
        $stmt->bind_param("ss", $userId, $data['timestamp']);
        $stmt->execute();
        break;

    case 'sessionEnd':
        $stmt = $conn->prepare("UPDATE user_sessions SET session_end = ? WHERE user_id = ? ORDER BY session_start DESC LIMIT 1");
        $stmt->bind_param("ss", $data['timestamp'], $userId);
        $stmt->execute();
        break;

    case 'referral':
        $stmt = $conn->prepare("INSERT INTO referral_source (referral_source, user_id, timestamp) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $data['sourceUrl'], $userId, $data['timestamp']);
        $stmt->execute();
        break;
        
    case 'deviceBrowser':
        $stmt = $conn->prepare("INSERT INTO device_browser (user_id, device_type, browser, operating_system) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $userId, $data['deviceType'], $data['browser'], $data['operatingSystem']);
        $stmt->execute();
        break;
        
    case 'error':
        $stmt = $conn->prepare("INSERT INTO error_tracking (user_id, error_message, url, timestamp) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $data['userId'], $data['errorMessage'], $data['url'], $data['timestamp']);
        $stmt->execute();
        break;

    default:
        error_log("Unknown event type: " . $data['eventType']);
}

if (isset($stmt) && $stmt) {
    if (!$stmt->execute()) {
        error_log("SQL Error: " . $stmt->error);
    }
    $stmt->close();
}

function getDeviceType($userAgent) {
    if (strpos($userAgent, 'Mobile') !== false) {
        return 'Mobile';
    } else if (strpos($userAgent, 'Tablet') !== false || strpos($userAgent, 'iPad') !== false) {
        return 'Tablet';
    } else {
        return 'Desktop';
    }
}
?>








