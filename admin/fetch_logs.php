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
        $act = strtolower(trim($log['action']));
        
        // ─── UTAMAKAN INI: PENENTUAN WARNA BADGE DINAMIK MENGIKUT KEYWORD TINDAKAN ───
        $badgeType = "badge-default";
        if (strpos($act, 'create') !== false) { $badgeType = "badge-create"; }
        elseif (strpos($act, 'issue') !== false) { $badgeType = "badge-issue"; }
        elseif (strpos($act, 'revoke') !== false) { $badgeType = "badge-revoke"; }
        elseif (strpos($act, 'verify') !== false) { $badgeType = "badge-verify"; }

        echo "<tr>";
        echo "<td><strong>#" . htmlspecialchars($log['logID']) . "</strong></td>";
        echo "<td><span style='font-weight: 600; color: #183055;'>" . htmlspecialchars($log['admin_name']) . "</span></td>";
        
        // 💡 DIBAIKI: Tambah pembolehubah $badgeType pada kelas span
        echo "<td><span class='action-badge " . $badgeType . "'>" . htmlspecialchars($log['action']) . "</span></td>";
        
        // 💡 DIBAIKI: Tambah style font monospace untuk resource signature
        echo "<td><span style='font-family: monospace; color: #4a5568; font-size: 13px;'>" . htmlspecialchars($log['resource']) . "</span></td>";
        
        // 💡 DIBAIKI: Tambah ikon kalendar pada sel waktu
        echo "<td class='timestamp-cell'><i class='fa-regular fa-calendar-days'></i> " . date("d M Y | h:i A", strtotime($log['timestamp'])) . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='5' style='text-align:center; padding: 30px; color: #718096;'>No internal logs registered inside this network grid.</td></tr>";
}
?>