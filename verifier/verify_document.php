<?php
session_start();
date_default_timezone_set("Asia/Kuala_Lumpur");

// Hanya verifier yang telah log masuk dibenarkan akses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'verifier') {
    header("Location: ../login/login.php");
    exit();
}
require '../db_connect.php'; 
$verifierName = isset($_SESSION['name']) ? $_SESSION['name'] : 'Verifier';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Document - SEAL</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js"></script>

    <style>
        :root {
            --sidebar-width: 260px;
            --header-bg: #2b7a9e;
            --main-bg: linear-gradient(to bottom, #caf0f8, #90e0ef, #48cae4);
            --dark-blue: #183055;
            --status-success: #28a745;   
            --status-tampered: #dc3545;  
            --status-revoked: #fd7e14;   
            --status-failed: #6c757d;    
        }

        body { margin: 0; font-family: "Segoe UI", Tahoma, sans-serif; background: var(--main-bg); min-height: 100vh; display: flex; overflow-x: hidden; }

        /* ====== SIDEBAR & WRAPPER ====== */
        .sidebar { width: var(--sidebar-width); height: 100vh; background-color: var(--dark-blue); color: white; position: fixed; left: 0; top: 0; transition: transform 0.3s ease-in-out; z-index: 1005; display: flex; flex-direction: column; }
        .sidebar.closed { transform: translateX(-100%); }
        .sidebar-header { padding: 20px; background-color: #122542; display: flex; align-items: center; gap: 15px; font-weight: bold; }
        .sidebar-menu { list-style: none; padding: 0; margin: 20px 0; }
        .sidebar-menu li a { padding: 15px 25px; display: flex; align-items: center; gap: 15px; color: #d1d9e6; text-decoration: none; transition: 0.2s; }
        .sidebar-menu li a:hover { background-color: #2b7a9e; color: white; }
        .sidebar-menu li a.active { background-color: #2b7a9e; color: white; border-left: 4px solid #fff; }

        .main-wrapper { flex: 1; display: flex; flex-direction: column; margin-left: var(--sidebar-width); transition: margin-left 0.3s ease-in-out, width 0.3s ease-in-out; width: 100%; box-sizing: border-box; }
        .main-wrapper.full-width { margin-left: 0 !important; }

        .header { height: 56px; background-color: var(--header-bg); display: flex; align-items: center; justify-content: space-between; padding: 0 24px; color: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); position: relative; z-index: 1000; }
        .toggle-btn { cursor: pointer; font-size: 20px; }

        .container { width: 95%; max-width: 1000px; margin: 30px auto; padding: 0 20px; display: flex; flex-direction: column; gap: 25px; }

        /* Page Hero */
        .page-hero { background: white; border-radius: 15px; padding: 35px; display: flex; align-items: center; gap: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .hero-text h1 { margin: 0; color: var(--dark-blue); font-size: 24px; }
        .hero-text p { margin: 5px 0; color: #666; font-size: 14px; }

        /* Cards Layout */
        .card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); animation: fadeIn 0.3s ease; }
        .card h2 { font-size: 18px; color: var(--dark-blue); margin-top: 0; border-bottom: 2px solid #f0f0f0; padding-bottom: 12px; margin-bottom: 20px; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .scan-box { border: 2px dashed #2b7a9e; border-radius: 15px; padding: 45px 30px; text-align: center; cursor: pointer; transition: 0.3s; color: #555; background: #fbfefe; }
        .scan-box:hover { border-color: var(--dark-blue); background: #f1f8ff; color: var(--header-bg); }
        
        input { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #ddd; font-size: 14px; box-sizing: border-box; }
        .verify-btn { background: var(--header-bg); color: white; border: none; padding: 12px; width: 100%; border-radius: 8px; font-weight: bold; cursor: pointer; margin-top: 10px; transition: 0.3s; }
        .verify-btn:hover { background: var(--dark-blue); }

        /* Overlay Layouts */
        .status-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); display: flex; align-items: center; justify-content: center; z-index: 9999; padding: 20px; backdrop-filter: blur(5px); }
        .highlight-card { background: white; width: 100%; max-width: 550px; border-radius: 20px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.4); animation: zoomIn 0.3s ease-out; }
        .highlight-header { padding: 40px 20px; text-align: center; color: white; }
        .highlight-header i { font-size: 60px; margin-bottom: 15px; }
        .highlight-header h2 { margin: 0; font-size: 24px; text-transform: uppercase; letter-spacing: 1px; }
        .highlight-body { padding: 30px; max-height: 70vh; overflow-y: auto; }

        .detail-item { display: flex; justify-content: space-between; padding: 12px; background: #f8f9fa; border-radius: 8px; margin-top: 10px; font-size: 13px; }
        .close-overlay { display: block; width: 100%; padding: 15px; background: var(--dark-blue); color: white; text-align: center; text-decoration: none; font-weight: bold; border: none; cursor: pointer; transition: 0.2s; }
        .close-overlay:hover { background: #122542; }

        .badge { padding: 6px 14px; border-radius: 50px; font-size: 12px; font-weight: 700; display: inline-block; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .status-authentic { background-color: var(--status-success) !important; color: white !important; }
        .status-invalid { background-color: var(--status-tampered) !important; color: white !important; }

        .info-row { display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #fdfdfe; border: 1px solid #edf2f7; border-radius: 8px; margin-top: 10px; }
        .info-label { font-size: 13px; font-weight: 600; color: #4a5568; }
        .info-value { font-size: 13px; font-weight: 700; color: var(--dark-blue); text-align: right; }

        .blockchain-proof-box { background: #f0fff4; border: 1px solid #c6f6d5; border-radius: 10px; padding: 15px; margin-top: 15px; font-size: 13px; text-align: left; }
        .blockchain-proof-title { color: #22543d; font-weight: 700; display: flex; align-items: center; gap: 8px; margin-bottom: 10px; font-size: 14px; }
        
        .forensic-alert-box { background: #fff5f5; border: 1px dashed #feb2b2; border-radius: 12px; padding: 18px; margin-bottom: 15px; text-align: left; }
        .forensic-title { color: #c53030; font-weight: 700; font-size: 14px; display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
        
        .hash-container { background: #ffffff; border: 1px solid #e2e8f0; font-family: 'Courier New', Courier, monospace; font-size: 11px; padding: 8px; border-radius: 6px; word-break: break-all; margin-top: 4px; color: #4a5568; box-shadow: inset 0 1px 3px rgba(0,0,0,0.05); }

        @keyframes zoomIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        @media (max-width: 768px) { 
            .page-hero { flex-direction: column; text-align: center; gap: 20px; padding: 25px; } 
            .hero-text h1 { font-size: 22px; }
        }
    </style>
</head>
<body>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header"><i class="fa-solid fa-hospital-user"></i> <span>SEAL</span></div>
    <ul class="sidebar-menu">
        <li><a href="home_verifier.php"><i class="fa-solid fa-house"></i> Home</a></li>
        <li><a href="verify_document.php" class="active"><i class="fa-solid fa-magnifying-glass"></i> Verify Document</a></li>       
        <li><a href="verification_history.php"><i class="fa-solid fa-clock-rotate-left"></i> Verification History</a></li>
        <li><a href="../profile.php"><i class="fa-solid fa-user-gear"></i> Profile Settings</a></li>
        <li><a href="../login/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
    </ul>
</div>

<div class="main-wrapper" id="mainWrapper">
    <div class="header">
        <div class="header-left">
            <i class="fa-solid fa-bars toggle-btn" onclick="toggleSidebar()"></i>
            <span style="font-weight: 600; margin-left: 15px;">Verifier Portal</span>
        </div>
    </div>

    <div class="container">
        <div class="page-hero">
            <div style="font-size: 50px; color: var(--header-bg);"><i class="fa-solid fa-shield-halved"></i></div>
            <div class="hero-text">
                <h1>Secure Verification Workspace</h1>
                <p>Verify medical document authenticity via digital PDF smart uploads or manual entry checks.</p>
            </div>
        </div>

        <div class="card">
            <h2><i class="fa-solid fa-file-pdf"></i> Smart PDF Upload Verification</h2>
            <div class="scan-box" onclick="document.getElementById('pdf_doc_input').click()">
                <i class="fa-solid fa-cloud-arrow-up" style="font-size: 40px; color: var(--header-bg); display: block; margin-bottom: 12px;"></i>
                <span id="file-name-display" style="font-weight: 600;">Click to Upload & Auto-Scan Medical PDF</span>
                <input type="file" id="pdf_doc_input" accept=".pdf" style="display: none;" onchange="handleSmartPDFUpload(event)">
            </div>
            
            <form id="hidden-verify-form" action="process_verification.php" method="POST" enctype="multipart/form-data" style="display:none;">
                <input type="hidden" name="extracted_hash" id="final_extracted_hash">
                <input type="file" name="pdf_doc" id="hidden_pdf_file">
                <input type="hidden" name="verify_type" id="verify_type_field" value="pdf">
            </form>
        </div>

        <div class="card">
            <h2><i class="fa-solid fa-keyboard"></i> Manual Entry</h2>
            <form action="process_verification.php" method="POST">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 8px;">Enter Document Secure ID</label>
                    <input type="text" name="doc_id" placeholder="e.g. MCUTHM000005" pattern="(MCUTHM|TSUTHM)\d{6}" oninput="this.value = this.value.toUpperCase()" required>
                </div>
                <button type="submit" class="verify-btn"><i class="fa-solid fa-magnifying-glass"></i> Search Document</button>
            </form>
        </div>
    </div>
</div>

<?php 
if (isset($_GET['result'])): 
    $resType = $_GET['result'];
    $isTampered = isset($_GET['tampered']) && $_GET['tampered'] == 'true';
    
    $cardColor = "var(--status-failed)"; $statusIcon = "fa-circle-question"; $statusText = "No Record Found";

    if ($isTampered) { $cardColor = "var(--status-tampered)"; $statusIcon = "fa-triangle-exclamation"; $statusText = "TAMPERED DATA!"; } 
    elseif ($resType == 'success') { $cardColor = "var(--status-success)"; $statusIcon = "fa-circle-check"; $statusText = "Authentic Record Found"; }
?>
<div class="status-overlay" id="resultOverlay">
    <div class="highlight-card" style="position: relative;">
        
        <button onclick="closeResult()" style="position: absolute; top: 15px; right: 20px; background: transparent; border: none; color: rgba(255,255,255,0.7); font-size: 22px; cursor: pointer; transition: 0.2s; z-index: 10;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,0.7)'" title="Close">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="highlight-header" style="background-color: <?php echo $cardColor; ?>;">
            <i class="fa-solid <?php echo $statusIcon; ?>"></i>
            <h2><?php echo $statusText; ?></h2>
        </div>
        
        <div class="highlight-body">
            <?php if (isset($_GET['hash'])): 
                $hash = mysqli_real_escape_string($conn, $_GET['hash']);
                
                if ($resType === 'not_found'): ?>
                    <div style="text-align: center; padding: 20px 10px;">
                        <i class="fa-solid fa-folder-open" style="font-size: 50px; color: var(--status-failed); margin-bottom: 15px; display: block;"></i>
                        <h3 style="color: var(--dark-blue); margin-bottom: 8px;">Document Unregistered</h3>
                        <p style="font-size:13px; color:#666; margin: 0 0 15px 0;">This cryptographic record does not exist anywhere within clinic systems.</p>
                        <div class="hash-container" style="margin-top: 20px; background: #f8f9fa; border: 1px dashed #cbd5e0; text-align: left;">
                            <strong>Generated Hash State:</strong><br>
                            <span style="color: #c53030;">❌ 0x<?php echo htmlspecialchars($_GET['hash']); ?></span>
                        </div>
                    </div>
                <?php else: 
                    // BINA QUERY SILANG SANGAT PINTAR UNTUK KES TAMPERED & AUTHENTIC
                    $refID = isset($_GET['reference_id']) ? intval($_GET['reference_id']) : 0;
                    
                    $sql = "SELECT * FROM (
                                SELECT 'MC' as type, mcID as rawID, CONCAT('MCUTHM', LPAD(m.mcID, 6, '0')) as docID, m.patientName, m.matric_staff_no, dp.name as doctor_name, m.documentHash, m.status, m.startDate as val1, m.endDate as val2 FROM mc m LEFT JOIN doctor_profiles dp ON m.doctorId = dp.doctorId
                                UNION ALL
                                SELECT 'TIMESLIP' as type, slipID as rawID, CONCAT('TSUTHM', LPAD(t.slipID, 6, '0')) as docID, t.patientName, t.matric_staff_no, dp.name as doctor_name, t.documentHash, t.status, t.timeIn as val1, t.timeOut as val2 FROM timeslip t LEFT JOIN doctor_profiles dp ON t.doctorId = dp.doctorId
                            ) AS combined WHERE combined.documentHash = ? OR combined.documentHash = CONCAT('0x', ?) OR combined.rawID = ?";
                    
                    $stmt = $conn->prepare($sql); 
                    $stmt->bind_param("ssi", $hash, $hash, $refID); 
                    $stmt->execute(); 
                    $doc = $stmt->get_result()->fetch_assoc();

                    if ($isTampered): ?>
                        <div class="forensic-alert-box">
                            <div class="forensic-title">
                                <i class="fa-solid fa-fingerprint"></i> Cryptographic Integrity Mismatch Detected
                            </div>
                            <p style="color: #742a2a; font-size: 13px; margin: 0 0 10px 0; font-weight: 500;">
                                Warning: The calculated document fingerprint does not align with the immutable state registered on the blockchain.
                            </p>
                            
                            <div style="margin-top: 10px; font-size: 12px;">
                                <strong>Original Document Secure ID:</strong>
                                <div class="hash-container" style="color: #2b6cb0; background: #ebf8ff; border-color: #bee3f8;">
                                    🔑 0x<?php echo $doc ? htmlspecialchars(str_replace('0x','',$doc['documentHash'])) : 'UNKNOWN_REFERENCE'; ?>
                                </div>
                            </div>
                            
                            <div style="margin-top: 8px; font-size: 12px;">
                                <strong>Corrupted Extracted Hash (Current Upload):</strong>
                                <div class="hash-container" style="color: #c53030; background: #fff;">
                                    ⚠️ 0x<?php echo htmlspecialchars(str_replace('0x','',$hash)); ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($doc): ?>
                        <div class="detail-item" style="background: #eef2f7; border-left: 4px solid <?php echo $cardColor; ?>; margin-bottom: 15px;">
                            <span><i class="fa-solid fa-hashtag"></i> Reference ID:</span>
                            <strong style="color: <?php echo $cardColor; ?>; font-size: 16px;">
                                <?php 
                                if ($isTampered && $refID > 0) {
                                    echo htmlspecialchars($doc['docID']);
                                } else {
                                    echo htmlspecialchars($doc['docID']);
                                }
                                ?>
                            </strong>
                        </div>
                        <div class="detail-item"><span>Patient Name:</span><strong><?php echo strtoupper(htmlspecialchars($doc['patientName'])); ?></strong></div>
                        <div class="detail-item"><span>Matric / Staff No:</span><strong><?php echo strtoupper(htmlspecialchars($doc['matric_staff_no'])); ?></strong></div>
                        <div class="detail-item"><span>Issuing Doctor:</span><strong>Dr. <?php echo htmlspecialchars($doc['doctor_name']); ?></strong></div>
                        
                        <div class="info-row">
                            <div class="info-label"><i class="fas fa-clock"></i> Clinical Timeline:</div>
                            <div class="info-value"><?php echo ($doc['type'] === 'MC') ? date('d M Y', strtotime($doc['val1'])) . " - " . date('d M Y', strtotime($doc['val2'])) : htmlspecialchars($doc['val1']) . " - " . htmlspecialchars($doc['val2']); ?></div>
                        </div>                    
                        
                        <div class="detail-item" style="margin-top: 15px; border-top: 1px dashed #ddd; padding-top: 15px;">
                            <span>Blockchain Ledger Status:</span>
                            <strong style="color:<?php echo $cardColor; ?>;">
                                <?php echo ($isTampered) ? "FAILED ✘ (Data Tampered)" : "VERIFIED ✓ (Secured)"; ?>
                            </strong>
                        </div>

                        <?php if ($resType === 'success' && !$isTampered && !empty($_GET['bc_time'])): 
                            $formattedBCDate = date("d F Y, h:i A", (int)$_GET['bc_time']);
                        ?>
                            <div class="blockchain-proof-box">
                                <div class="blockchain-proof-title">
                                    <i class="fa-solid fa-cubes-blockchain" style="color: #2f855a;"></i> Sepolia Ledger Integrity Verification
                                </div>
                                <div style="margin-bottom: 8px;">
                                    <strong>Date Issued:</strong>
                                    <div class="hash-container">🕒 <?php echo $formattedBCDate; ?></div>
                                </div>
                                <div>
                                    <strong>Immutable Document Secure ID:</strong>
                                    <div class="hash-container" style="color:#2b6cb0;">🔑 <?php echo htmlspecialchars($doc['documentHash']); ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?> 
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('closed');
        document.getElementById('mainWrapper').classList.toggle('full-width');
    }
    function closeResult() {
        document.getElementById('resultOverlay').style.display = 'none';
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    async function handleSmartPDFUpload(event) {
        const file = event.target.files[0]; if (!file) return;
        document.getElementById('file-name-display').innerText = "Scanning: " + file.name + "...";
        try {
            const arrayBuffer = await file.arrayBuffer();
            const loadingTask = pdfjsLib.getDocument({data: arrayBuffer});
            const pdf = await loadingTask.promise;
            
            // Render halaman pertama PDF ke canvas resolusi tinggi untuk memproses pengekstrakan teks
            const page = await pdf.getPage(1);
            const viewport = page.getViewport({ scale: 4.0 }); 
            const canvas = document.createElement('canvas'); 
            const context = canvas.getContext('2d');
            canvas.height = viewport.height; 
            canvas.width = viewport.width;
            context.imageSmoothingEnabled = false;
            
            await page.render({ canvasContext: context, viewport: viewport }).promise;
            
            // Ambil fail lampiran mentah fizikal dan hantar terus ke process_verification.php
            const dataTransfer = new DataTransfer(); 
            dataTransfer.items.add(file);
            document.getElementById('hidden_pdf_file').files = dataTransfer.files;
            document.getElementById('final_extracted_hash').value = "FORCE_DECODE_VIA_PYTHON";
            
            const form = document.getElementById('hidden-verify-form');
            document.getElementById('verify_type_field').value = 'pdf';
            form.submit();
            
        } catch (err) { 
            Swal.fire("System Error", "Failed to parse PDF document structure properly.", "error"); 
        }
    }
</script>
</body>
</html>