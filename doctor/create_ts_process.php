<?php
use Dompdf\Dompdf;
use Dompdf\Options;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();
require '../db_connect.php';
require 'vendor/autoload.php';

date_default_timezone_set('Asia/Kuala_Lumpur');
set_time_limit(120); 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $doctorID = $_SESSION['userID']; 
    $sql_dr = "SELECT name, mmc_number FROM doctor_profiles WHERE doctorID = ?";
    $stmt_dr = $conn->prepare($sql_dr);
    $stmt_dr->bind_param("i", $doctorID);
    $stmt_dr->execute();
    $dr_result = $stmt_dr->get_result()->fetch_assoc();
    $doctorName = $dr_result['name'] ?? "Medical Officer";

    $patientName  = mysqli_real_escape_string($conn, $_POST['full_name']);
    $patientNRIC  = mysqli_real_escape_string($conn, $_POST['patientNRIC']); 
    $matric_no    = mysqli_real_escape_string($conn, $_POST['matric_staff_no']);
    $patientEmail = !empty($_POST['patient_email']) ? mysqli_real_escape_string($conn, $_POST['patient_email']) : NULL;
    $diagnosis    = mysqli_real_escape_string($conn, $_POST['diagnosis']);
    
    // PEMBETULAN: Dapatkan nilai dari POST dahulu sebelum format
    $visitDate    = $_POST['visit_date']; 
    $startTime    = date("H:i:s", strtotime($_POST['time_in']));
    $endTime      = date("H:i:s", strtotime($_POST['time_out']));
    $currentTime = date("H:i:s");

    // Formatkan string yang akan muncul dalam PDF untuk tujuan Hashing
    $visitDateStr = date('d F Y', strtotime($visitDate));
    $startTimeStr = date("h:i A", strtotime($startTime));
    $endTimeStr   = date("h:i A", strtotime($endTime));

    // Bina rawData guna string yang diformat (Sama seperti process_verification.php)
    $rawData = trim($patientNRIC) . trim($visitDateStr) . trim($startTimeStr) . trim($endTimeStr) . trim($doctorID) . trim($currentTime);    
    $documentHash = hash('sha256', $rawData);

    // ====== AUTOMATED TAMPER DETECTION: BINA PAYLOAD KRIPTOGRAFI ======
    // Gabungkan data kritikal: Hash, Nama, No Matrik, dan Masa Masuk (Time In)
    $raw_payload = $documentHash . '|' . $patientName . '|' . $matric_staff_no . '|' . $timeIn;

    // Enkod menjadi string selamat Base64
    $encrypted_payload = base64_encode($raw_payload);

    // Simpan payload ini ke dalam session untuk kegunaan generate_pdf.php
    $_SESSION['pdf_qr_payload'] = $encrypted_payload;
    $_SESSION['pdf_doc_hash'] = $documentHash;

    // Blockchain Interaction
    $command = "node " . __DIR__ . "/blockchain_relayer.js " . escapeshellarg($documentHash);
    $blockchainTx = shell_exec($command);
    $blockchainTx = trim($blockchainTx); 
    
    if (empty($blockchainTx) || substr($blockchainTx, 0, 2) !== '0x') {
        header("Location: create_mc.php?msg=error&detail=blockchain_fail");
        exit();
    }

    // BINA DATETIME YANG SAMA DENGAN CURRENT TIME PHP UNTUK DI-FORCE KE DATABASE
    $currentDate = date("Y-m-d");
    $combinedDateTime = $currentDate . " " . $currentTime;

    $conn->begin_transaction();
    try {
        // Simpan ke Jadual Timeslip (Ganti NOW() dengan ?, tambah "s" pada bind_param)
        $sql = "INSERT INTO timeslip (doctorID, patientName, patientNRIC, matric_staff_no, patientEmail, visitDate, timeIn, timeOut, diagnosis, documentHash, transactionHash, status, createdAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssssssssss", $doctorID, $patientName, $patientNRIC, $matric_no, $patientEmail, $visitDate, $startTime, $endTime, $diagnosis, $documentHash, $blockchainTx, $combinedDateTime);
        $stmt->execute(); 
        
        $newTSID = $conn->insert_id;
        
        $sql_block = "INSERT INTO blockchainrecord (documentHash, previousHash, blockHash) VALUES (?, ?, ?)";
        $stmt_block = $conn->prepare($sql_block);
        $prevHashSql = $conn->query("SELECT blockHash FROM blockchainrecord ORDER BY transactionID DESC LIMIT 1");
        $previousHash = ($prevHashSql->num_rows > 0) ? $prevHashSql->fetch_assoc()['blockHash'] : str_repeat("0", 64);
        $blockHash = hash('sha256', $documentHash . $previousHash . $blockchainTx);
        $stmt_block->bind_param("sss", $documentHash, $previousHash, $blockHash);
        $stmt_block->execute();

        //commit transaction main database
        $conn->commit();

        $auditAction = "CREATE TIMESLIP";

        $formattedTSID = "TSUTHM" . str_pad($newTSID, 6, "0", STR_PAD_LEFT);
        $auditResource = "Doc ID: " . $formattedTSID . " (NRIC: " . $patientNRIC . ")";        

        $sql_audit = "INSERT INTO auditlog (userID, action, resource, timestamp) VALUES (?, ?, ?, NOW())";
        $stmt_audit = $conn->prepare($sql_audit);
        $stmt_audit->bind_param("iss", $doctorID, $auditAction, $auditResource);
        $stmt_audit->execute();
        
        if (!empty($patientEmail) && filter_var($patientEmail, FILTER_VALIDATE_EMAIL)) {
            try {
                $options = new \Dompdf\Options();
                $options->set('isRemoteEnabled', true); 
                $dompdf = new \Dompdf\Dompdf($options);

                $serverIP = "192.168.0.223"; 
                $verificationURL = "http://" . $serverIP . "/fyp/verify.php?hash=" . $documentHash;
                $qrCodeURL = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode($verificationURL);

                $html = "
                <html>
                <head>
                    <style>
                        body { font-family: 'Helvetica', Arial, sans-serif; background-color: #ffffff; margin: 0; padding: 0; }
                        .document-container { padding: 40px; position: relative; background: #fff; }
                        .header { text-align: center; border-bottom: 3px solid #183055; padding-bottom: 15px; margin-bottom: 25px; }
                        .status-stamp {
                            position: absolute; top: 40px; right: 40px; padding: 8px 15px;
                            border: 4px solid #27ae60; border-radius: 8px; font-weight: bold; font-size: 20px;
                            color: #27ae60; transform: rotate(15deg); opacity: 0.7; text-transform: uppercase;
                        }
                        table.info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
                        .label { font-size: 10px; color: #7f8c8d; text-transform: uppercase; margin-bottom: 2px; }
                        .value { font-size: 14px; color: #2c3e50; font-weight: bold; }
                        .footer-meta { margin-top: 40px; padding-top: 15px; border-top: 1px solid #eee; }
                        .hash-box { font-family: monospace; font-size: 8px; color: #7f8c8d; word-wrap: break-word; background: #f9f9f9; padding: 5px; }
                    </style>
                </head>
                <body>
                    <div class='document-container'>
                        <div class='status-stamp'>VERIFIED</div>

                        <div class='header'>
                            <h1 style='color: #183055; margin: 0; font-size: 24px;'>TIME-SLIP</h1>
                            <p style='margin: 5px 0; font-size: 14px;'>Pusat Kesihatan Universiti | UTHM</p>
                            <p style='font-size: 12px; color: #555;'>Document ID: TSUTHM" . str_pad($newTSID, 6, "0", STR_PAD_LEFT) . "</p>
                        </div>

                        <table class='info-table'>
                            <tr>
                                <td width='50%'>
                                    <div class='label'>Patient Name</div>
                                    <div class='value'>" . strtoupper(htmlspecialchars($patientName)) . "</div>
                                </td>
                                <td width='50%'>
                                    <div class='label'>Patient NRIC</div>
                                    <div class='value'>" . htmlspecialchars($patientNRIC) . "</div>
                                </td>
                            </tr>
                        </table>

                        <table class='info-table'>
                            <tr>
                                <td width='100%'>
                                    <div class='label'>Matric / Staff Number</div>
                                    <div class='value'>" . strtoupper(htmlspecialchars($matric_no)) . "</div>
                                </td>
                            </tr>
                        </table>

                        <table class='info-table'>
                            <tr>
                                <td width='50%'>
                                    <div class='label'>Visit Date</div>
                                    <div class='value'>" . $visitDateStr . "</div>
                                </td>
                                <td width='50%'>
                                    <div class='label'>Time</div>
                                    <div class='value'>" . $startTimeStr . " - " . $endTimeStr . "</div>
                                </td>
                            </tr>
                        </table>

                        <div style='margin-bottom: 20px;'>
                            <div class='label'>Diagnosis / Purpose</div>
                            <div class='value' style='font-style: italic;'>\"" . htmlspecialchars($diagnosis) . "\"</div>
                        </div>

                        <div style='margin-bottom: 20px;'>
                            <div class='label'>Attending Physician</div>
                            <div class='value'>Dr. " . htmlspecialchars($doctorName) . "</div>
                            <div style='font-size: 1px; color: #ffffff;'>SEAL_DID:" . $doctorID . " | GEN_TIME:" . $currentTime . "</div>
                        </div>

                        <div class='footer-meta'>
                            <table width='100%'>
                                <tr>
                                    <td width='75%' style='vertical-align: top;'>
                                        <div style='font-size: 10px;'>
                                            <strong>Digital Fingerprint (SHA-256):</strong>
                                            <div class='hash-box'>" . $documentHash . "</div>
                                            <br>
                                            <strong>Blockchain Transaction ID:</strong>
                                            <div class='hash-box'>" . $blockchainTx . "</div>
                                            <p style='margin-top: 8px; font-style: italic; font-size: 9px;'>Verified by SEAL Blockchain Security System</p>
                                        </div>
                                    </td>
                                    <td width='25%' style='text-align: center; vertical-align: top;'>
                                        <img src='" . $qrCodeURL . "' width='80'>
                                        <p style='font-size: 8px; color: #183055; font-weight: bold; margin-top: 4px;'>Verify Online</p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </body>
                </html>";

                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                $pdfOutput = $dompdf->output();

                $mail = new PHPMailer(true);
                $mail->isSMTP(); 
                $mail->Host = 'smtp.gmail.com'; 
                $mail->SMTPAuth = true;
                $mail->Username = 'adamuqrii@gmail.com'; 
                $mail->Password = 'jaujitzxavbqcvic';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
                $mail->Port = 587;
                $mail->setFrom('no-reply@seal.com', 'SEAL Medical Portal');
                $mail->addAddress($patientEmail, $patientName);
                $mail->isHTML(true);
                $mail->Subject = 'Digital Time-Slip - ' . strtoupper($patientName);
                $mail->Body = "Hello $patientName,<br><br>Attached is your digital Time-Slip ID: TSUTHM" . str_pad($newTSID, 6, "0", STR_PAD_LEFT);    
                $mail->addStringAttachment($pdfOutput, "TimeSlip_{$patientNRIC}.pdf");
                $mail->send();

            } catch (Exception $e) { 
                error_log("Email Error: " . $mail->ErrorInfo); 
            }
        } 

        header("Location: create_timeslip.php?msg=success");
        exit();

    } catch (Exception $e) { 
        $conn->rollback(); 
        die("Error: " . $e->getMessage()); 
    }
} 
?>