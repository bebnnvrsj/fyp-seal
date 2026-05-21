<?php
require '../db_connect.php';
require '../vendor/autoload.php';

session_start();

// SEKATAN KESELAMATAN EKSLUSIF: Hanya benarkan akses jika verifier sudah log masuk dalam portal
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'verifier') {
    header("Location: verify_document.php?result=error&message=Unauthorized+Access");
    exit();
}

try {
    $verifyType = isset($_POST['verify_type']) ? trim($_POST['verify_type']) : "pdf";
    $rawInputHash = ""; // Kita akan overwrite nilai ini dengan hash segar dari Python Flask

    // =========================================================================
    // JALUR 1: CAMERA SCAN (FULL-PAGE OCR) -> Hantar Gambar ke /process-image
    // =========================================================================
    if ($verifyType === 'camera') {
        if (!isset($_FILES['file'])) {
            header("Location: verify_document.php?result=error&message=No+camera+image+detected");
            exit;
        }
        
        $imagePath = $_FILES['file']['tmp_name'];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:5000/process-image');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'file' => new CURLFile($imagePath, $_FILES['file']['type'], $_FILES['file']['name'])
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            header("Location: verify_document.php?result=error&message=" . urlencode("OCR Server Unreachable"));
            exit();
        }

        $resultData = json_decode($response, true);
        if ($resultData && $resultData['status'] === 'success') {
            $rawInputHash = trim($resultData['ocr_hash']);
        } else {
            $msg = isset($resultData['message']) ? $resultData['message'] : "OCR Reading Failure";
            header("Location: verify_document.php?result=error&message=" . urlencode($msg));
            exit();
        }

    // =========================================================================
    // JALUR 2: UPLOAD PDF -> Hantar Fail .pdf ke /process-pdf (SINI YANG BOCOR TADI!)
    // =========================================================================
    } else if ($verifyType === 'pdf') {
        if (!isset($_FILES['pdf_doc'])) {
            header("Location: verify_document.php?result=error&message=No+PDF+file+detected");
            exit;
        }

        $pdfPath = $_FILES['pdf_doc']['tmp_name'];

        // Hantar fail PDF fizikal ke API Python Flask pintu pagar /process-pdf
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:5000/process-pdf');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'file' => new CURLFile($pdfPath, $_FILES['pdf_doc']['type'], $_FILES['pdf_doc']['name'])
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            header("Location: verify_document.php?result=error&message=" . urlencode("Python PDF Engine Unreachable"));
            exit();
        }

        $resultData = json_decode($response, true);
        if ($resultData && $resultData['status'] === 'success') {
            $rawInputHash = trim($resultData['ocr_hash']); // Ambil freshHash hasil bacaan pdfplumber
        } else {
            $msg = isset($resultData['message']) ? $resultData['message'] : "Failed to parse PDF data structural alignment";
            header("Location: verify_document.php?result=error&message=" . urlencode($msg));
            exit();
        }
    }

    // Memastikan hash hasil dari Python Flask tidak kosong
    if (empty($rawInputHash)) {
        header("Location: verify_document.php?result=not_found&message=Cryptographic+Hash+Generation+Failed");
        exit;
    }

    # ─────────────────────────────────────────────────────────────────────────
    # 🛡️ LOGIK UTAMA CADANGAN ANDA: TANGKAP PENOLAKAN KATA KUNCI DARI PYTHON
    # ─────────────────────────────────────────────────────────────────────────
    if ($rawInputHash === "INVALID_DOCUMENT_TYPE") {
        $verifierID = $_SESSION['userID'];
        $log_sql = "INSERT INTO verificationlog (verifierID, documentID, verificationStatus, verificationDate) VALUES (?, 0, 'Not Found', NOW())";
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->bind_param("i", $verifierID);
        $log_stmt->execute();

        // Paksa buka modal abu-abu "No Record Found" serta-merta!
        header("Location: verify_document.php?result=not_found&hash=UNREGISTERED_DOCUMENT");
        exit;
    }

    // PEMBERSIHAN HASH SECARA KETAT
    $qrHash = preg_replace('/^0x/i', '', trim($rawInputHash));
    if (preg_match('/([a-fA-F0-9]{64})/', $qrHash, $matches)) {
        $qrHash = $matches[1];
    }
    $qrHash = strtolower($qrHash);

    // ─────────────────────────────────────────────────────────────────────────
    // ⚠️ KOD FORENSIK VIVA: Sila letak ini untuk tengok kenapa dia tampered!
    // ─────────────────────────────────────────────────────────────────────────
    // Kod ini akan memberhentikan sistem skrin secara paksa dan tunjuk nilai sebenar.
    // Jika nilai $qrHash di skrin nanti TIDAK SAMA dengan '251b791a...', sah data hancur sebelum SQL!
    // ─────────────────────────────────────────────────────────────────────────
    /*echo "<h3>DEBUG HASHING MEDDOQS</h3>";
    echo "1. Data dari Python (\$rawInputHash): " . htmlspecialchars($rawInputHash) . "<br>";
    echo "2. Hasil Bersih PHP (\$qrHash): " . htmlspecialchars($qrHash) . "<br>";
    echo "3. Panjang (\$qrHash): " . strlen($qrHash) . " karakter<br>";
    die("<br>[SILA PADAM DROP / COMMENT BLOCK INI SELEPAS SEMAK]"); */ 

    // =========================================================================
    // SEMAKAN PANGKALAN DATA (GROUND TRUTH)
    // =========================================================================
    $sql = "SELECT combined.* FROM (
                SELECT 'MC' as type, LOWER(TRIM(REPLACE(documentHash, '0x', ''))) as cleanDocHash, LOWER(REPLACE(transactionHash, '0x', '')) as cleanTxHash, documentHash, status, doctorID, mcID as docID FROM mc
                UNION ALL
                SELECT 'TIMESLIP' as type, LOWER(TRIM(REPLACE(documentHash, '0x', ''))) as cleanDocHash, LOWER(REPLACE(transactionHash, '0x', '')) as cleanTxHash, documentHash, status, doctorID, slipID as docID FROM timeslip
            ) AS combined WHERE combined.cleanDocHash = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $qrHash);
    $stmt->execute();
    $dbResult = $stmt->get_result()->fetch_assoc();

    // ─────────────────────────────────────────────────────────────────────────
    // 🚨 DIBAIKI: LOGIK KHUSUS UNTUK MENANGKAP KES TAMPERED (DATA MISMATCH)
    // ─────────────────────────────────────────────────────────────────────────
    if (!$dbResult) {
        $verifierID = $_SESSION['userID'];
        $detectedDocID = 0; // Lalai jika gagal dikesan langsung
        
        // Strategi Forensik: Tangkap ID teks dari POST manual entry atau parameter tersembunyi
        $inputID = "";
        if (isset($_POST['doc_id']) && !empty($_POST['doc_id'])) {
            $inputID = strtoupper(trim($_POST['doc_id']));
        } elseif (isset($_POST['extracted_hash']) && (strpos($_POST['extracted_hash'], 'MCUTHM') !== false || strpos($_POST['extracted_hash'], 'TSUTHM') !== false)) {
            $inputID = strtoupper(trim($_POST['extracted_hash']));
        }

        // 💡 DIBAIKI: Ekstrak ID nombor tulen menggunakan pattern Regex yang kalis ralat
        if (!empty($inputID)) {
            if (preg_match('/(?:MCUTHM|TSUTHM)0*([1-9][0-9]*|0)/i', $inputID, $matches)) {
                $numericID = (int)$matches[1];
            } else {
                $numericID = intval(preg_replace('/[^0-9]/', '', $inputID));
            }
            
            // Semak sama ada ID angka ini betul-betul wujud dalam pangkalan data induk
            $check_sql = "SELECT mcID as realID FROM mc WHERE mcID = ? 
                          UNION ALL 
                          SELECT slipID as realID FROM timeslip WHERE slipID = ?";
            $chk_stmt = $conn->prepare($check_sql);
            $chk_stmt->bind_param("ii", $numericID, $numericID);
            $chk_stmt->execute();
            $chk_res = $chk_stmt->get_result()->fetch_assoc();
            
            if ($chk_res) {
                $detectedDocID = $chk_res['realID']; // Berjaya tangkap ID tulen (Contoh: 4)
            }
        }

        // Simpan angka ID yang dikesan ke dalam database (Kolum documentID kekal INT)
        $log_sql = "INSERT INTO verificationlog (verifierID, documentID, verificationStatus, verificationDate) VALUES (?, ?, 'Tampered', NOW())";
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->bind_param("ii", $verifierID, $detectedDocID);
        $log_stmt->execute();

        // Paksa hantar status tampered ke frontend
        header("Location: verify_document.php?result=success&tampered=true&hash=" . urlencode($qrHash));
        exit;
    }

    // Jalur kecemasan tambahan sekiranya ada herotan string (Double-Protection)
    $realDocHash = strtolower(preg_replace('/^0x/i', '', trim($dbResult['documentHash'])));
    if ($qrHash !== $realDocHash) {
        header("Location: verify_document.php?result=success&tampered=true&hash=" . $dbResult['documentHash']);
        exit;
    }

    // =========================================================================
    // SEMAKAN RANTAIAN BLOK SEPOLIA
    // =========================================================================
    $blockchainVerified = false; 
    $blockchainTimestamp = time();
    
    $api_url = "http://localhost:3000/verify-on-blockchain/" . $dbResult['documentHash'];
    $ctx = stream_context_create(['http' => ['timeout' => 5]]);
    $bcResponse = @file_get_contents($api_url, false, $ctx);

    if ($bcResponse) {
        $bcData = json_decode($bcResponse, true);
        if (isset($bcData['isValid']) && $bcData['isValid'] === true) {
            $blockchainVerified = true;
            if (isset($bcData['timestamp'])) {
                $blockchainTimestamp = trim($bcData['timestamp']);
            }
        }
    }

    // 🟢 JALUR UTAMA UNTUK MENYALAKAN MODAL HIJAU (AUTHENTIC)
    $verifierID = $_SESSION['userID']; 
    $cleanDocID = $dbResult['docID'];

    $log_sql = "INSERT INTO verificationlog (verifierID, documentID, verificationStatus, verificationDate) VALUES (?, ?, 'Authentic', NOW())"; 
    $log_stmt = $conn->prepare($log_sql); 
    $log_stmt->bind_param("ii", $verifierID, $cleanDocID); 
    $log_stmt->execute(); 
    
    header("Location: verify_document.php?result=success&hash=" . $dbResult['documentHash'] . "&bc_time=" . urlencode($blockchainTimestamp)); 
    exit();

} catch (Exception $e) {
    header("Location: verify_document.php?result=error&message=" . urlencode($e->getMessage()));
}
exit();
?>