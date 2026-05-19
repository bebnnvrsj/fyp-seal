<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | SEAL</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary-blue: #2b7a9e;
            --dark-blue: #183055;
            --bg-gradient: linear-gradient(to bottom right, #caf0f8, #90e0ef, #48cae4);
        }

        body {
            margin: 0; font-family: "Segoe UI", sans-serif;
            background: var(--bg-gradient);
            height: 100vh; display: flex; justify-content: center; align-items: center;
        }

        .login-wrapper {
            display: flex; width: 1000px; height: 600px;
            background: #ffffff; border-radius: 30px; overflow: hidden;
            box-shadow: 0px 15px 35px rgba(0,0,0,0.2);
        }

        .brand-side {
            flex: 1; background-color: var(--dark-blue); color: white;
            display: flex; flex-direction: column; justify-content: center;
            padding: 60px;
        }

        .form-side {
            width: 450px; padding: 60px;
            display: flex; flex-direction: column; justify-content: center;
            text-align: center;
        }

        .form-group { position: relative; margin-bottom: 25px; text-align: left; }
        .form-group label { display: block; font-size: 12px; font-weight: 700; color: var(--dark-blue); margin-bottom: 8px; }
        
        input {
            width: 100%; padding: 12px 15px 12px 42px;
            border-radius: 12px; border: 1px solid #ddd; font-size: 15px; box-sizing: border-box;
        }

        .input-icon { position: absolute; left: 15px; top: 38px; color: #aaa; }

        .reset-btn {
            background-color: var(--primary-blue); color: white; border: none;
            padding: 14px; width: 100%; font-size: 16px; font-weight: 700;
            border-radius: 12px; cursor: pointer; transition: 0.3s;
        }

        .reset-btn:hover { background-color: var(--dark-blue); transform: translateY(-2px); }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="brand-side">
        <h1>Password Recovery</h1>
        <p>Don't worry! Enter your registered email address and we'll send you instructions to reset your password.</p>
        <div style="margin-top: 30px; font-size: 60px; opacity: 0.2;"><i class="fa-solid fa-key"></i></div>
    </div>

    <div class="form-side">
        <h2>Reset Password</h2>
        <p style="color:#777; font-size: 14px; margin-bottom: 35px;">Secure your account with a new password.</p>

        <form id="resetForm" action="forgot_pw_process.php" method="POST">
            <div class="form-group">
                <label>REGISTERED EMAIL</label>
                <i class="fa-solid fa-envelope input-icon"></i>
                <input type="email" name="email" placeholder="name@example.com" required>
            </div>
            
            <button type="submit" class="reset-btn">Send Reset Link</button>

            <div style="margin-top: 25px; font-size: 13px;">
                Remembered your password? <a href="login.php" style="color: var(--primary-blue); text-decoration: none; font-weight: bold;">Login here</a>
            </div>
        </form>
    </div>
</div>

<script>
    const urlParams = new URLSearchParams(window.location.search);
    
    // Jika status ialah 'sent' (Berjaya)
    if (urlParams.get('status') === 'sent') {
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: 'A password reset link has been sent to your email.',
            confirmButtonColor: '#2b7a9e'
        }).then(() => {
            // Pilihan: Boleh redirect pengguna ke halaman login selepas mereka klik OK
            // window.location.href = 'login.php';
        });
    } 
    // Jika status ialah 'not_found' (Tiada User)
    else if (urlParams.get('status') === 'not_found') {
        Swal.fire({
            icon: 'error',
            title: 'No User Registered',
            text: 'This email address is not associated with any account.',
            confirmButtonColor: '#2b7a9e'
        });
    }
    else if (urlParams.get('status') === 'error') {
    Swal.fire({
        icon: 'error',
        title: 'System Error',
        text: 'Failed to send email. Please check your internet connection or SMTP settings.',
        confirmButtonColor: '#2b7a9e'
    });
}
</script>

</body>
</html>