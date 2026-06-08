<?php
session_start();
// Security guard: restrict directly running queries to admin roles only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    exit("Unauthorized");
}
require '../db_connect.php';

$actionFilter = isset($_GET['action']) ? mysqli_real_escape_string($conn, $_GET['action']) : '';

// 3NF Relational Mapping Query Layout to resolve names correctly from role subtables
$sql = "SELECT 
            a.logID, 
            a.action, 
            a.resource, 
            a.timestamp,
            IFNULL(COALESCE(ap.name, dp.name, vp.name), 'External Verifier') AS admin_name
        FROM auditlog a 
        LEFT JOIN users u ON a.userID = u.userID 
        LEFT JOIN admin_profiles ap ON u.userID = ap.adminID
        LEFT JOIN doctor_profiles dp ON u.userID = dp.doctorID
        LEFT JOIN verifier_profiles vp ON u.userID = vp.verifierID";

// Filter validation blocks based on active bubble glass choice selection
if (!empty($actionFilter)) {
    if ($actionFilter === 'VERIFY') {
        // Capture both standard authentic and tampered exceptions at once
        $sql .= " WHERE a.action LIKE 'VERIFY%'";
    } else {
        $sql .= " WHERE a.action = '$actionFilter'";
    }
}

$sql .= " ORDER BY a.timestamp DESC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while($log = $result->fetch_assoc()) {
        $act = strtoupper(trim($log['action']));
        
        // Dynamic badge style rendering assignment engine
        $badgeType = "badge-default";
        if (strpos($act, 'CREATE') !== false) { $badgeType = "badge-create"; }
        elseif (strpos($act, 'ISSUE') !== false) { $badgeType = "badge-issue"; }
        elseif (strpos($act, 'REVOKE') !== false) { $badgeType = "badge-revoke"; }
        elseif (strpos($act, 'VERIFY_AUTHENTIC') !== false) { $badgeType = "badge-verify"; }
        elseif (strpos($act, 'VERIFY_TAMPERED') !== false) { $badgeType = "badge-tampered"; }
        elseif ($act === 'USER_REGISTRATION') { $badgeType = "badge-registration"; } // Purple theme badge allocation

        // Assign label colors depending on target authentication entities
        $nameColor = ($log['admin_name'] === 'External Verifier') ? '#fd7e14' : '#183055';

        echo "<tr>";
        echo "<td><strong>#" . htmlspecialchars($log['logID']) . "</strong></td>";
        echo "<td><span style='font-weight: 600; color: " . $nameColor . ";'>" . htmlspecialchars($log['admin_name']) . "</span></td>";
        echo "<td><span class='action-badge " . $badgeType . "'>" . htmlspecialchars($log['action']) . "</span></td>";
        echo "<td><span style='font-family: monospace; color: #4a5568; font-size: 13px;'>" . htmlspecialchars($log['resource']) . "</span></td>";
        echo "<td class='timestamp-cell'><i class='fa-regular fa-calendar-days'></i> " . date("d M Y | h:i A", strtotime($log['timestamp'])) . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='5' style='text-align:center; padding: 30px; color: #718096;'>No logs matched this operational query footprint.</td></tr>";
}
?>