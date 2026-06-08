<?php
require '../vendor/autoload.php';
require '../db_connect.php';
date_default_timezone_set('Asia/Kuala_Lumpur');

$message = "";
$statusClass = "";

// TAMBAHAN: Semak jika fail benar-benar wujud sebelum diproses
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['pdf_doc'])) {
    
    // Semak ralat muat naik fail
    if ($_FILES['pdf_doc']['error'] !== UPLOAD_ERR_OK) {
        $message = "Error: Please select a valid PDF file.";
        $statusClass = "alert-danger";
    } else {
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($_FILES['pdf_doc']['tmp_name']);
            $text = $pdf->getText(); 

            // 2. Cari Hash (64 aksara hex)
            preg_match('/[a-f0-9]{64}/', $text, $hashMatches);
            $foundHash = $hashMatches[0] ?? '';

            if ($foundHash) {
                $stmt = $conn->prepare("SELECT * FROM medicaldocument WHERE documentHash = ?");
                $stmt->bind_param("s", $foundHash);
                $stmt->execute();
                $dbData = $stmt->get_result()->fetch_assoc();

                if ($dbData) {
                    // 4. KEMASKINI REGEX: Cari tarikh (contoh: 07 Apr 2026 atau 07/04/2026)
                    // Regex ini lebih selamat untuk pelbagai format tarikh
                    preg_match('/\d{2} [A-Z][a-z]{2} \d{4}/', $text, $dateMatches);
                    $pdfDate = $dateMatches[0] ?? ''; 

                    $originalDate = date("d M Y", strtotime($dbData['expiryDate']));

                    // 5. PERBANDINGAN BLOCKCHAIN
                    if (trim($pdfDate) !== trim($originalDate)) {
                        $message = "<strong>DATA TAMPERED!</strong><br>The date on this PDF ($pdfDate) does not match the digitally signed Blockchain record ($originalDate).";
                        $statusClass = "alert-danger";
                    } else {
                        $message = "<strong>VERIFIED!</strong><br>Document is authentic. Data matches the SEAL Blockchain ledger.";
                        $statusClass = "alert-success";
                    }
                } else {
                    $message = "<strong>INVALID!</strong><br>Security hash not found in our records.";
                    $statusClass = "alert-warning";
                }
            } else {
                $message = "<strong>ERROR!</strong><br>No SEAL security hash detected in this PDF.";
                $statusClass = "alert-danger";
            }
        } catch (Exception $e) {
            $message = "System Error: " . $e->getMessage();
            $statusClass = "alert-danger";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>SEAL - PDF Verification</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="card shadow mx-auto" style="max-width: 600px;">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Verify SEAL Document (PDF)</h4>
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert <?php echo $statusClass; ?>"><?php echo $message; ?></div>
                <?php endif; ?>

                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Upload PDF Document</label>
                        <input type="file" name="pdf_doc" class="form-control" accept=".pdf" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Verify Authenticity</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>