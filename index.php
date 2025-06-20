<?php
$servername = "nozomi.proxy.rlwy.net";
$username = "root";
$password = "tARaNwlbYssfcMNUzJCzcStbDxsPNNrM";
$dbname = "railway";
$DB_PORT=16930;
// Create connection
$conn = new mysqli($servername, $username, $password, $dbname,$DB_PORT);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Fetch latest sensor data
$fire = $gas = $emergency = $pump = "Off";  // Initialize variables to default values

$sql = "SELECT * FROM sensor_logs";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $fire = $row["fire_detected"] ? "On" : "Off";
    $gas = $row["gas_detected"] ? "On" : "Off";
    $emergency = $row["emergency_triggered"] ? "On" : "Off";
    $pump = $row["pump_status"] ? "On" : "Off";
}

// Get notification list
$notifications = [];
$noteSql = "SELECT * FROM notifications ORDER BY id DESC";
$noteResult = $conn->query($noteSql);
if ($noteResult->num_rows > 0) {
    while ($row = $noteResult->fetch_assoc()) {
        $notifications[] = $row;
    }
}

// Get log history
$logs = [];
$logSql = "SELECT * FROM sensor_logs";
$logResult = $conn->query($logSql);
if ($logResult->num_rows > 0) {
    while ($log = $logResult->fetch_assoc()) {
        $logs[] = $log;
    }
}

