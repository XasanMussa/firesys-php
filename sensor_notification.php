<?php
// Database connection settings
$servername = "nozomi.proxy.rlwy.net";
$username = "root";
$password = "tARaNwlbYssfcMNUzJCzcStbDxsPNNrM";
$dbname = "railway";
$DB_PORT = 16930;

// Create a new MySQL connection
$conn = new mysqli($servername, $username, $password, $dbname, $DB_PORT);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function broadcast_sensor_data() {
    // Create a new MySQL connection for fresh data
    $servername = "nozomi.proxy.rlwy.net";
    $username = "root";
    $password = "tARaNwlbYssfcMNUzJCzcStbDxsPNNrM";
    $dbname = "railway";
    $DB_PORT = 16930;
    $conn = new mysqli($servername, $username, $password, $dbname, $DB_PORT);

    $logs = [];
    $logSql = "SELECT * FROM sensor_logs";
    $logResult = $conn->query($logSql);
    if ($logResult && $logResult->num_rows > 0) {
        while ($log = $logResult->fetch_assoc()) {
            $logs[] = $log;
        }
    }
    $sensor_data = count($logs) > 0 ? $logs[count($logs) - 1] : [];
    $msg = json_encode([
        'type' => 'sensor_update',
        'sensor_data' => $sensor_data,
        'logs' => array_reverse($logs)
    ]);
    
    // Hardcoded WebSocket server details
    $ws_host = 'ws://fire-backend-production.up.railway.app';
    $ws_port = 8080;
    
    error_log("Attempting to broadcast sensor data to WebSocket server at $ws_host:$ws_port");
    
    // Set a shorter timeout to avoid hanging
    $timeout = 1;
    $fp = @fsockopen($ws_host, $ws_port, $errno, $errstr, $timeout);
    if ($fp) {
        fwrite($fp, $msg . "\n");
        fclose($fp);
        error_log("Successfully broadcast sensor data");
    } else {
        error_log("Failed to connect to WebSocket server at $ws_host:$ws_port: $errstr ($errno)");
        // Continue execution even if WebSocket fails - don't let it break the API
    }
    $conn->close();
}

// Handle sensor data upload (upload_sensor endpoint)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_GET['endpoint']) && $_GET['endpoint'] == 'upload_sensor') {
    // Validate all fields are present and are 0 or 1
    $fields = ['fire_detected', 'gas_detected', 'emergency_triggered', 'pump_status'];
    $values = [];
    foreach ($fields as $field) {
        if (!isset($_POST[$field]) || !in_array($_POST[$field], ['0', '1', 0, 1], true)) {
            echo json_encode(["status" => "error", "message" => "Missing or invalid value for $field"]);
            exit();
        }
        $values[] = intval($_POST[$field]);
    }
    list($fire_detected, $gas_detected, $emergency_triggered, $pump_status) = $values;
    $stmt = $conn->prepare("INSERT INTO sensor_logs (fire_detected, gas_detected, emergency_triggered, pump_status) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiii", $fire_detected, $gas_detected, $emergency_triggered, $pump_status);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Sensor data uploaded successfully"]);
        broadcast_sensor_data();
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to upload sensor data"]);
    }

    $stmt->close();
    exit();
}

function broadcast_notifications() {
    // Fetch all notifications
    global $conn;
    $notifications = [];
    $result = $conn->query("SELECT * FROM notifications ORDER BY id DESC");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
    }
    $msg = json_encode([
        'type' => 'notification_update',
        'notifications' => $notifications
    ]);
    
    // Hardcoded WebSocket server details
    $ws_host = 'fire-backend-production.up.railway.app';
    $ws_port = 8080;
    
    error_log("Attempting to broadcast notifications to WebSocket server at $ws_host:$ws_port");
    
    // Set a shorter timeout to avoid hanging
    $timeout = 1;
    $fp = @fsockopen($ws_host, $ws_port, $errno, $errstr, $timeout);
    if ($fp) {
        fwrite($fp, $msg . "\n");
        fclose($fp);
        error_log("Successfully broadcast notifications");
    } else {
        error_log("Failed to connect to WebSocket server at $ws_host:$ws_port: $errstr ($errno)");
        // Continue execution even if WebSocket fails - don't let it break the API
    }
}

// Handle notification addition (add_notification endpoint)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_GET['endpoint']) && $_GET['endpoint'] == 'add_notification') {
    $message = isset($_POST['message']) ? $_POST['message'] : '';

    if (!empty($message)) {
        $stmt = $conn->prepare("INSERT INTO notifications (message) VALUES (?)");
        $stmt->bind_param("s", $message);

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Notification added successfully"]);
            broadcast_notifications();
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to add notification"]);
        }

        $stmt->close();
    } else {
        echo json_encode(["status" => "error", "message" => "Notification message cannot be empty"]);
    }

    exit();
}

// Handle fetch data (fetch endpoint)
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['endpoint']) && $_GET['endpoint'] == 'fetch') {
    $sensor_data = [];
    $notification_data = [];

    // Fetch latest sensor data
    $sensorSql = "SELECT * FROM sensor_logs ORDER BY timestamp DESC LIMIT 1";
    $sensorResult = $conn->query($sensorSql);
    if ($sensorResult->num_rows > 0) {
        while ($row = $sensorResult->fetch_assoc()) {
            $sensor_data[] = $row;
        }
    }

    // Fetch latest notifications
    $notificationSql = "SELECT * FROM notifications ORDER BY id DESC LIMIT 10";
    $notificationResult = $conn->query($notificationSql);
    if ($notificationResult->num_rows > 0) {
        while ($row = $notificationResult->fetch_assoc()) {
            $notification_data[] = $row;
        }
    }

    echo json_encode([
        "sensor_data" => $sensor_data,
        "notifications" => $notification_data
    ]);
    exit();
}

// Handle deleting notifications (delete_notification endpoint)
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['endpoint']) && $_GET['endpoint'] == 'delete_notification' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
        broadcast_notifications();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Delete failed']);
    }
    $stmt->close();
    $conn->close();
    exit();
}

// Close the database connection
$conn->close();
?>