<?php
require 'vendor/autoload.php'; // Pastikan path vendor betul (dalam folder doctor)
require '../db_connect.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// 1. Terima parameter HASH dan TYPE
$hash = isset($_GET['hash']) ? mysqli_real_escape_string($conn, $_GET['hash']) : '';
$type = isset($_GET['type']) ? mysqli_real_escape_string($conn, $_GET['type']) : '';

if (empty($hash) || empty($type)) {
    die("Akses Terhalang: Maklumat dokumen tidak lengkap.");
}

// 2. Ambil data dari database menggunakan HASH
$sql = ($type === 'mc') 
    ? "SELECT m.*, u.name as doctor_name, u.mmc_number FROM mc m JOIN users u ON m.doctorID = u.userID WHERE m.documentHash = ?" 
    : "SELECT t.*, u.name as doctor_name, u.mmc_number FROM timeslip t JOIN users u ON t.doctorID = u.userID WHERE t.documentHash = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $hash);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();

if (!$doc) { die("Dokumen tidak dijumpai."); }

// === PEMBETULAN FORENSIK 1: Ambil masa genTime (Jam:Minit:Saat) yang tepat dari database ===
$genTime = date("H:i:s", strtotime($doc['createdAt']));

// 3. Jana Data QR Code (Tukar ke Base64 supaya stabil dalam PDF)
$serverIP = "pagan-ensnare-graveyard.ngrok-free.dev"; 
$encrypted_payload = isset($_SESSION['pdf_qr_payload']) ? $_SESSION['pdf_qr_payload'] : "";
$verificationURL = "https://" . $serverIP . "/fyp/verifier/process_verification.php?payload=" . urlencode($encrypted_payload);
$qrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=" . urlencode($verificationURL);

// Teknik Base64 untuk masukkan imej QR ke dalam Dompdf
$qrData = file_get_contents($qrApiUrl);
$qrBase64 = 'data:image/png;base64,' . base64_encode($qrData);

// 4. Konfigurasi Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true); 
$dompdf = new Dompdf($options);

// 5. Bina HTML (Design Rasmi + QR Code)
$html = '
<!DOCTYPE html>
<html>
<head>
    <style>
        @page { margin: 0; }
        body { font-family: "Helvetica", Arial, sans-serif; color: #2c3e50; margin: 0; padding: 40px; background-color: #ffffff; }
        .document-box { border: 1px solid #eee; padding: 40px; position: relative; height: 90%; }
        .header { text-align: center; border-bottom: 3px solid #183055; padding-bottom: 20px; margin-bottom: 30px; }
        .title { color: #183055; font-size: 26px; font-weight: bold; margin: 0; letter-spacing: 1px; }
        .status-stamp {
            position: absolute; top: 40px; right: 40px; padding: 8px 15px;
            border: 3px solid #27ae60; color: #27ae60; border-radius: 8px;
            font-weight: bold; font-size: 20px; transform: rotate(10deg); opacity: 0.5;
        }
        .info-grid { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .info-grid td { padding: 10px 0; vertical-align: top; }
        .label { font-size: 11px; color: #7f8c8d; text-transform: uppercase; font-weight: bold; }
        .value { font-size: 15px; color: #2c3e50; font-weight: bold; display: block; margin-top: 3px; }
        .diagnosis-box { background: #f9f9f9; padding: 15px; border-left: 4px solid #183055; margin: 20px 0; }
        .footer-meta { margin-top: 50px; border-top: 1px solid #eee; padding-top: 20px; width: 100%; }
        .hash-text { font-family: "Courier", monospace; font-size: 9px; color: #95a5a6; word-wrap: break-word; }
        
        /* Kekalkan tulisan putih tersembunyi yang selamat untuk dikesan oleh PDF Parser */
        .seal-id { color: #ffffff; font-size: 1px; opacity: 0.01; text-align: left; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="document-box">
        <div class="status-stamp">VERIFIED</div>
        
        <div class="header">
            <div class="title">' . ($type === 'mc' ? 'MEDICAL CERTIFICATE' : 'TIME-SLIP') . '</div>
            <div style="margin-top: 5px; font-size: 14px;">Pusat Kesihatan Universiti | UTHM</div>
            <div style="font-size: 12px; color: #7f8c8d;">Document ID: ' . ($type === 'mc' ? 'MCUTHM' : 'TSUTHM') . str_pad(($type === 'mc' ? $doc['mcID'] : $doc['slipID']), 6, "0", STR_PAD_LEFT) . '</div>
        </div>

        <table class="info-grid">
            <tr>
                <td width="50%">
                    <div class="label">Patient Name</div>
                    <div class="value">' . strtoupper($doc['patientName']) . '</div>
                </td>
                <td width="50%">
                    <div class="label">IC Number</div>
                    <div class="value">' . $doc['patientNRIC'] . '</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="label">Matric / Staff Number</div>
                    <div class="value">' . strtoupper($doc['matric_staff_no']) . '</div>
                </td>
                <td>';
if ($type === 'mc') {
    $html .= '
                    <div class="label">Duration</div>
                    <div class="value">' . date('d M Y', strtotime($doc['startDate'])) . ' - ' . date('d M Y', strtotime($doc['endDate'])) . '</div>';
} else {
    $html .= '
                    <div class="label">Visit Date</div>
                    <div class="value">' . date('d F Y', strtotime($doc['visitDate'])) . '</div>'; // Diselaraskan ke 'd F Y' sepadan dengan create_timeslip_process
}
$html .= '
                </td>
            </tr>
        </table>

        <div class="diagnosis-box">
            <div class="label">Diagnosis / Clinical Findings</div>
            <div class="value" style="font-style: italic; font-weight: normal;">"' . strtoupper($doc['diagnosis']) . '"</div>
        </div>

        <div style="margin-top: 20px;">
            <div class="label">Attending Physician</div>
            <div class="value">Dr. ' . $doc['doctor_name'] . '</div>
            <div style="font-size: 12px; color: #7f8c8d;">MMC No: ' . $doc['mmc_number'] . '</div>
        </div>

        <table class="footer-meta">
            <tr>
                <td width="75%">
                    <div class="label">Digital Fingerprint (SHA-256)</div>
                    <div class="hash-text">' . $doc['documentHash'] . '</div>
                    <div class="label" style="margin-top: 10px;">Blockchain Transaction ID</div>
                    <div class="hash-text">' . $doc['transactionHash'] . '</div>
                    <p style="font-size: 10px; font-style: italic; margin-top: 15px;">Verified by SEAL Blockchain Security System</p>
                </td>
                <td width="25%" align="right">
                    <img src="' . $qrBase64 . '" width="100" style="border: 1px solid #eee; padding: 5px;">
                    <div style="font-size: 9px; font-weight: bold; color: #183055; margin-top: 5px; text-align: center;">VERIFY ONLINE</div>
                </td>
            </tr>
        </table>
        
        <div class="seal-id">SEAL_DID:' . $doc['doctorID'] . ' | GEN_TIME:' . $genTime . '</div>
    </div>
</body>
</html>';

// 6. Jana dan Download PDF
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream(($type === 'mc' ? "MC_" : "TS_") . $doc['patientNRIC'] . ".pdf", ["Attachment" => true]);