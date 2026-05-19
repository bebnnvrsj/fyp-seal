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
    
    // Ambil info doktor
    $sql_dr = "SELECT name, mmc_number FROM doctor_profiles WHERE doctorID = ?";
    $stmt_dr = $conn->prepare($sql_dr);
    $stmt_dr->bind_param("i", $doctorID);
    $stmt_dr->execute();
    $dr_result = $stmt_dr->get_result()->fetch_assoc();
    $doctorName = $dr_result['name'] ?? "Medical Officer";

    // Sanitasi Input
    $patientName  = mysqli_real_escape_string($conn, $_POST['full_name']);
    $patientNRIC  = mysqli_real_escape_string($conn, $_POST['patientNRIC']);
    $matric_no    = mysqli_real_escape_string($conn, $_POST['matric_search']);
    $patientEmail = mysqli_real_escape_string($conn, $_POST['patient_email'] ?? '');
    $diagnosis    = mysqli_real_escape_string($conn, $_POST['diagnosis']);
    
    $dbStartDate = $_POST['start_date'];
    $dbEndDate   = $_POST['end_date'];
    $currentTime = date("H:i:s");

    // Formatkan tarikh untuk PDF & Hashing
    $startDateStr = date('d M Y', strtotime($dbStartDate));
    $endDateStr   = date('d M Y', strtotime($dbEndDate));

    // BINA RAW DATA UNTUK HASHING (Wajib sama dengan skrip verifikasi)
    $rawData = trim($patientNRIC) . trim($startDateStr) . trim($endDateStr) . strtoupper(trim($diagnosis)) . trim($doctorID) . trim($currentTime);    
    $documentHash = hash('sha256', $rawData);

    // ====== AUTOMATED TAMPER DETECTION: BINA PAYLOAD KRIPTOGRAFI ======
    // Gabungkan data kritikal: Hash, Nama, No Matrik, dan Tarikh Mula MC
    $raw_payload = $documentHash . '|' . $patientName . '|' . $matric_staff_no . '|' . $startDate;

    // Encode menjadi string selamat Base64 supaya tidak rosak di dalam URL QR Code
    $encrypted_payload = base64_encode($raw_payload);

    // Simpan payload ini ke dalam session supaya boleh dibaca oleh generate_pdf.php
    $_SESSION['pdf_qr_payload'] = $encrypted_payload;
    $_SESSION['pdf_doc_hash'] = $documentHash;
    
    // INTERAKSI BLOCKCHAIN
    $command = "node " . __DIR__ . "/blockchain_relayer.js " . escapeshellarg($documentHash);
    $blockchainTx = trim(shell_exec($command));

    // Validasi Output Blockchain (Mesti bermula dengan 0x)
    if (empty($blockchainTx) || substr($blockchainTx, 0, 2) !== '0x') {
        die("Blockchain Error: Transaction failed. Output: " . $blockchainTx);
    }    
    
    $systemSig = "SYSTEM_MC_SIG_" . bin2hex(random_bytes(16));    
    $lastBlockSql = "SELECT blockHash FROM blockchainrecord ORDER BY transactionID DESC LIMIT 1";
    $lastBlockResult = $conn->query($lastBlockSql);
    $previousHash = ($lastBlockResult->num_rows > 0) ? $lastBlockResult->fetch_assoc()['blockHash'] : str_repeat("0", 64);
    $blockHash = hash('sha256', $documentHash . $previousHash . $blockchainTx);

    // BINA DATETIME YANG SAMA DENGAN CURRENT TIME PHP UNTUK DI-FORCE KE DATABASE
    $currentDate = date("Y-m-d");
    $combinedDateTime = $currentDate . " " . $currentTime;

    $conn->begin_transaction();
    try {
        // Simpan ke Jadual MC (Ganti NOW() dengan ?, tambah "s" pada bind_param)
        $sql1 = "INSERT INTO mc (doctorID, patientName, patientNRIC, matric_staff_no, patientEmail, diagnosis, startDate, endDate, documentHash, digitalSignature, transactionHash, status, createdAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?)";       
        $stmt1 = $conn->prepare($sql1);
        $stmt1->bind_param("isssssssssss", $doctorID, $patientName, $patientNRIC, $matric_no, $patientEmail, $diagnosis, $dbStartDate, $dbEndDate, $documentHash, $systemSig, $blockchainTx, $combinedDateTime);
        $stmt1->execute();
        $newMCID = $conn->insert_id;

        // Simpan ke Jadual Blockchain Record (Internal Audit)
        $sql2 = "INSERT INTO blockchainrecord (documentHash, previousHash, blockHash) VALUES (?, ?, ?)";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("sss", $documentHash, $previousHash, $blockHash);
        $stmt2->execute();

        $conn->commit();

        $auditAction = "CREATE_MC";
        $formattedMCID = "MCUTHM" . str_pad($newMCID, 6, "0", STR_PAD_LEFT);
        $auditResource = "Doc ID: " . $formattedMCID . " (NRIC: " . $patientNRIC . ")";

        $sql_audit = "INSERT INTO auditlog (userID, action, resource, timestamp) VALUES (?, ?, ?, NOW())";
        $stmt_audit = $conn->prepare($sql_audit);
        $stmt_audit->bind_param("iss", $doctorID, $auditAction, $auditResource);
        $stmt_audit->execute();

        // PROSES PENJANAAN PDF & EMEL
        if (!empty($patientEmail) && filter_var($patientEmail, FILTER_VALIDATE_EMAIL)) {
            $options = new Options();
            $options->set('isRemoteEnabled', true); 
            $dompdf = new Dompdf($options);

            $serverIP = "192.168.0.223"; // Tukar kepada IP server anda
            $verificationURL = "http://" . $serverIP . "/fyp/verify.php?hash=" . $documentHash;
            $qrCodeURL = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode($verificationURL);

            $html = "
            <html>
            <head>
                <style>
                    body { font-family: 'Helvetica', Arial, sans-serif; color: #333; margin: 0; padding: 0; background-color: #ffffff; }
                    .document-container { padding: 40px; position: relative; background: #fff; }
                    .header { text-align: center; border-bottom: 3px solid #183055; padding-bottom: 15px; margin-bottom: 25px; }
                    .header h1 { color: #183055; margin: 0; font-size: 24px; text-transform: uppercase; }
                    .header p { margin: 5px 0; font-size: 14px; }
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
                        <h1>MEDICAL CERTIFICATE</h1>
                        <p>Pusat Kesihatan Universiti | UTHM</p>
                        <p style='font-size: 12px; color: #555;'>Document ID: MCUTHM" . str_pad($newMCID, 6, "0", STR_PAD_LEFT) . "</p>
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
                                <div class='label'>Start Date</div>
                                <div class='value'>" . $startDateStr . "</div>
                            </td>
                            <td width='50%'>
                                <div class='label'>End Date</div>
                                <div class='value'>" . $endDateStr . "</div>
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

            // Penghantaran Emel
            try {
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
                $mail->Subject = 'Digital MC - ' . strtoupper($patientName);
                $mail->Body = "Hello $patientName,<br><br>Attached is your digital MC. ID: " . "MCUTHM" . str_pad($newMCID, 6, "0", STR_PAD_LEFT);
                
                $mail->addStringAttachment($pdfOutput, "MC_{$patientNRIC}.pdf");
                $mail->send();
            } catch (Exception $e) {
                error_log("Email Error: " . $mail->ErrorInfo);
            }
        }

        header("Location: create_mc.php?msg=success");
        exit();

    } catch (Exception $e) { 
        $conn->rollback(); 
        die("Error: " . $e->getMessage()); 
    }
}
?>