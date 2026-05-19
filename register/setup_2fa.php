<?php
session_start();
require_once '../GoogleAuthenticator.php';

// Pastikan hanya pengguna yang baru mendaftar boleh akses
if (!isset($_SESSION['temp_secret']) || !isset($_SESSION['temp_email'])) {
    header("Location: ../login/login.php");
    exit();
}

$ga = new GoogleAuthenticator();
$secret = $_SESSION['temp_secret'];
$email = $_SESSION['temp_email'];

// Bina format URL standard untuk 2FA (otpauth)
$issuer = 'SEAL_System';
$otpauth_url = "otpauth://totp/" . rawurlencode($issuer . ":" . $email) . "?secret=" . $secret . "&issuer=" . rawurlencode($issuer);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Setup 2FA - SEAL</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        body { 
            font-family: "Segoe UI", sans-serif; 
            background: linear-gradient(to bottom right, #caf0f8, #90e0ef, #48cae4); 
            height: 100vh; display: flex; justify-content: center; align-items: center; margin: 0;
        }
        .setup-card {
            background: white; padding: 40px; border-radius: 25px; 
            box-shadow: 0 15px 35px rgba(0,0,0,0.2); text-align: center; max-width: 450px;
        }
        #qrcode-canvas { 
            display: flex; justify-content: center; margin: 25px 0; 
        }
        #qrcode-canvas img {
            border: 8px solid #f0f0f0; border-radius: 15px;
        }
        .secret-key { 
            background: #f8f9fa; padding: 10px; border-radius: 8px; 
            font-family: monospace; font-weight: bold; color: #2b7a9e; letter-spacing: 2px;
        }
        .btn-done {
            background: #2b7a9e; color: white; border: none; padding: 12px 30px; 
            border-radius: 10px; font-weight: bold; cursor: pointer; transition: 0.3s;
            text-decoration: none; display: inline-block; margin-top: 20px;
        }
    </style>
</head>
<body>

<div class="setup-card">
    <i class="fa-solid fa-shield-halved" style="font-size: 50px; color: #2b7a9e;"></i>
    <h2 style="color: #183055;">Secure Your Account</h2>
    <p style="color: #666; font-size: 14px;">Scan this QR code with your <b>Google Authenticator</b> app.</p>

    <div id="qrcode-canvas"></div>

    <p style="font-size: 12px; color: #888;">Manual Code:</p>
    <div class="secret-key"><?php echo $secret; ?></div>

    <div style="margin-top: 25px; padding: 15px; background: #fff3cd; border-radius: 10px; font-size: 11px; color: #856404; text-align: left;">
        <i class="fa-solid fa-triangle-exclamation"></i> <b>IMPORTANT:</b> Save this key. You will need it if you lose your phone.
    </div>

    <a href="../login/login.php" class="btn-done">I've Scanned It - Go to Login</a>
</div>

<script>
    // Jana Kod QR secara client-side
    new QRCode(document.getElementById("qrcode-canvas"), {
        text: "<?php echo $otpauth_url; ?>",
        width: 200,
        height: 200
    });
</script>

</body>
</html>