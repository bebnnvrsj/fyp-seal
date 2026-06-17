<?php
require '../db_connect.php';
require '../vendor/autoload.php';

session_start();

// Only verifier can log in
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'verifier') {
    header("Location: verify_document.php?result=error&message=Unauthorized+Access");
    exit();
}

try {
    // =========================================================================
    // MANUAL ENTRY BACKUP BYPASS (PYTHON ENGINE FALLBACK)
    // =========================================================================
    if (isset($_POST['doc_id']) && !empty($_POST['doc_id'])) {
        $inputID = strtoupper(trim($_POST['doc_id']));
        
        // Extract numeric ID from formatted document ID (e.g., MCUTHM000004 → 4)
        $numericID = 0;
        if (preg_match('/(?:MCUTHM|TSUTHM)0*([1-9][0-9]*|0)/i', $inputID, $matches)) {
            $numericID = (int)$matches[1];
        }

        // Retrieve original document hash from database using extracted ID
        $search_sql = "SELECT documentHash FROM mc WHERE mcID = ? 
                       UNION ALL 
                       SELECT documentHash FROM timeslip WHERE slipID = ?";
        $src_stmt = $conn->prepare($search_sql);
        $src_stmt->bind_param("ii", $numericID, $numericID);
        $src_stmt->execute();
        $src_res = $src_stmt->get_result()->fetch_assoc();

        if ($src_res) {
            // If document exists, redirect with original hash for verification overlay
            header("Location: verify_document.php?result=success&hash=" . urlencode($src_res['documentHash']));
            exit;
        } else {
            // If document not found, return not_found status
            header("Location: verify_document.php?result=not_found&hash=" . urlencode($inputID));
            exit;
        }
    }

    // =========================================================================
    // MAIN FLOW: PDF UPLOAD → Send file to Python Flask /process-pdf engine
    // =========================================================================
    $verifyType = isset($_POST['verify_type']) ? trim($_POST['verify_type']) : "pdf";
    $rawInputHash = ""; // Will be replaced with OCR result from Python engine

    if ($verifyType === 'pdf') {
        if (!isset($_FILES['pdf_doc'])) {
            header("Location: verify_document.php?result=error&message=No+PDF+file+detected");
            exit;
        }

        $pdfPath = $_FILES['pdf_doc']['tmp_name'];

        // Send PDF file to Python Flask API for OCR and hash extraction
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://seal-pdf-engine.onrender.com/process-pdf');        
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
            
            // Extract OCR generated hash from Python engine
            $rawInputHash = trim($resultData['ocr_hash']); 
            
            // Capture extracted document ID from Python engine if available
            if (isset($resultData['extracted_id']) && $resultData['extracted_id'] !== 'UNKNOWN') {
                $_POST['extracted_hash'] = trim($resultData['extracted_id']); 
            }
        } else {
            $msg = isset($resultData['message']) ? $resultData['message'] : "Failed to parse PDF data structural alignment";
            header("Location: verify_document.php?result=error&message=" . urlencode($msg));
            exit();
        }
    } else {
        header("Location: verify_document.php?result=error&message=Invalid+Verification+Type");
        exit();
    }

    // Ensure OCR hash is not empty
    if (empty($rawInputHash)) {
        header("Location: verify_document.php?result=not_found&message=Cryptographic+Hash+Generation+Failed");
        exit;
    }

    // =========================================================================
    // 🛡️ HANDLE INVALID DOCUMENT TYPE FROM PYTHON ENGINE
    // =========================================================================
    if ($rawInputHash === "INVALID_DOCUMENT_TYPE") {
        $verifierID = $_SESSION['userID'];
        
        // Log invalid document attemot
        $log_sql = "INSERT INTO verificationlog (verifierID, documentID, verificationStatus, verificationDate) VALUES (?, 0, 'Not Found', NOW())";
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->bind_param("i", $verifierID);
        $log_stmt->execute();

        // "No Record Found" response
        header("Location: verify_document.php?result=not_found&hash=UNREGISTERED_DOCUMENT");
        exit;
    }

    // Strict cleanup of hash input
    $qrHash = preg_replace('/^0x/i', '', trim($rawInputHash));
    if (preg_match('/([a-fA-F0-9]{64})/', $qrHash, $matches)) {
        $qrHash = $matches[1];
    }
    $qrHash = strtolower($qrHash);

    // =========================================================================
    // DATABASE VERIFICATION (SOURCE OF TRUTH)
    // =========================================================================
    $sql = "SELECT combined.* FROM (
                SELECT 'MC' as type, LOWER(TRIM(REPLACE(documentHash, '0x', ''))) as cleanDocHash, LOWER(REPLACE(transactionHash, '0x', '')) as cleanTxHash, documentHash, status, doctorID, mcID as docID, m.createdAt FROM mc m
                UNION ALL
                SELECT 'TIMESLIP' as type, LOWER(TRIM(REPLACE(documentHash, '0x', ''))) as cleanDocHash, LOWER(REPLACE(transactionHash, '0x', '')) as cleanTxHash, documentHash, status, doctorID, slipID as docID, t.createdAt FROM timeslip t
            ) AS combined WHERE combined.cleanDocHash = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $qrHash);
    $stmt->execute();
    $dbResult = $stmt->get_result()->fetch_assoc();

    // =========================================================================
    // 🚨 TAMPER DETECTION LOGIC (DATA MISMATCH HANDLING)
    // =========================================================================
    if (!$dbResult) {
        $verifierID = $_SESSION['userID'];
        $detectedDocID = 0; 
        
        $inputID = "";
        // Extract ID from manual input if available
        if (isset($_POST['doc_id']) && !empty($_POST['doc_id'])) {
            $inputID = strtoupper(trim($_POST['doc_id']));
        } 
        elseif (isset($_POST['extracted_hash']) && !empty($_POST['extracted_hash']) && $_POST['extracted_hash'] !== 'FORCE_DECODE_VIA_PYTHON') {
            $inputID = strtoupper(trim($_POST['extracted_hash']));
        }

        // Extract numeric ID from document string
        if (!empty($inputID)) {
            if (preg_match('/(?:MCUTHM|TSUTHM)0*([1-9][0-9]*|0)/i', $inputID, $matches)) {
                $numericID = (int)$matches[1];
            } else {
                $numericID = intval(preg_replace('/[^0-9]/', '', $inputID));
            }
            
            // Validate if ID exists in database
            $check_sql = "SELECT mcID as realID FROM mc WHERE mcID = ? 
                          UNION ALL 
                          SELECT slipID as realID FROM timeslip WHERE slipID = ?";
            $chk_stmt = $conn->prepare($check_sql);
            $chk_stmt->bind_param("ii", $numericID, $numericID);
            $chk_stmt->execute();
            $chk_res = $chk_stmt->get_result()->fetch_assoc();
            
            if ($chk_res) {
                $detectedDocID = $chk_res['realID']; // Berjaya tangkap ID tulen (Contoh: ID 4)
                $docType = $chek_res['type'];
            }
        }

        // Log tampered verification attempt
        $log_sql = "INSERT INTO verificationlog (verifierID, documentID, verificationStatus, extractedHash, verificationDate) VALUES (?, ?, 'Tampered', ?, NOW())";
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->bind_param("iis", $verifierID, $detectedDocID, $qrHash);
        $log_stmt->execute();

        // ─── 🆕 Audit log for tampered document detection (TAMPERED!) ───
        $auditAction = "VERIFY_TAMPERED";
        $formattedDocID = ($docType === 'MC') ? "MCUTHM" . str_pad($detectedDocID, 6, "0", STR_PAD_LEFT) : "TSUTHM" . str_pad($detectedDocID, 6, "0", STR_PAD_LEFT);
        if ($detectedDocID === 0) { $formattedDocID = "UNKNOWN_ID"; }
        
        $auditResource = "Verifier (" . $_SESSION['name'] . ") scanned a TAMPERED/FORGED document. Reference ID: " . $formattedDocID . " | Corrupted Hash: 0x" . substr($qrHash, 0, 16) . "...";

        $sql_audit = "INSERT INTO auditlog (userID, action, resource, timestamp) VALUES (?, ?, ?, NOW())";
        $stmt_audit = $conn->prepare($sql_audit);
        $stmt_audit->bind_param("iss", $verifierID, $auditAction, $auditResource);
        $stmt_audit->execute();

        // Redirect with tampered flag for UI overlay display
        header("Location: verify_document.php?result=success&tampered=true&hash=" . urlencode($qrHash) . "&reference_id=" . urlencode($detectedDocID));
        exit;
    }
    
    // Additional safeguard check for corrupted hash mismatch
    $realDocHash = strtolower(preg_replace('/^0x/i', '', trim($dbResult['documentHash'])));
    if ($qrHash !== $realDocHash) {
        header("Location: verify_document.php?result=success&tampered=true&hash=" . $dbResult['documentHash']);
        exit;
    }

    // =========================================================================
    // ⛓️ BLOCKCHAIN VERIFICATION CHECK (SEPOLIA LIVE LEDGER)
    // =========================================================================
    $blockchainVerified = false; 
    $blockchainTimestamp = 0; 
    
    $api_url = "https://seal-backend-hakf.onrender.com/verify-on-blockchain/" . $dbResult['documentHash'];
    $ctx = stream_context_create(['http' => ['timeout' => 8]]);
    $bcResponse = @file_get_contents($api_url, false, $ctx);

    if ($bcResponse) {
        $bcData = json_decode($bcResponse, true);
        if (isset($bcData['isValid']) && $bcData['isValid'] === true) {
            $blockchainVerified = true;
            if (isset($bcData['timestamp'])) {
                $blockchainTimestamp = intval(trim($bcData['timestamp']));
            }
        }
    }

    // =========================================================================
    // SUCCESS PATH: AUTHENTIC DOCUMENT VERIFICATION
    // =========================================================================
    $verifierID = $_SESSION['userID']; 
    $cleanDocID = $dbResult['docID'];

    $log_sql = "INSERT INTO verificationlog (verifierID, documentID, verificationStatus, verificationDate) VALUES (?, ?, 'Authentic', NOW())"; 
    $log_stmt = $conn->prepare($log_sql); 
    $log_stmt->bind_param("ii", $verifierID, $cleanDocID); 
    $log_stmt->execute(); 
    
    // ─── 🆕 INTERVENTI AUDIT LOG GLOBAL (KES AUTHENTIC SAH!) ───
    $auditAction = "VERIFY_AUTHENTIC";
    $formattedDocID = ($dbResult['type'] === 'MC') ? "MCUTHM" . str_pad($cleanDocID, 6, "0", STR_PAD_LEFT) : "TSUTHM" . str_pad($cleanDocID, 6, "0", STR_PAD_LEFT);
    $auditResource = "Verifier (" . $_SESSION['name'] . ") successfully verified document " . $formattedDocID . " for Patient: " . strtoupper($dbResult['patientName']) . " (Status: Authentic & Secure)";

    $sql_audit = "INSERT INTO auditlog (userID, action, resource, timestamp) VALUES (?, ?, ?, NOW())";
    $stmt_audit = $conn->prepare($sql_audit);
    $stmt_audit->bind_param("iss", $verifierID, $auditAction, $auditResource);
    $stmt_audit->execute();
    
    $finalTimestamp = time();
    if ($blockchainTimestamp > 1704038400) {
        $finalTimestamp = $blockchainTimestamp;
    } else if (isset($dbResult['createdAt']) && !empty($dbResult['createdAt'])) {
        $finalTimestamp = strtotime($dbResult['createdAt']); 
    }

    header("Location: verify_document.php?result=success&hash=" . $dbResult['documentHash'] . "&bc_time=" . urlencode($finalTimestamp)); 
    exit();

} catch (Exception $e) {
    header("Location: verify_document.php?result=error&message=" . urlencode($e->getMessage()));
}
exit();
?>