<?php
session_start();
require '../db_connect.php';

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

$token = $_GET['token'] ?? '';

// Jika tiada token di URL dan tiada backup session emel, halang akses terus
if (empty($token) && !isset($_SESSION['reset_email'])) {
    header("Location: forgot_pw.php");
    exit;
}

// Jika ada token di URL, buat pengesahan database (Cross-Device Handshake)
if (!empty($token)) {
    $sql = "SELECT username FROM users WHERE reset_token = ? AND token_expires > NOW()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $_SESSION['reset_email'] = $user['username']; // Simpan emel secara selamat dalam session pelayan
        
        // ✨ SOLUSI MUTLAK: Kosongkan sisa ralat sesi lepas kerana token ini terbukti SAH!
        $error = ''; 
    } else {
        // Token tidak sah atau tamat tempoh 30 minit
        $_SESSION['error'] = "The password reset link is invalid or has expired. Please request a new one.";
        header("Location: forgot_pw.php?status=expired");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | SEAL</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary-blue: #2b7a9e;
            --dark-blue: #183055;
            --bg-gradient: linear-gradient(to bottom right, #caf0f8, #90e0ef, #48cae4);
            --white: #ffffff;
        }

        * { box-sizing: border-box; font-family: "Segoe UI", Tahoma, sans-serif; }

        body {
            margin: 0;
            height: 100vh;
            background: var(--bg-gradient);
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .container {
            background: var(--white);
            width: 100%;
            max-width: 450px;
            padding: 50px 40px;
            border-radius: 25px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
            text-align: center;
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .brand-logo {
            font-size: 32px;
            font-weight: 800;
            color: var(--dark-blue);
            margin-bottom: 5px;
            letter-spacing: 1px;
        }

        h2 {
            font-size: 20px;
            color: #555;
            margin-bottom: 30px;
            font-weight: 500;
        }

        .form-group {
            position: relative;
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group i {
            position: absolute;
            left: 15px;
            top: 42px;
            color: #aaa;
        }

        label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: var(--dark-blue);
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        input {
            width: 100%;
            padding: 14px 15px 14px 45px;
            border-radius: 12px;
            border: 1px solid #ddd;
            font-size: 15px;
            transition: 0.3s;
        }

        input:focus {
            border-color: var(--primary-blue);
            outline: none;
            box-shadow: 0 0 0 3px rgba(43, 122, 158, 0.1);
        }

        button {
            width: 100%;
            padding: 15px;
            background: var(--primary-blue);
            border: none;
            border-radius: 12px;
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 10px;
        }

        button:hover {
            background: var(--dark-blue);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .alert {
            padding: 12px;
            border-radius: 10px;
            font-size: 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .error { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }

        .back-link {
            display: block;
            margin-top: 25px;
            font-size: 14px;
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 600;
        }

        .back-link:hover { text-decoration: underline; }

        .form-group .toggle-password {
            position: absolute;
            right: 15px;
            top: 42px;
            left: auto;
            cursor: pointer;
            color: #666;
            transition: 0.2s;
        }

        .form-group .toggle-password:hover {
            color: var(--primary-blue);
        }

        .password-criteria {
            text-align: left;
            margin-top: -10px;
            margin-bottom: 20px;
            padding-left: 5px;
        }

        .criteria {
            font-size: 11px;
            color: #b91c1c;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
            transition: 0.3s;
        }

        .criteria.valid {
            color: #15803d;
        }

        .criteria i {
            font-size: 10px;
        }

        .match-message {
            font-size: 11px;
            text-align: left;
            margin-top: -15px;
            margin-bottom: 15px;
            font-weight: 600;
            display: none;
        }

        .match-message.error { color: #b91c1c; display: block; }
        .match-message.success { color: #15803d; display: block; }
    </style>
</head>
<body>

<div class="container">
    <div class="brand-logo">SEAL</div>
    <h2>Set New Password</h2>

    <?php if ($error): ?>
        <div class="alert error">
            <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form action="reset_process.php" method="post">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

        <div class="form-group">
            <label>New Password</label>
            <i class="fa-solid fa-lock"></i>
            <input type="password" name="password" id="password" 
                placeholder="Min. 12 characters" 
                pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&_ ]).{12,}" 
                required>
            <i class="fa-solid fa-eye toggle-password" id="togglePassword"></i>
        </div>

        <div class="password-criteria">
            <div id="char-length" class="criteria"><i class="fa-solid fa-circle"></i> At least 12 characters</div>
            <div id="uppercase" class="criteria"><i class="fa-solid fa-circle"></i> One uppercase letter (A-Z)</div>
            <div id="lowercase" class="criteria"><i class="fa-solid fa-circle"></i> One lowercase letter (a-z)</div>
            <div id="number" class="criteria"><i class="fa-solid fa-circle"></i> One number (0-9)</div>
            <div id="symbol" class="criteria"><i class="fa-solid fa-circle"></i> One special symbol (@$!%*?&_)</div>
        </div>

        <div class="form-group">
            <label>Confirm Password</label>
            <i class="fa-solid fa-lock"></i>
            <input type="password" name="confirm_password" id="confirm_password" 
                placeholder="Repeat your password" required>
            <i class="fa-solid fa-eye toggle-password" id="toggleConfirmPassword"></i>
        </div>

        <div id="match-status" class="match-message"></div>

        <button type="submit">Update Password</button>
    </form>

    <a href="login.php" class="back-link">Back to login</a>
</div>

<script>
    const passwordInput = document.getElementById('password');
    const confirmInput = document.getElementById('confirm_password');
    const matchStatus = document.getElementById('match-status');

    confirmInput.addEventListener('input', () => {
        const pw = passwordInput.value;
        const cpw = confirmInput.value;

        if (cpw.length === 0) {
            matchStatus.style.display = 'none';
        } else if (pw === cpw) {
            matchStatus.textContent = '✓ Passwords match';
            matchStatus.className = 'match-message success';
        } else {
            matchStatus.textContent = '✗ Passwords do not match';
            matchStatus.className = 'match-message error';
        }
    });
    
    const criteria = {
        length: document.getElementById('char-length'),
        upper: document.getElementById('uppercase'),
        lower: document.getElementById('lowercase'),
        num: document.getElementById('number'),
        sym: document.getElementById('symbol')
    };

    passwordInput.addEventListener('input', () => {
        const val = passwordInput.value;
        validate(val.length >= 12, criteria.length);
        validate(/[A-Z]/.test(val), criteria.upper);
        validate(/[a-z]/.test(val), criteria.lower);
        validate(/\d/.test(val), criteria.num);
        validate(/[@$!%*?&_]/.test(val), criteria.sym);
    });

    function validate(condition, element) {
        if (condition) {
            element.classList.add('valid');
            element.querySelector('i').classList.replace('fa-circle', 'fa-circle-check');
        } else {
            element.classList.remove('valid');
            element.querySelector('i').classList.replace('fa-circle-check', 'fa-circle');
        }
    }

    function setupToggle(btnId, inputId) {
        const btn = document.getElementById(btnId);
        const input = document.getElementById(inputId);
        btn.addEventListener('click', () => {
            const type = input.type === 'password' ? 'text' : 'password';
            input.type = type;
            btn.classList.toggle('fa-eye');
            btn.classList.toggle('fa-eye-slash');
        });
    }
    setupToggle('togglePassword', 'password');
    setupToggle('toggleConfirmPassword', 'confirm_password');
</script>
</body>
</html>