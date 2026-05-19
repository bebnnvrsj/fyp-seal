<?php
session_start();
// Only verifier can access this page
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
    <script src="https://unpkg.com/html5-qrcode"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>

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

        /* ====== INTERACTIVE TAB SWITCHER ====== */
        .tab-wrapper { display: flex; background: rgba(255,255,255,0.6); padding: 5px; border-radius: 30px; gap: 5px; max-width: 450px; margin: 0 auto; width: 100%; box-sizing: border-box; }
        .tab-btn { flex: 1; padding: 12px; border: none; background: transparent; border-radius: 25px; font-weight: 700; color: var(--dark-blue); cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 13px; }
        .tab-btn.active { background: var(--header-bg); color: white; box-shadow: 0 4px 10px rgba(43,122,158,0.3); }

        /* Cards & View Management */
        .card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); display: none; animation: fadeIn 0.3s ease; }
        .card.active { display: block; }
        .card h2 { font-size: 18px; color: var(--dark-blue); margin-top: 0; border-bottom: 2px solid #f0f0f0; padding-bottom: 12px; margin-bottom: 20px; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* Camera Lock Box Styling (NEW Improvement) */
        .camera-lock-box { text-align: center; padding: 40px 20px; background: #fff5f5; border: 2px dashed #fc8181; border-radius: 12px; color: #c53030; display: none; }
        .camera-lock-box i { font-size: 45px; margin-bottom: 15px; }

        /* Camera Viewport Tweaks */
        #reader-viewport { width: 100%; max-width: 450px; margin: 0 auto; border: none !important; background: #fafafa; }
        #reader-viewport button { background-color: var(--header-bg) !important; color: white !important; border: none !important; padding: 12px 24px !important; border-radius: 8px !important; font-weight: bold; cursor: pointer !important; margin: 15px auto !important; display: block !important; }

        .scan-box { border: 2px dashed #ccc; border-radius: 15px; padding: 30px; text-align: center; cursor: pointer; transition: 0.3s; color: #666; }
        .scan-box:hover { border-color: var(--header-bg); background: #f9f9f9; color: var(--header-bg); }
        
        input { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #ddd; font-size: 14px; box-sizing: border-box; }
        .verify-btn { background: var(--header-bg); color: white; border: none; padding: 12px; width: 100%; border-radius: 8px; font-weight: bold; cursor: pointer; margin-top: 10px; transition: 0.3s; }
        .verify-btn:hover { background: var(--dark-blue); }

        /* Overlay Layouts */
        .status-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); display: flex; align-items: center; justify-content: center; z-index: 9999; padding: 20px; backdrop-filter: blur(5px); }
        .highlight-card { background: white; width: 100%; max-width: 550px; border-radius: 20px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.4); animation: zoomIn 0.3s ease-out; }
        .highlight-header { padding: 40px 20px; text-align: center; color: white; }
        .highlight-header i { font-size: 60px; margin-bottom: 15px; }
        .highlight-header h2 { margin: 0; font-size: 24px; text-transform: uppercase; letter-spacing: 1px; }
        .highlight-body { padding: 30px; }

        .detail-item { display: flex; justify-content: space-between; padding: 12px; background: #f8f9fa; border-radius: 8px; margin-top: 10px; font-size: 13px; }
        .close-overlay { display: block; width: 100%; padding: 15px; background: var(--dark-blue); color: white; text-align: center; text-decoration: none; font-weight: bold; border: none; cursor: pointer; }

        .badge { padding: 6px 14px; border-radius: 50px; font-size: 12px; font-weight: 700; display: inline-block; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .status-authentic { background-color: var(--status-success) !important; color: white !important; }
        .status-revoked-guide { background-color: var(--status-revoked) !important; color: white !important; }
        .status-invalid { background-color: var(--status-tampered) !important; color: white !important; }

        .info-row { display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #fdfdfe; border: 1px solid #edf2f7; border-radius: 8px; margin-top: 10px; }
        .info-label { font-size: 13px; font-weight: 600; color: #4a5568; }
        .info-value { font-size: 13px; font-weight: 700; color: var(--dark-blue); text-align: right; }

        @media (max-width: 1024px) {
            .main-wrapper { }
        }

        @media (max-width: 768px) { 
            .menu-grid, .stats-grid { grid-template-columns: 1fr; } 
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
                <p>Verify medical document authenticity via live camera scanning or digital PDF smart uploads.</p>
            </div>
        </div>

        <div class="tab-wrapper">
            <button id="btn-camera" class="tab-btn active" onclick="switchView('camera')"><i class="fa-solid fa-camera"></i> Camera Scan</button>
            <button id="btn-pdf" class="tab-btn" onclick="switchView('pdf')"><i class="fa-solid fa-file-pdf"></i> Upload PDF</button>
        </div>

        <div id="card-camera" class="card active">
            <h2><i class="fa-solid fa-expand"></i> Full-Page Document QR Scanner</h2>
            <p style="color:#666; font-size:13px; margin-bottom:15px;">Position the whole printed document page or digital sheet inside the viewfinder area.</p>
            
            <div class="camera-lock-box" id="desktop-camera-warning">
                <i class="fa-solid fa-laptop-code"></i>
                <h3 style="margin: 0 0 5px 0;">Live Scanner Restricted</h3>
                <p style="margin: 0; font-size: 13px;">Live camera scanner is optimized for mobile/tablet devices only. Please use the <strong>Upload PDF</strong> tab for desktop verification.</p>
            </div>

            <div id="reader-viewport"></div>
        </div>

        <div id="card-pdf" class="card">
            <h2><i class="fa-solid fa-qrcode"></i> Smart PDF Upload</h2>
            <div class="scan-box" onclick="document.getElementById('pdf_doc_input').click()">
                <i class="fa-solid fa-cloud-arrow-up" style="font-size: 30px; color: var(--header-bg); display: block; margin-bottom: 10px;"></i>
                <span id="file-name-display">Click to Upload & Auto-Scan PDF</span>
                <input type="file" id="pdf_doc_input" accept=".pdf" style="display: none;" onchange="handleSmartPDFUpload(event)">
            </div>
            <form id="hidden-verify-form" action="process_verification.php" method="POST" enctype="multipart/form-data" style="display:none;">
                <input type="hidden" name="extracted_hash" id="final_extracted_hash">
                <input type="file" name="pdf_doc" id="hidden_pdf_file">
                <input type="hidden" name="verify_type" value="pdf">
            </form>
        </div>

        <div class="card active" style="display: block;">
            <h2><i class="fa-solid fa-keyboard"></i> Manual Entry Backup</h2>
            <form action="process_verification.php" method="POST">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 8px;">Enter Document ID</label>
                    <input type="text" name="doc_id" placeholder="e.g. MCUTHM00005" pattern="(MCUTHM|TSUTHM)\d{6}" oninput="this.value = this.value.toUpperCase()" required>
                </div>
                <button type="submit" class="verify-btn"><i class="fa-solid fa-magnifying-glass"></i> Search Document</button>
            </form>
        </div>

        <div class="card active" style="display: block; border-left: 5px solid var(--dark-blue);">
            <h2><i class="fa-solid fa-circle-info"></i> Verification Guide</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px;">
                <div class="status-item"><span class="badge status-authentic">VALID / ACTIVE</span></div>
                <div class="status-item"><span class="badge status-revoked-guide">REVOKED</span></div>
                <div class="status-item"><span class="badge status-invalid">TAMPERED</span></div>
            </div>
        </div>
    </div>
</div>

<?php 
if (isset($_GET['result'])): 
    $resType = $_GET['result'];
    $isRevoked = isset($_GET['revoked']) && $_GET['revoked'] == 'true';
    $isTampered = isset($_GET['tampered']) && $_GET['tampered'] == 'true';
    
    $cardColor = "var(--status-failed)"; $statusIcon = "fa-circle-question"; $statusText = "No Record Found";

    if ($isTampered) { $cardColor = "var(--status-tampered)"; $statusIcon = "fa-triangle-exclamation"; $statusText = "TAMPERED DATA!"; } 
    elseif ($resType == 'success') {
        if ($isRevoked) { $cardColor = "var(--status-revoked)"; $statusIcon = "fa-circle-xmark"; $statusText = "Document Revoked"; } 
        else { $cardColor = "var(--status-success)"; $statusIcon = "fa-circle-check"; $statusText = "Authentic Record Found"; }
    }
?>
<div class="status-overlay" id="resultOverlay">
    <div class="highlight-card">
        <div class="highlight-header" style="background-color: <?php echo $cardColor; ?>;">
            <i class="fa-solid <?php echo $statusIcon; ?>"></i>
            <h2><?php echo $statusText; ?></h2>
        </div>
        <div class="highlight-body">
            <?php if ((isset($_GET['hash'])) && ($resType == 'success' || $isTampered)): 
                $hash = mysqli_real_escape_string($conn, $_GET['hash']);
                $sql = "SELECT * FROM (
                            SELECT 'MC' as type, CONCAT('MCUTHM', LPAD(m.mcID, 6, '0')) as docID, m.patientName, m.matric_staff_no, dp.name as doctor_name, m.documentHash, m.status, m.startDate as val1, m.endDate as val2 FROM mc m LEFT JOIN doctor_profiles dp ON m.doctorId = dp.doctorId
                            UNION ALL
                            SELECT 'TIMESLIP' as type, CONCAT('TSUTHM', LPAD(t.slipID, 6, '0')) as docID, t.patientName, t.matric_staff_no, dp.name as doctor_name, t.documentHash, t.status, t.timeIn as val1, t.timeOut as val2 FROM timeslip t LEFT JOIN doctor_profiles dp ON t.doctorId = dp.doctorId
                        ) AS combined WHERE combined.documentHash = ?";
                $stmt = $conn->prepare($sql); $stmt->bind_param("s", $hash); $stmt->execute(); $doc = $stmt->get_result()->fetch_assoc();
                if ($doc): ?>
                <div class="detail-item" style="background: #eef2f7; border-left: 4px solid var(--header-bg); margin-bottom: 15px;">
                    <span><i class="fa-solid fa-hashtag"></i> Reference:</span><strong style="color: var(--header-bg); font-size: 16px;"><?php echo htmlspecialchars($doc['docID']); ?></strong>
                </div>
                <div class="detail-item"><span>Patient Name:</span><strong><?php echo strtoupper(htmlspecialchars($doc['patientName'])); ?></strong></div>
                <div class="detail-item"><span>Matric / Staff No:</span><strong><?php echo strtoupper(htmlspecialchars($doc['matric_staff_no'])); ?></strong></div>
                <div class="detail-item"><span>Issuing Doctor:</span><strong>Dr. <?php echo htmlspecialchars($doc['doctor_name']); ?></strong></div>
                <div class="info-row">
                    <div class="info-label"><i class="fas fa-clock"></i> Timeline:</div>
                    <div class="info-value"><?php echo ($doc['type'] === 'MC') ? date('d M Y', strtotime($doc['val1'])) . " - " . date('d M Y', strtotime($doc['val2'])) : htmlspecialchars($doc['val1']) . " - " . htmlspecialchars($doc['val2']); ?></div>
                </div>                    
                <div class="detail-item" style="margin-top: 15px; border-top: 1px dashed #ddd; padding-top: 15px;">
                    <span>Blockchain Integrity:</span><strong style="color:<?php echo $cardColor; ?>;"><?php echo ($isTampered) ? "FAILED ✘ (Data Mismatch)" : "VERIFIED ✓ (Secured)"; ?></strong>
                </div>
            <?php endif; endif; ?>
        </div>
        <button class="close-overlay" onclick="closeResult()">DISMISS</button>
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

    // ====== LOGIK SEMAKAN JENIS PERANTI (MOBILE VS DESKTOP) ======
    function isMobileDevice() {
        return /Mobi|Android|iPhone|iPad|Tablet|PlayBook|Silicon/i.test(navigator.userAgent);
    }

    let cameraScanner = null;
    
    function switchView(mode) {
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.getElementById('btn-' + mode).classList.add('active');
        
        document.getElementById('card-camera').classList.remove('active');
        document.getElementById('card-pdf').classList.remove('active');
        document.getElementById('card-' + mode).classList.add('active');
        
        if (mode === 'camera') { 
            launchLiveCamera(); 
        } else { 
            if (cameraScanner) { cameraScanner.clear(); } 
        }
    }

    function launchLiveCamera() {
        if (!isMobileDevice()) {
            document.getElementById('desktop-camera-warning').style.display = 'block';
            document.getElementById('reader-viewport').style.display = 'none';
            return;
        }

        document.getElementById('desktop-camera-warning').style.display = 'none';
        document.getElementById('reader-viewport').style.display = 'block';

        cameraScanner = new Html5QrcodeScanner("reader-viewport", { 
            fps: 30, 
            qrbox: function(viewfinderWidth, viewfinderHeight) {
                var minEdgeFraction = 0.8; 
                var minEdgeSize = Math.min(viewfinderWidth, viewfinderHeight);
                var qrboxSize = Math.floor(minEdgeSize * minEdgeFraction);
                return { width: qrboxSize, height: qrboxSize };
            },
            rememberLastUsedCamera: true,
            aspectRatio: 1.0
        });
        
        cameraScanner.render((decodedText) => {
            cameraScanner.clear();
            
            // AUTOMATED: Imbas, kesan, dan terus hantar data rahsia ke backend secara senyap
            Swal.fire({
                title: 'QR Detected!',
                text: 'Extracting cryptographic ledger payload...',
                icon: 'success',
                timer: 900,
                showConfirmButton: false,
                willClose: () => {
                    document.getElementById('final_extracted_hash').value = decodedText;
                    
                    const form = document.getElementById('hidden-verify-form');
                    form.querySelector('input[name="verify_type"]').value = 'camera';
                    document.getElementById('hidden_pdf_file').value = ""; 
                    
                    form.submit();
                }
            });
        }, (err) => {});
    }

async function handleSmartPDFUpload(event) {
        const file = event.target.files[0]; if (!file) return;
        document.getElementById('file-name-display').innerText = "Scanning: " + file.name + "...";
        try {
            const arrayBuffer = await file.arrayBuffer();
            const loadingTask = pdfjsLib.getDocument({data: arrayBuffer});
            const pdf = await loadingTask.promise;
            const page = await pdf.getPage(1);
            
            const viewport = page.getViewport({ scale: 4.0 }); 
            const canvas = document.createElement('canvas'); const context = canvas.getContext('2d');
            canvas.height = viewport.height; canvas.width = viewport.width;
            context.imageSmoothingEnabled = false;
            await page.render({ canvasContext: context, viewport: viewport }).promise;
            const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
            
            const qrCode = jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: "dontInvert" });

            if (qrCode) {
                const qrText = qrCode.data.trim();
                let finalHashOrPayload = "";
                let isPayloadVersion = false;

                // ====== PEMBETULAN KHAS: SEMAK SAMA ADA INPUT ADALAH URL ATAU TEKS MENTAH ======
                if (qrText.startsWith('http://') || qrText.startsWith('https://')) {
                    const url = new URL(qrText);
                    if (url.searchParams.get("payload")) {
                        finalHashOrPayload = qrText; // Hantar pautan penuh payload
                        isPayloadVersion = true;
                    } else if (url.searchParams.get("hash")) {
                        finalHashOrPayload = url.searchParams.get("hash");
                    }
                } else {
                    // Jika QR cuma mengandungi teks HASH mentah sebulat-bulatnya (seperti fail anda)
                    finalHashOrPayload = qrText;
                }

                if(finalHashOrPayload) {
                    document.getElementById('final_extracted_hash').value = finalHashOrPayload;
                    const dataTransfer = new DataTransfer(); dataTransfer.items.add(file);
                    document.getElementById('hidden_pdf_file').files = dataTransfer.files;
                    
                    // Set jenis pengesahan dengan selamat
                    const form = document.getElementById('hidden-verify-form');
                    form.querySelector('input[name="verify_type"]').value = isPayloadVersion ? 'camera' : 'pdf';
                    
                    form.submit();
                } else { Swal.fire("Error", "No valid cryptographic parameters found in QR.", "error"); }
            } else {
                Swal.fire("No QR Detected", "System failed to auto-scan QR from this PDF.", "warning");
                document.getElementById('file-name-display').innerText = "Click to Upload & Auto-Scan PDF";
            }
        } catch (err) { Swal.fire("System Error", "Fail to parse PDF structure.", "error"); }
    }

    // Mulakan sistem dengan cubaan mengaktifkan kamera secara auto-detect
    document.addEventListener("DOMContentLoaded", function() { switchView('camera'); });
</script>
</body>
</html>