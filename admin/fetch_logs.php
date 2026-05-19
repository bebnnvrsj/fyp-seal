<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    exit("Unauthorized");
}
require '../db_connect.php';

$actionFilter = isset($_GET['action']) ? mysqli_real_escape_string($conn, $_GET['action']) : '';

// Bina SQL secara dinamik
$sql = "SELECT a.logID, u.name as admin_name, a.action, a.resource, a.timestamp 
        FROM auditlog a 
        JOIN users u ON a.userID = u.userID";

if (!empty($actionFilter)) {
    $sql .= " WHERE a.action = '$actionFilter'";
}

$sql .= " ORDER BY a.timestamp DESC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while($log = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td><strong>#" . htmlspecialchars($log['logID']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($log['admin_name']) . "</td>";
        echo "<td><span class='action-badge'>" . htmlspecialchars($log['action']) . "</span></td>";
        echo "<td>" . htmlspecialchars($log['resource']) . "</td>";
        echo "<td>" . date("d M Y | h:i A", strtotime($log['timestamp'])) . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='5' style='text-align:center; padding: 30px;'>No logs found for this action.</td></tr>";
}
?>