<?php
require '../vendor/autoload.php';

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

$freshHash = "";
$rawDataString = "";
$detectedType = "None";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['doc'])) {
    try {
        $pdfFile = $_FILES['doc']['tmp_name'];
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($pdfFile);
        
        $text = $pdf->getText();
        $cleanText = preg_replace('/\s+/', ' ', $text);

        // 1. Ekstrak NRIC
        preg_match('/\d{12}/', $cleanText, $nricMatch);
        $nric = $nricMatch[0] ?? "";

        // 2. Ekstrak Metadata Tersembunyi (DID & GEN_TIME)
        // Format: SEAL_DID:X | GEN_TIME:HH:MM:SS
        $doctorID = "";
        $genTime = "";
        if (preg_match('/SEAL_DID:(\d+) \| GEN_TIME:(\d{2}:\d{2}:\d{2})/', $cleanText, $metaMatch)) {
            $doctorID = $metaMatch[1];
            $genTime  = $metaMatch[2];
        }

        // 3. Logik Hashing Mengikut Struktur Baru
        if (preg_match('/MEDICAL CERTIFICATE/i', $cleanText)) {
            $detectedType = "MEDICAL CERTIFICATE";
            
            preg_match_all('/\d{2} [A-Z][a-z]{2} \d{4}/', $cleanText, $dateMatches);
            $startDate = $dateMatches[0][0] ?? "";
            $endDate = $dateMatches[0][1] ?? "";
            
            preg_match('/\"(.*?)\"/', $cleanText, $diagMatch);
            $diagnosis = strtoupper(trim($diagMatch[1] ?? ""));

            // STRUKTUR MC: NRIC + Start + End + Diag + DID + Salt(GEN_TIME)
            $rawDataString = trim($nric) . trim($startDate) . trim($endDate) . $diagnosis . trim($doctorID) . trim($genTime);
            $freshHash = hash('sha256', $rawDataString);

        } else if (preg_match('/TIME[- ]SLIP/i', $cleanText)) {
            $detectedType = "TIME-SLIP";
            
            preg_match('/\d{2} [A-Z][a-z]{2} \d{4}/', $cleanText, $dateMatch);
            $visitDate = $dateMatch[0] ?? "";

            preg_match_all('/\d{2}:\d{2} [AP]M/', $cleanText, $timeMatches);
            $timeIn = $timeMatches[0][0] ?? "";
            $timeOut = $timeMatches[0][1] ?? "";
            
            preg_match('/\"(.*?)\"/', $cleanText, $diagMatch);
            $diagnosis = strtoupper(trim($diagMatch[1] ?? ""));

            // STRUKTUR TS: NRIC + VisitDate + In + Out + DID + Salt(GEN_TIME)
            $rawDataString = trim($nric) . trim($visitDate) . trim($timeIn) . trim($timeOut) . trim($doctorID) . trim($genTime);
            $freshHash = hash('sha256', $rawDataString);
        }

    } catch (Exception $e) {
        $freshHash = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SEAL | Debugger V2 Refined</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light p-5">
    <div class="container bg-white p-4 shadow-sm rounded" style="max-width: 800px;">
        <h3 class="mb-3 text-primary">Hash Debugger (Salt-Aware)</h3>
        <p class="text-muted small">Memastikan hash sepadan dengan mengambil kira maklumat GEN_TIME (Salt).</p>
        <hr>
        
        <form method="POST" enctype="multipart/form-data" class="mb-4">
            <input type="file" name="doc" class="form-control mb-2" accept="application/pdf" required>
            <button type="submit" class="btn btn-dark w-100">Calculate Fresh Hash</button>
        </form>

        <?php if ($freshHash): ?>
            <div class="p-3 border rounded bg-light">
                <div class="mb-2"><strong>Detected:</strong> <?php echo $detectedType; ?></div>
                <div class="mb-2"><strong>Raw Data String:</strong></div>
                <div class="p-2 bg-white border rounded font-monospace mb-3" style="word-break: break-all; color: #d63384; font-size: 0.9rem;">
                    <?php echo $rawDataString; ?>
                </div>
                <div class="mb-2"><strong>SHA-256 Hash:</strong></div>
                <div class="alert alert-info font-monospace" style="word-break: break-all;">
                    <?php echo $freshHash; ?>
                </div>
                <div class="small text-muted">
                    Nota: Pastikan string Raw Data di atas sama sebiji dengan string dalam pangkalan data.
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>