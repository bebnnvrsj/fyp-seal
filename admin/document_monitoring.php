<?php
session_start();
// Pastikan hanya admin boleh akses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/login.php");
    exit();
}
require '../db_connect.php'; 

// GABUNGKAN DATA JADUAL MC DAN TIMESLIP MENGGUNAKAN UNION
$sql = "SELECT m.mcID AS id, m.patientName, 'MC' AS doc_type, m.status, m.documentHash, m.createdAt, u.name AS doctor_name 
        FROM mc m
        INNER JOIN users u ON m.doctorID = u.userID
        UNION
        SELECT t.slipID AS id, t.patientName, 'Time Slip' AS doc_type, t.status, t.documentHash, t.createdAt, u.name AS doctor_name 
        FROM timeslip t
        INNER JOIN users u ON t.doctorID = u.userID
        ORDER BY createdAt DESC";

$result = $conn->query($sql);

// Mengira jumlah keseluruhan dokumen yang dikeluarkan
$total_docs = $result ? $result->num_rows : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Monitoring - SEAL</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --sidebar-width: 260px;
            --header-bg: #2b7a9e;
            --main-bg: linear-gradient(to bottom, #caf0f8, #90e0ef, #48cae4);
            --dark-blue: #183055;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, sans-serif;
            background: var(--main-bg);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
        }

        /* ====== SIDEBAR ====== */
        .sidebar { width: var(--sidebar-width); height: 100vh; background-color: var(--dark-blue); color: white; position: fixed; left: 0; top: 0; transition: transform 0.3s ease; z-index: 1000; display: flex; flex-direction: column; }
        .sidebar.closed { transform: translateX(-100%); }
        .sidebar-header { padding: 20px; background-color: #122542; display: flex; align-items: center; gap: 15px; font-weight: bold; }
        .sidebar-menu { list-style: none; padding: 0; margin: 20px 0; }
        .sidebar-menu li a { padding: 15px 25px; display: flex; align-items: center; gap: 15px; color: #d1d9e6; text-decoration: none; transition: 0.2s; }
        .sidebar-menu li a:hover { background-color: #2b7a9e; color: white; }
        .sidebar-menu li a.active { background-color: #2b7a9e; color: white; border-left: 4px solid #fff; }

        /* ====== MAIN WRAPPER ====== */
        .main-wrapper { flex: 1; display: flex; flex-direction: column; margin-left: var(--sidebar-width); transition: margin-left 0.3s ease; width: 100%; }
        .main-wrapper.full-width { margin-left: 0; }

        .header { height: 56px; background-color: var(--header-bg); display: flex; align-items: center; justify-content: space-between; padding: 0 24px; color: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .toggle-btn { cursor: pointer; font-size: 20px; }

        /* ====== CONTAINER ====== */
        .container { 
            width: 95%; 
            max-width: 100%; 
            margin: 30px auto; 
            padding: 0 40px; 
            box-sizing: border-box;
            display: flex; 
            flex-direction: column; 
            gap: 25px; 
        }

        .page-hero {
            background: white;
            border-radius: 15px;
            padding: 25px 35px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .hero-info h1 { margin: 0; color: var(--dark-blue); font-size: 24px; }
        .hero-info p { margin: 5px 0 0; color: #666; font-size: 14px; }

        /* Search Bar */
        .actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .search-wrapper { position: relative; width: 350px; }
        .search-wrapper input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border-radius: 10px;
            border: 1px solid #ddd;
            outline: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .search-wrapper i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #888; }

        /* Table Card */
        .management-card {
            background: #ffffff;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0px 4px 15px rgba(0,0,0,0.1);
            width: 100%; 
            overflow-x: auto;
        }

        table { width: 100%; border-collapse: collapse; }
        th { background-color: #f8f9fa; color: #555; text-align: left; padding: 15px; border-bottom: 2px solid #dee2e6; font-weight: 600; }
        td { padding: 15px; border-bottom: 1px solid #eee; color: #333; vertical-align: middle; }
        tr:hover { background-color: #f1f8ff; }

        .doc-badge { padding: 5px 12px; border-radius: 6px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .doc-mc { background: #e3f2fd; color: #0d47a1; }
        .doc-ts { background: #e8f5e9; color: #1b5e20; }

        /* Blockchain Dynamic Badges */
        .chain-status { font-weight: 600; font-size: 13px; display: flex; align-items: center; gap: 8px; }
        .chain-loading { color: #f39c12; }
        .chain-success { color: #2e7d32; }
        .chain-failed { color: #c62828; }

        .hash-text { font-family: 'Courier New', Courier, monospace; font-size: 11px; color: #7f8c8d; background: #f8f9fa; padding: 4px 8px; border-radius: 4px; display: inline-block; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        @media (max-width: 768px) {
            td, th { padding: 10px; font-size: 13px; }
        }
    </style>
</head>
<body>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header"><i class="fa-solid fa-user-shield"></i> <span>SEAL</span></div>
    <ul class="sidebar-menu">
        <li><a href="../admin/home_admin.php"><i class="fa-solid fa-house"></i> Dashboard</a></li>
        <li><a href="../admin/register_patient.php"><i class="fa-solid fa-user-plus"></i> Register Patient</a></li>
        <li><a href="../admin/user_management.php"><i class="fa-solid fa-users-gear"></i> User Management</a></li>
        <li><a href="../admin/document_monitoring.php" class="active"><i class="fa-solid fa-file-shield"></i> Doc Monitoring</a></li>
        <li><a href="../admin/audit_logs.php"><i class="fa-solid fa-clipboard-list"></i> Audit Logs</a></li>
        <li><a href="../profile.php"><i class="fa-solid fa-user-gear"></i> Profile Settings</a></li>
        <li><a href="../login/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
    </ul>
</div>

<div class="main-wrapper" id="mainWrapper">
    <div class="header">
        <div class="header-left">
            <i class="fa-solid fa-bars toggle-btn" onclick="toggleSidebar()"></i>
            <span style="font-weight: 600; margin-left: 15px;">Administrator Portal</span>
        </div>
    </div>

    <div class="container">
        <div class="page-hero">
            <div class="hero-info">
                <h1><i class="fa-solid fa-file-shield"></i> Document Blockchain Monitoring</h1>
                <p>Live Integrity Verification | Comparing Local Metadata with Decentralized Ethereum Ledger</p>
            </div>
            <div style="background: var(--dark-blue); color: white; padding: 10px 20px; border-radius: 10px; font-weight: bold; text-align: center;">
                <span style="font-size: 11px; display:block; opacity: 0.8;">TOTAL ISSUED</span>
                <span style="font-size: 20px;"><?php echo $total_docs; ?></span>
            </div>
        </div>

        <div class="actions-bar">
            <div class="search-wrapper">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="docSearch" onkeyup="filterTable()" placeholder="Search by patient name...">
            </div>
        </div>

        <div class="management-card">
            <table id="docTable">
                <thead>
                    <tr>
                        <th>Doc ID</th>
                        <th>Patient Name</th>
                        <th>Type</th>
                        <th>Issued By (Doctor)</th> <th>Document Hash (SHA-256)</th>
                        <th>Blockchain Status (Live Fetch)</th>
                        <th>Blockchain Block Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result && $result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            $docType = strtoupper($row['doc_type']);
                            $badgeClass = ($docType == 'MC') ? 'doc-mc' : 'doc-ts';
                            $docHash = $row['documentHash'];
                            $doctorName = $row['doctor_name']; // Ambil nama doktor dari query

                            // Jana ID 6 Digit mengikut format UTHM
                            $formattedID = str_pad($row["id"], 6, "0", STR_PAD_LEFT);
                            $displayID = ($docType == 'MC') ? "MCUTHM" . $formattedID : "TSUTHM" . $formattedID;
                            $uniqueRowID = strtolower($docType) . "-" . $row["id"]; 

                            echo "<tr>";
                            echo "<td><strong>#" . $displayID . "</strong></td>";
                            echo "<td>" . htmlspecialchars($row["patientName"]) . "</td>";
                            echo "<td><span class='doc-badge $badgeClass'>" . $docType . "</span></td>";
                            
                            // PAPAR NAMA DOKTOR
                            echo "<td><i class='fa-solid fa-user-md' style='color:#2b7a9e; margin-right:5px;'></i> " . htmlspecialchars($doctorName) . "</td>";
                            
                            echo "<td><span class='hash-text' title='".$docHash."'>" . htmlspecialchars($docHash) . "</span></td>";
                            
                            // Lajur Status Blockchain (Live Fetch)
                            echo "<td>
                                    <span class='chain-status chain-loading' id='status-".$uniqueRowID."' data-hash='".$docHash."'>
                                        <i class='fa-solid fa-spinner fa-spin'></i> Querying Sepolia...
                                    </span>
                                </td>";
                            
                            // Lajur Waktu Blok Blockchain
                            echo "<td>
                                    <span id='time-".$uniqueRowID."' style='color: #555; font-size: 13px;'>-</span>
                                </td>";
                            
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7' style='text-align:center; padding: 30px;'>No health documents issued yet.</td></tr>"; // Tukar colspan ke 7
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const mainWrapper = document.getElementById('mainWrapper');
        sidebar.classList.toggle('closed');
        mainWrapper.classList.toggle('full-width');
    }

    function filterTable() {
        const input = document.getElementById("docSearch");
        const filter = input.value.toUpperCase();
        const table = document.getElementById("docTable");
        const tr = table.getElementsByTagName("tr");

        for (let i = 1; i < tr.length; i++) {
            const tdName = tr[i].getElementsByTagName("td")[1];
            if (tdName) {
                const txtValueName = tdName.textContent || tdName.innerText;
                if (txtValueName.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }
        }
    }

    // INTERACTION DENGAN RELAYER UNTUK MENGAMBIL DATA TERUS DARI SEPOLIA
    async function fetchLiveBlockchainData() {
        const statusElements = document.querySelectorAll('[id^="status-"]');

        for (let element of statusElements) {
            const docKey = element.id.replace('status-', ''); // Ambil key unik contoh: mc-1 atau ts-5
            const rawHash = element.getAttribute('data-hash');
            const timeElement = document.getElementById(`time-${docKey}`);

            if (!rawHash) {
                element.innerHTML = "<i class='fa-solid fa-triangle-exclamation'></i> No Hash Found";
                element.className = "chain-status chain-failed";
                continue;
            }

            try {
                const response = await fetch(`http://localhost:3000/verify-on-blockchain/${rawHash}`);
                if (!response.ok) throw new Error("Relayer offline");
                
                const data = await response.json();

                if (data.isValid === true) {
                    element.innerHTML = "<i class='fa-solid fa-circle-check'></i> Secured on Chain";
                    element.className = "chain-status chain-success";

                    const unixTimestamp = parseInt(data.timestamp);
                    if (unixTimestamp > 0) {
                        const date = new Date(unixTimestamp * 1000);
                        timeElement.innerText = date.toLocaleString('en-MY', { 
                            day: '2-digit', 
                            month: 'short', 
                            year: 'numeric', 
                            hour: '2-digit', 
                            minute: '2-digit',
                            hour12: true 
                        });
                    } else {
                        timeElement.innerText = "Genesis Block";
                    }
                } else {
                    element.innerHTML = "<i class='fa-solid fa-circle-xmark'></i> Untrusted / Tampered";
                    element.className = "chain-status chain-failed";
                    timeElement.innerHTML = "<span style='color:red; font-weight:bold;'>NOT ON LEDGER</span>";
                }

            } catch (error) {
                element.innerHTML = "<i class='fa-solid fa-plug-circle-xmark'></i> Relayer Error";
                element.className = "chain-status chain-failed";
                timeElement.innerText = "Connection lost";
            }
        }
    }

    document.addEventListener("DOMContentLoaded", function() {
        fetchLiveBlockchainData();
    });
</script>
</body>
</html>