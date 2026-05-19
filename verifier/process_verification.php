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
    // KEMBALI KEPADA $_POST SAHAJA: Menyekat terus sebarang cubaan imbasan daripada kamera luar peranti (Mod 1 Di-disabled)
    $verifyType = isset($_POST['verify_type']) ? trim($_POST['verify_type']) : "pdf";
    $rawInputHash = isset($_POST['extracted_hash']) ? trim($_POST['extracted_hash']) : "";

    if ($verifyType === 'pdf' && !isset($_FILES['pdf_doc'])) {
        header("Location: verify_document.php?result=error&message=No+file+detected");
        exit;
    }

    if (empty($rawInputHash)) {
        header("Location: verify_document.php?result=not_found&message=QR+Hash+Missing");
        exit;
    }

    $qrHash = "";
    // Proses pengekstrakan URL hanya sah jika dihantar melalui POST dari dalam sistem (Mod 2)
    if ($verifyType === 'camera' && (strpos($rawInputHash, 'http') !== false || strpos($rawInputHash, 'payload=') !== false)) {
        $urlParts = parse_url($rawInputHash);
        if (isset($urlParts['query'])) {
            parse_str($urlParts['query'], $queryParams);
            if (isset($queryParams['payload'])) {
                $decodedPayload = base64_decode(trim($queryParams['payload']));
                $payloadData = explode('|', $decodedPayload);
                if (count($payloadData) === 4) {
                    $qrHash = trim($payloadData[0]);
                }
            } else if (isset($queryParams['hash'])) {
                $qrHash = trim($queryParams['hash']);
            }
        }
        if (empty($qrHash)) { $qrHash = $rawInputHash; }
    } else {
        $qrHash = $rawInputHash;
    }

    $qrHash = preg_replace('/^0x/i', '', trim($qrHash));
    if (strlen($qrHash) > 64) { $qrHash = substr($qrHash, -64); }
    $qrHash = strtolower($qrHash);

    // Carian Data Ground Truth di Database
    $sql = "SELECT combined.* FROM (
                SELECT 'MC' as type, LOWER(REPLACE(documentHash, '0x', '')) as cleanDocHash, LOWER(REPLACE(transactionHash, '0x', '')) as cleanTxHash, documentHash, status, doctorID, mcID as docID FROM mc
                UNION ALL
                SELECT 'TIMESLIP' as type, LOWER(REPLACE(documentHash, '0x', '')) as cleanDocHash, LOWER(REPLACE(transactionHash, '0x', '')) as cleanTxHash, documentHash, status, doctorID, slipID as docID FROM timeslip
            ) AS combined WHERE combined.cleanDocHash = ? OR combined.cleanTxHash = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $qrHash, $qrHash);
    $stmt->execute();
    $dbResult = $stmt->get_result()->fetch_assoc();

    if (!$dbResult) {
        header("Location: verify_document.php?result=not_found&hash=" . urlencode($qrHash));
        exit;
    }

    $realDocHash = $dbResult['documentHash'];

    // Semakan Integriti Hash Kriptografi
    if ($qrHash !== strtolower(preg_replace('/^0x/i', '', trim($realDocHash))) && $qrHash !== strtolower(preg_replace('/^0x/i', '', trim($dbResult['cleanTxHash'])))) {
        $verifierID = $_SESSION['userID']; $cleanDocID = $dbResult['docID'];
        $log_sql = "INSERT INTO verificationlog (verifierID, documentID, verificationStatus, verificationDate) VALUES (?, ?, 'Tampered', NOW())";
        $log_stmt = $conn->prepare($log_sql); $log_stmt->bind_param("is", $verifierID, $cleanDocID); $log_stmt->execute();
        header("Location: verify_document.php?result=success&tampered=true&hash=" . $realDocHash);
        exit;
    }

    // Semakan Rantaian Blok Sepolia
    $blockchainVerified = true;
    $api_url = "http://localhost:3000/verify-on-blockchain/" . $realDocHash;
    $ctx = stream_context_create(['http' => ['timeout' => 5]]);
    $bcResponse = @file_get_contents($api_url, false, $ctx);

    if ($bcResponse) {
        $bcData = json_decode($bcResponse, true);
        if (isset($bcData['isValid']) && $bcData['isValid'] === true) { $blockchainVerified = true; }
    }

    if (!$blockchainVerified) {
        header("Location: verify_document.php?result=success&tampered=true&hash=" . $realDocHash);
    } else {
        $verifierID = $_SESSION['userID']; $cleanDocID = $dbResult['docID'];
        $dbStatus = strtoupper(trim($dbResult['status'] ?? ''));
        $logStatus = ($dbStatus === 'REVOKED') ? 'Revoked' : 'Authentic';

        $log_sql = "INSERT INTO verificationlog (verifierID, documentID, verificationStatus, verificationDate) VALUES (?, ?, ?, NOW())";
        $log_stmt = $conn->prepare($log_sql); $log_stmt->bind_param("iss", $verifierID, $cleanDocID, $logStatus); $log_stmt->execute();

        $revokedStatus = ($dbStatus === 'REVOKED') ? '&revoked=true' : '';
        header("Location: verify_document.php?result=success&hash=" . $realDocHash . $revokedStatus);
    }

} catch (Exception $e) {
    header("Location: verify_document.php?result=error&message=" . urlencode($e->getMessage()));
}
exit();
?>