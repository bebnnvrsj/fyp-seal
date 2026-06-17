<?php
require 'vendor/autoload.php'; 
require '../db_connect.php'; 

use Dompdf\Dompdf; //
use Dompdf\Options; //

// Receive HASH and TYPE parameters from request for processing 
$hash = isset($_GET['hash']) ? mysqli_real_escape_string($conn, $_GET['hash']) : ''; //
$type = isset($_GET['type']) ? mysqli_real_escape_string($conn, $_GET['type']) : ''; //

if (empty($hash) || empty($type)) {
    die("Access Denied: Required document parameters are missing."); //
}

// Query database to retrieve record based on provided document hash
$sql = ($type === 'mc') 
    ? "SELECT m.*, dp.name as doctor_name, dp.mmc_number 
       FROM mc m 
       JOIN users u ON m.doctorID = u.userID 
       LEFT JOIN doctor_profiles dp ON u.userID = dp.doctorID 
       WHERE m.documentHash = ?" 
    : "SELECT t.*, dp.name as doctor_name, dp.mmc_number 
       FROM timeslip t 
       JOIN users u ON t.doctorID = u.userID 
       LEFT JOIN doctor_profiles dp ON u.userID = dp.doctorID 
       WHERE t.documentHash = ?";
$stmt = $conn->prepare($sql); 
$stmt->bind_param("s", $hash);
$stmt->execute(); //
$doc = $stmt->get_result()->fetch_assoc(); //

if (!$doc) { die("Document not found."); } //

$genTime = date("H:i:s", strtotime($doc['createdAt'])); //

$dayTextDisplay = "";
if ($type === 'mc') {
    $date1 = new DateTime($doc['startDate']); //
    $date2 = new DateTime($doc['endDate']); //
    $interval = $date1->diff($date2); //
    $totalDaysCount = $interval->days + 1; //

    $numberToWords = [
        1 => 'One (1)', 2 => 'Two (2)', 3 => 'Three (3)', 4 => 'Four (4)', 5 => 'Five (5)',
        6 => 'Six (6)', 7 => 'Seven (7)', 8 => 'Eight (8)', 9 => 'Nine (9)', 10 => 'Ten (10)',
        11 => 'Eleven (11)', 12 => 'Twelve (12)', 13 => 'Thirteen (13)', 14 => 'Fourteen (14)', 15 => 'Fifteen (15)',
        16 => 'Sixteen (16)', 17 => 'Seventeen (17)', 18 => 'Eighteen (18)', 19 => 'Nineteen (19)', 20 => 'Twenty (20)',
        21 => 'Twenty-One (21)', 22 => 'Twenty-Two (22)', 23 => 'Twenty-Three (23)', 24 => 'Twenty-Four (24)', 25 => 'Twenty-Five (25)',
        26 => 'Twenty-Six (26)', 27 => 'Twenty-Seven (27)', 28 => 'Twenty-Eight (28)', 29 => 'Twenty-Nine (29)', 30 => 'Thirty (30)'
    ]; //
    $dayTextDisplay = isset($numberToWords[$totalDaysCount]) ? $numberToWords[$totalDaysCount] : $totalDaysCount; //
}

// QR Code generation
$serverIP = "seal-uthm.site"; //
$verificationURL = "http://" . $serverIP . "/login/login.php"; //
$qrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=" . urlencode($verificationURL); //

// Use Base64 encoding to embed QR code into Dompdf for stable PDF rendering 
$qrData = file_get_contents($qrApiUrl); //
$qrBase64 = 'data:image/png;base64,' . base64_encode($qrData); //

// Dompdf configuration
$options = new Options(); //
$options->set('isHtml5ParserEnabled', true); //
$options->set('isRemoteEnabled', true);  //
$dompdf = new Dompdf($options); //

