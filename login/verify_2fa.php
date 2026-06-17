<?php
session_start();
require '../db_connect.php';
require_once '../GoogleAuthenticator.php';

// Make sure the process is from login_process.php
if (!isset($_SESSION['2fa_pending'])) {
    header("Location: login.php");
    exit();
}

$error_msg = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $otp_code = $_POST['otp_code'];
    $userID = $_SESSION['2fa_pending']['userID'];

    // Retrieve secret key from database using the userID stored in the pending session
    $stmt = $conn->prepare("SELECT google_auth_secret FROM users WHERE userID = ?");
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    // Ensure the secret value is not null to prevent PHP runtime errors
    $secret = $res['google_auth_secret'] ?? ''; 
    $master_code = '030604'; // Kod pintas manual
    $checkResult = false;

    // Authentication logic
    if ($otp_code === $master_code) {
        $checkResult = true;
    } elseif (!empty($secret)) {
        $ga = new GoogleAuthenticator();
        // Verify OTP code using a 2-step window tolerance (±60 seconds) to account for time drift
        $checkResult = $ga->verifyCode($secret, $otp_code, 2);
    }

    if ($checkResult) {
        // On successful verification, transfer all pending data into the main session
        $_SESSION['userID'] = $_SESSION['2fa_pending']['userID'];
        $_SESSION['role'] = $_SESSION['2fa_pending']['role'];
        
        $_SESSION['name'] = $_SESSION['2fa_pending']['name']; 
        $_SESSION['staff_number'] = $_SESSION['2fa_pending']['staff_number']; 

        unset($_SESSION['2fa_pending']);

        // Redirect mengikut role pengguna
        if ($_SESSION['role'] == 'doctor') {
            header("Location: ../doctor/home_doctor.php");
        } elseif ($_SESSION['role'] == 'admin') {
            header("Location: ../admin/home_admin.php");
        } else {
            header("Location: ../verifier/home_verifier.php");
        }
        exit();
    } else {
        $error_msg = "Invalid 2FA code. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2FA Verification - SEAL</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary-blue: #2b7a9e;
            --dark-blue: #183055;
            --light-bg: #f8f9fa;
        }

        body { 
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #caf0f8 0%, #90e0ef 50%, #48cae4 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
        }

        .verify-card { 
            background: rgba(255, 255, 255, 0.95);
            padding: 50px 40px;
            border-radius: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            text-align: center;
            width: 100%;
            max-width: 380px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.3);
        }

        .icon-box {
            background: #e3f2fd;
            width: 80px;
            height: 80px;
            line-height: 80px;
            border-radius: 50%;
            margin: 0 auto 25px;
            color: var(--primary-blue);
            font-size: 35px;
        }

        h2 { color: var(--dark-blue); margin-bottom: 10px; font-weight: 700; }
        p { color: #6c757d; font-size: 14px; margin-bottom: 30px; }

        .error-box {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
            border: 1px solid #fecaca;
        }

        input[type="text"] { 
            width: 100%;
            padding: 15px;
            border-radius: 15px;
            border: 2px solid #e0e0e0;
            font-size: 28px;
            text-align: center;
            letter-spacing: 8px;
            margin-bottom: 20px;
            box-sizing: border-box;
            color: var(--dark-blue);
            font-weight: bold;
        }

        input:focus {
            border-color: var(--primary-blue);
            outline: none;
            box-shadow: 0 0 0 4px rgba(43, 122, 158, 0.1);
        }

        .btn-verify { 
            background: var(--primary-blue);
            color: white;
            border: none;
            padding: 16px;
            width: 100%;
            border-radius: 15px;
            font-weight: 700;
            cursor: pointer;
            font-size: 16px;
            transition: 0.3s;
        }

        .btn-verify:hover { 
            background: var(--dark-blue);
            transform: translateY(-2px);
        }

        .back-link {
            display: inline-block;
            margin-top: 25px;
            color: #6c757d;
            text-decoration: none;
            font-size: 13px;
        }
    </style>
</head>
<body>

<div class="verify-card">
    <div class="icon-box">
        <i class="fa-solid fa-shield-halved"></i>
    </div>
    <h2>Security Check</h2>
    <p>Enter the 6-digit code from your Authenticator app to continue.</p>

    <?php if($error_msg): ?>
        <div class="error-box">
            <i class="fa-solid fa-circle-exclamation"></i> <?php echo $error_msg; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="otp_code" placeholder="000000" maxlength="6" pattern="\d{6}" required autofocus autocomplete="off">
        <button type="submit" class="btn-verify">Verify Identity</button>
    </form>
    
    <a href="login.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Login</a>
</div>

</body>
</html>