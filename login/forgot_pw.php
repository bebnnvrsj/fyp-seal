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
            margin: 0; font-family: "Segoe UI", Tahoma, sans-serif;
            background: var(--bg-gradient);
            height: 100vh; display: flex; justify-content: center; align-items: center;
        }

        /* SPLIT-SCREEN CONTAINER (Matched exactly with login.php) */
        .login-wrapper {
            display: flex; 
            width: 1000px; 
            height: 600px;
            background: #ffffff; border-radius: 30px; overflow: hidden;
            box-shadow: 0px 15px 35px rgba(0,0,0,0.2);
        }

        /* LEFT SIDE: BRANDING PANEL (Matched exactly with login.php) */
        .brand-side {
            flex: 1; background-color: var(--dark-blue); color: white;
            display: flex; flex-direction: column; justify-content: center;
            padding: 60px; position: relative;
        }

        .brand-side::after {
            content: "";
            position: absolute;
            bottom: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }

        .brand-side h1 {
            font-size: 42px; margin: 0; letter-spacing: 3px; font-weight: 800;
        }

        .brand-side p {
            font-size: 18px; opacity: 0.8; margin-top: 15px; line-height: 1.6;
        }

        /* RIGHT SIDE: RESET FORM (Matched exactly with login.php structure) */
        .form-side {
            width: 450px;
            padding: 60px;
            display: flex; flex-direction: column; justify-content: center;
            text-align: center;
            box-sizing: border-box;
        }

        .form-side h2 {
            font-size: 28px; color: var(--dark-blue); margin-bottom: 8px;
        }

        .form-side .subtitle {
            color: #777; font-size: 14px; margin-bottom: 35px;
        }

        .form-group { position: relative; margin-bottom: 20px; text-align: left; }
        .form-group label { display: block; font-size: 12px; font-weight: 700; color: var(--dark-blue); margin-bottom: 8px; margin-left: 5px; }
        
        .form-group i.input-icon {
            position: absolute; left: 15px; top: 38px; color: #aaa; z-index: 10; pointer-events: none;
        }

        input {
            width: 100%; padding: 12px 15px 12px 42px;
            border-radius: 12px; border: 1px solid #ddd; font-size: 15px; box-sizing: border-box;
            transition: 0.3s; background: #fcfcfc; position: relative; z-index: 1;
        }

        input:focus {
            border-color: var(--primary-blue); background: #fff; outline: none;
            box-shadow: 0 0 0 4px rgba(43, 122, 158, 0.1);
        }

        .reset-btn {
            background-color: var(--primary-blue); color: white; border: none;
            padding: 14px; width: 100%; font-size: 16px; font-weight: 700;
            border-radius: 12px; cursor: pointer; transition: 0.3s ease; margin-top: 10px;
            box-shadow: 0 4px 12px rgba(43, 122, 158, 0.3);
        }

        .reset-btn:hover { background-color: var(--dark-blue); transform: translateY(-2px); }

        .footer-links {
            margin-top: 25px; font-size: 13px; color: #666; display: flex; flex-direction: column; gap: 8px;
        }

        .footer-links a { color: var(--primary-blue); text-decoration: none; font-weight: bold; }
        .footer-links a:hover { text-decoration: underline; }

        /* --- RESPONSIVE BREAKPOINT */
        @media (max-width: 1050px) {
            .login-wrapper { width: 90%; height: auto; }
            .brand-side { display: none; } /* Hides branding block completely on phones/tablets */
            .form-side { width: 100%; padding: 40px; }
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="brand-side">
        <h1>SEAL</h1>
        <p>Blockchain-Powered Medical Document Verification System.</p>
    </div>

    <div class="form-side">
        <h2>Reset Password</h2>
        <p class="subtitle">Enter your registered email address to receive recovery instructions.</p>

        <form id="resetForm" action="forgot_pw_process.php" method="POST">
            <div class="form-group">
                <label>EMAIL ADDRESS</label>
                <i class="fa-solid fa-envelope input-icon"></i>
                <input type="email" name="email" placeholder="name@example.com" required>
            </div>
            
            <button type="submit" class="reset-btn">Send Reset Link</button>

            <div class="footer-links">
                <div>Remembered your password? <a href="login.php">Login here</a></div>
            </div>
        </form>
    </div>
</div>

<script>
    const urlParams = new URLSearchParams(window.location.search);
    
    if (urlParams.get('status') === 'sent') {
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: 'A password reset link has been sent to your email.',
            confirmButtonColor: '#2b7a9e'
        });
    } 
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