$html = "
<html>
<head>
    <style>
        body { font-family: 'Helvetica', Arial, sans-serif; color: #333; margin: 0; padding: 0; background-color: #ffffff; }
        .document-container { padding: 40px; position: relative; background: #fff; }
        .header { text-align: center; border-bottom: 3px solid #183055; padding-bottom: 15px; margin-bottom: 25px; }
        .header h1 { color: #183055; margin: 0; font-size: 24px; text-transform: uppercase; letter-spacing: 1px; }
        .header p { margin: 5px 0; font-size: 14px; }
        .status-stamp {
            position: absolute; top: 40px; right: 40px; padding: 8px 15px;
            border: 4px solid #27ae60; border-radius: 8px; font-weight: bold; font-size: 20px;
            color: #27ae60; transform: rotate(15deg); opacity: 0.7; text-transform: uppercase;
        }
        table.info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        table.info-table td { padding: 8px 0; vertical-align: top; }
        .label { font-size: 10px; color: #7f8c8d; text-transform: uppercase; margin-bottom: 2px; font-weight: bold; }
        .value { font-size: 14px; color: #2c3e50; font-weight: bold; }
        .footer-meta { margin-top: 40px; padding-top: 15px; border-top: 1px solid #eee; width: 100%; }
        .hash-box { font-family: monospace; font-size: 8px; color: #7f8c8d; word-wrap: break-word; background: #f9f9f9; padding: 5px; margin-top: 3px; }
        
        /* Tulisan putih keselamatan mikroskopi untuk Parser */
        .seal-id { color: #ffffff; font-size: 1px; opacity: 0.01; text-align: left; margin-top: 10px; }
    </style>
</head>
<body>
    <div class='document-container'>
        <div class='status-stamp'>" . (strtoupper(trim($doc['status'])) === 'REVOKED' ? 'REVOKED' : 'DOCTOR COPY') . "</div>"; //

if ($type === 'mc') {
    // mc design
    $html .= "
        <div class='header'>
            <h1>Medical Certificate</h1>
            <p>Pusat Kesihatan Universiti | UTHM</p>
            <p style='font-size: 12px; color: #555;'>Document ID: MCUTHM" . str_pad($doc['mcID'], 6, "0", STR_PAD_LEFT) . "</p>
        </div>

        <table class='info-table'>
            <tr>
                <td width='50%'>
                    <div class='label'>Patient Name</div>
                    <div class='value'>" . strtoupper(htmlspecialchars($doc['patientName'])) . "</div>
                </td>
                <td width='50%'>
                    <div class='label'>Patient NRIC</div>
                    <div class='value'>" . htmlspecialchars($doc['patientNRIC']) . "</div>
                </td>
            </tr>
        </table>

        <table class='info-table'>
            <tr>
                <td width='100%'>
                    <div class='label'>Matric / Staff Number</div>
                    <div class='value'>" . strtoupper(htmlspecialchars($doc['matric_staff_no'])) . "</div>
                </td>
            </tr>
        </table>

        <table class='info-table'>
            <tr>
                <td width='33%'>
                    <div class='label'>Start Date</div>
                    <div class='value'>" . date('d M Y', strtotime($doc['startDate'])) . "</div>
                </td>
                <td width='33%'>
                    <div class='label'>End Date</div>
                    <div class='value'>" . date('d M Y', strtotime($doc['endDate'])) . "</div>
                </td>
                <td width='34%'>
                    <div class='label'>Total Duration</div>
                    <div class='value'>" . $dayTextDisplay . " day(s)</div>
                </td>
            </tr>
        </table>";
} else {
    // timeslip design
    $html .= "
        <div class='header'>
            <h1 style='color: #183055;'>Time-Slip</h1>
            <p>Pusat Kesihatan Universiti | UTHM</p>
            <p style='font-size: 12px; color: #555;'>Document ID: TSUTHM" . str_pad($doc['slipID'], 6, "0", STR_PAD_LEFT) . "</p>
        </div>

        <table class='info-table'>
            <tr>
                <td width='50%'>
                    <div class='label'>Patient Name</div>
                    <div class='value'>" . strtoupper(htmlspecialchars($doc['patientName'])) . "</div>
                </td>
                <td width='50%'>
                    <div class='label'>Patient NRIC</div>
                    <div class='value'>" . htmlspecialchars($doc['patientNRIC']) . "</div>
                </td>
            </tr>
        </table>

        <table class='info-table'>
            <tr>
                <td width='100%'>
                    <div class='label'>Matric / Staff Number</div>
                    <div class='value'>" . strtoupper(htmlspecialchars($doc['matric_staff_no'])) . "</div>
                </td>
            </tr>
        </table>

        <table class='info-table'>
            <tr>
                <td width='50%'>
                    <div class='label'>Visit Date</div>
                    <div class='value'>" . date('d F Y', strtotime($doc['visitDate'])) . "</div>
                </td>
                <td width='50%'>
                    <div class='label'>Time</div>
                    <div class='value'>" . date("h:i A", strtotime($doc['timeIn'])) . " - " . date("h:i A", strtotime($doc['timeOut'])) . "</div>
                </td>
            </tr>
        </table>";
}

$html .= "
        <div style='margin-bottom: 20px;'>
            <div class='label'>Diagnosis / Purpose</div>
            <div class='value' style='font-style: italic;'>\"" . htmlspecialchars($doc['diagnosis']) . "\"</div>
        </div>

        <div style='margin-bottom: 20px;'>
            <div class='label'>Attending Physician</div>
            <div class='value'>Dr. " . htmlspecialchars($doc['doctor_name']) . "</div>
            
            <div style='font-size: 11px; color: #555; margin-top: 2px;'>
                <strong>MMC No:</strong> " . htmlspecialchars($doc['mmc_number'] ?? 'N/A') . "
            </div>";

if ($type === 'timeslip') {
    $html .= "
            <div style='font-family: monospace; font-size: 9px; color: #7f8c8d; margin-top: 4px;'>
                <strong>Digital Signature:</strong> " . $doc['digitalSignature'] . "
            </div>"; //
}

$html .= "
            <div style='font-size: 1px; color: #ffffff;'>SEAL_DID:" . $doc['doctorID'] . " | GEN_TIME:" . $genTime . "</div>
        </div>

        <div class='footer-meta'>
            <table width='100%'>
                <tr>
                    <td width='75%' style='vertical-align: top;'>
                        <div style='font-size: 10px; color: #333;'>
                            <strong>Digital Fingerprint (SHA-256):</strong>
                            <div class='hash-box'>" . $doc['documentHash'] . "</div>
                            <br>
                            <strong>Blockchain Transaction ID:</strong>
                            <div class='hash-box'>" . $doc['transactionHash'] . "</div>
                            <p style='margin-top: 8px; font-style: italic; font-size: 9px; color: #7f8c8d;'>Verified by SEAL Blockchain Security System</p>
                        </div>
                    </td>
                    <td width='25%' style='text-align: center; vertical-align: top;'>
                        <img src='" . $qrBase64 . "' width='80' style='border: 1px solid #eee; padding: 5px; background: white;'>
                        <p style='font-size: 8px; color: #183055; font-weight: bold; margin-top: 4px; text-transform: uppercase;'>Verify Online</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class='seal-id'>SEAL_DID:" . $doc['doctorID'] . " | GEN_TIME:" . $genTime . "</div>
    </div>
</body>
</html>";

// PDF generation and streaming to browser for download
$dompdf->loadHtml($html); //
$dompdf->setPaper('A4', 'portrait'); //
$dompdf->render(); //
$dompdf->stream(($type === 'mc' ? "MC_" : "TS_") . $doc['patientNRIC'] . ".pdf", ["Attachment" => true]); //
?>