// Use the last log entry for dashboard cards
if (count($logs) > 0) {
    $lastLog = $logs[count($logs) - 1];
    $fire = $lastLog["fire_detected"] ? "On" : "Off";
    $gas = $lastLog["gas_detected"] ? "On" : "Off";
    $emergency = $lastLog["emergency_triggered"] ? "On" : "Off";
    $pump = $lastLog["pump_status"] ? "On" : "Off";
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Panel</title>
    <style>
        body { font-family: Arial; margin: 0; background: #f8fcff; }
        .header { background: #1e1f3d; color: white; padding: 15px 20px; font-size: 24px; display: flex; justify-content: space-between; align-items: center; }
        .dashboard { display: flex; flex-wrap: wrap; justify-content: center; gap: 30px; padding: 30px; }
        .card { background: white; width: 200px; text-align: center; padding: 20px; border-radius: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .card div { font-size: 40px; }
        .on { color: red; font-weight: bold; }
        .off { color: gray; font-weight: bold; }
        .table-section { margin: 30px auto; width: 90%; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: center; }
        th { background-color: #f4f4f4; }

        .notification-icon { cursor: pointer; font-size: 20px; }
        .sidebar { position: fixed; top: 0; right: -100%; width: 400px; height: 100%; background: white; box-shadow: -2px 0 5px rgba(0,0,0,0.3); overflow-y: auto; transition: right 0.3s ease; z-index: 1000; padding: 20px; }
        .sidebar.show { right: 0; }
        .notif-card { background: #f1f1f1; padding: 10px; border-radius: 10px; margin-bottom: 10px; position: relative; }
        .notif-card span { display: block; }
        .notif-card a { position: absolute; top: 10px; right: 10px; color: red; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>

<div class="header">
    Admin Panel
    <div class="notification-icon" onclick="toggleSidebar()">🔔</div>
</div>

<div class="dashboard">
    <div class="card">
        <div>🔥</div>
        <p>Fire Alerts</p>
        <p class="<?php echo strtolower($fire); ?>"><?php echo $fire; ?></p>
    </div>
    <div class="card">
        <div>⛽</div>
        <p>Gas Alerts</p>
        <p class="<?php echo strtolower($gas); ?>"><?php echo $gas; ?></p>
    </div>
    <div class="card">
        <div>⚠️</div>
        <p>Emergency Alerts</p>
        <p class="<?php echo strtolower($emergency); ?>"><?php echo $emergency; ?></p>
    </div>
    <div class="card">
        <div>🌊</div>
        <p>Pump Status</p>
        <p class="<?php echo strtolower($pump); ?>"><?php echo $pump; ?></p>
    </div>
</div>

<div class="table-section">
    <table>
        <thead>
        <tr>
            <th>Timestamp</th>
            <th>Fire</th>
            <th>Gas</th>
            <th>Emergency</th>
            <th>Pump</th>
        </tr>
        </thead>
        <tbody>
        <?php if (count($logs) > 0): ?>
            <?php foreach (array_reverse($logs) as $log): ?>
                <tr>
                    <td><?php echo isset($log['timestamp']) && $log['timestamp'] !== null && $log['timestamp'] !== '' ? $log['timestamp'] : 'null'; ?></td>
                    <td><?php echo $log['fire_detected'] ? "🔥 On" : "🔥 Off"; ?></td>
                    <td><?php echo $log['gas_detected'] ? "⛽ On" : "⛽ Off"; ?></td>
                    <td><?php echo $log['emergency_triggered'] ? "⚠️ On" : "⚠️ Off"; ?></td>
                    <td><?php echo $log['pump_status'] ? "🌊 On" : "🌊 Off"; ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="5">No logs available</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="notifSidebar" class="sidebar">
    <h3>Notifications</h3>
    <?php if (count($notifications) > 0): ?>
        <?php foreach ($notifications as $note): ?>
        <div class="notif-card">
            <span><?php echo htmlspecialchars($note['message']); ?></span>
            <a href="#" class="delete-notification" data-id="<?php echo $note['id']; ?>">✖</a>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No notifications available</p>
    <?php endif; ?>
</div>

<script>
// Toggle sidebar visibility
function toggleSidebar() {
    document.getElementById("notifSidebar").classList.toggle("show");
}

// Deleting a notification
document.addEventListener('click', function(event) {
    if (event.target && event.target.classList.contains('delete-notification')) {
        event.preventDefault();
        const notificationId = event.target.dataset.id;

        fetch(`https://fire-backend-production.up.railway.app/sensor_notification.php?endpoint=delete_notification&id=${notificationId}`, {
            method: 'GET',
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                event.target.closest('.notif-card').remove();
            }
        })
        .catch(error => console.error('Error:', error));
    }
});
</script>

<script>
// Close sidebar when clicking outside
// This script ensures the sidebar closes if a user clicks outside of it or the notification icon
// It is placed after the main script to ensure DOM elements exist

document.addEventListener('mousedown', function(event) {
    const sidebar = document.getElementById("notifSidebar");
    const icon = document.querySelector('.notification-icon');
    if (
        sidebar.classList.contains('show') &&
        !sidebar.contains(event.target) &&
        !icon.contains(event.target)
    ) {
        sidebar.classList.remove('show');
    }
});
</script>

<script>
// 1-second polling to fetch data and update the UI without reloading
function updateDashboardAndLogs(data) {
    // Update dashboard cards
    if (data.sensor_data && data.sensor_data.length > 0) {
        const last = data.sensor_data[data.sensor_data.length - 1];
        const dashboardCards = document.querySelectorAll('.dashboard .card');
        if (dashboardCards.length >= 4) {
            dashboardCards[0].querySelector('p:last-child').className = (last.fire_detected == 1 || last.fire_detected === "1") ? 'on' : 'off';
            dashboardCards[0].querySelector('p:last-child').textContent = (last.fire_detected == 1 || last.fire_detected === "1") ? 'On' : 'Off';
            dashboardCards[1].querySelector('p:last-child').className = (last.gas_detected == 1 || last.gas_detected === "1") ? 'on' : 'off';
            dashboardCards[1].querySelector('p:last-child').textContent = (last.gas_detected == 1 || last.gas_detected === "1") ? 'On' : 'Off';
            dashboardCards[2].querySelector('p:last-child').className = (last.emergency_triggered == 1 || last.emergency_triggered === "1") ? 'on' : 'off';
            dashboardCards[2].querySelector('p:last-child').textContent = (last.emergency_triggered == 1 || last.emergency_triggered === "1") ? 'On' : 'Off';
            dashboardCards[3].querySelector('p:last-child').className = (last.pump_status == 1 || last.pump_status === "1") ? 'on' : 'off';
            dashboardCards[3].querySelector('p:last-child').textContent = (last.pump_status == 1 || last.pump_status === "1") ? 'On' : 'Off';
        }
    }

    // Update logs table
    if (data.sensor_data) {
        let logHtml = '';
        // Show newest first
        const logs = [...data.sensor_data].reverse();
        logs.forEach(log => {
            logHtml += `<tr>
                <td>${log.timestamp ? log.timestamp : 'null'}</td>
                <td>${log.fire_detected == 1 || log.fire_detected === "1" ? "🔥 On" : "🔥 Off"}</td>
                <td>${log.gas_detected == 1 || log.gas_detected === "1" ? "⛽ On" : "⛽ Off"}</td>
                <td>${log.emergency_triggered == 1 || log.emergency_triggered === "1" ? "⚠️ On" : "⚠️ Off"}</td>
                <td>${log.pump_status == 1 || log.pump_status === "1" ? "🌊 On" : "🌊 Off"}</td>
            </tr>`;
        });
        if (!logHtml) {
            logHtml = '<tr><td colspan="5">No logs available</td></tr>';
        }
        const logsTbody = document.querySelector('.table-section table tbody');
        if (logsTbody) {
            logsTbody.innerHTML = logHtml;
        }
    }
}

function updateNotifications(data) {
    if (data.notifications) {
        let notifHtml = '<h3>Notifications</h3>';
        if (data.notifications.length > 0) {
            data.notifications.forEach(note => {
                notifHtml += `<div class="notif-card">` +
                    `<span>${note.message}</span>` +
                    `<a href=\"#\" class=\"delete-notification\" data-id=\"${note.id}\">✖</a>` +
                    `</div>`;
            });
        } else {
            notifHtml += '<p>No notifications available</p>';
        }
        const notifSidebar = document.getElementById('notifSidebar');
        if (notifSidebar) {
            notifSidebar.innerHTML = notifHtml;
        }
    }
}

function pollDataAndRefresh() {
    fetch('https://fire-backend-production.up.railway.app/sensor_notification.php?endpoint=fetch')
        .then(response => response.json())
        .then(data => {
            updateDashboardAndLogs(data);
            updateNotifications(data);
            setTimeout(pollDataAndRefresh, 1000); // Poll again after 1 second
        })
        .catch(error => {
            console.error('Polling error:', error);
            setTimeout(pollDataAndRefresh, 1000); // Retry after 1 second
        });
}

window.addEventListener('DOMContentLoaded', function() {
    pollDataAndRefresh();
});
</script>

</body>
</html>