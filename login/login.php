<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | SEAL</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    :root {
        --primary-blue: #2b7a9e;
        --dark-blue: #183055;
        --bg-gradient: linear-gradient(to bottom right, #caf0f8, #90e0ef, #48cae4);
    }

    body {
        margin: 0;
        font-family: "Segoe UI", Tahoma, sans-serif;
        background: var(--bg-gradient);
        height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    /* SPLIT-SCREEN CONTAINER */
    .login-wrapper {
        display: flex;
        width: 1000px;
        height: 600px;
        background: #ffffff;
        border-radius: 30px;
        overflow: hidden;
        box-shadow: 0px 15px 35px rgba(0,0,0,0.2);
    }

    /* LEFT SIDE: BRANDING PANEL */
    .brand-side {
        flex: 1;
        background-color: var(--dark-blue);
        color: white;
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 60px;
        position: relative;
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
        font-size: 42px;
        margin: 0;
        letter-spacing: 3px;
        font-weight: 800;
    }

    .brand-side p {
        font-size: 18px;
        opacity: 0.8;
        margin-top: 15px;
        line-height: 1.6;
    }

    .feature-list {
        margin-top: 40px;
        list-style: none;
        padding: 0;
    }

    .feature-list li {
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 14px;
    }

    .feature-list i { color: #48cae4; }

    /* RIGHT SIDE: LOGIN FORM */
    .form-side {
        width: 450px;
        padding: 60px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        text-align: center;
    }

    .form-side h2 {
        font-size: 28px;
        color: var(--dark-blue);
        margin-bottom: 8px;
    }

    .form-side .subtitle {
        color: #777;
        font-size: 14px;
        margin-bottom: 35px;
    }

    .form-group {
        position: relative;
        margin-bottom: 20px;
        text-align: left;
    }

    .form-group label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        color: var(--dark-blue);
        margin-bottom: 8px;
        margin-left: 5px;
    }

    .form-group i.input-icon {
        position: absolute;
        left: 15px;
        top: 38px; 
        color: #aaa;
        z-index: 10;        
        pointer-events: none;
    }

    input {
        width: 100%;
        padding: 12px 15px 12px 42px; 
        border-radius: 12px;
        border: 1px solid #ddd;
        font-size: 15px;
        box-sizing: border-box;
        transition: 0.3s;
        background: #fcfcfc;
        position: relative; 
        z-index: 1;
        }

    input:focus {
        border-color: var(--primary-blue);
        background: #fff;
        outline: none;
        box-shadow: 0 0 0 4px rgba(43, 122, 158, 0.1);
    }

    .password-container {
        position: relative;
        display: flex;
        align-items: center;
    }

    .toggle-password {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #777;
        z-index: 10;
    }

    .login-btn {
        background-color: var(--primary-blue);
        border: none;
        color: white;
        padding: 14px;
        width: 100%;
        font-size: 16px;
        font-weight: 700;
        border-radius: 12px;
        cursor: pointer;
        transition: 0.3s ease;
        margin-top: 10px;
        box-shadow: 0 4px 12px rgba(43, 122, 158, 0.3);
    }

    .login-btn:hover {
        background-color: var(--dark-blue);
        transform: translateY(-2px);
    }

    .footer-links {
        margin-top: 25px;
        font-size: 13px;
        color: #666;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .footer-links a {
        color: var(--primary-blue);
        text-decoration: none;
        font-weight: bold;
    }

    .footer-links a:hover { text-decoration: underline; }

    /* RESPONSIVE */
    @media (max-width: 1050px) {
        .login-wrapper { width: 90%; height: auto; }
        .brand-side { display: none; }
        .form-side { width: 100%; padding: 40px; }
    }
</style>
</head>
<body>

<div class="login-wrapper">
    <div class="brand-side">
        <h1>SEAL</h1>
        <p>Blockchain-Powered Medical Document Verification System.</p>
        
        <ul class="feature-list">
            <li><i class="fa-solid fa-circle-check"></i> Secure Digital MC Issuance</li>
            <li><i class="fa-solid fa-circle-check"></i> Real-time Document Monitoring</li>
            <li><i class="fa-solid fa-circle-check"></i> Immutable Audit Logs</li>
            <li><i class="fa-solid fa-circle-check"></i> Instant Verifier Access</li>
        </ul>
    </div>

    <div class="form-side">
        <h2>Welcome</h2>
        <p class="subtitle">Please enter your credentials to login.</p>

        <form action="login_process.php" method="POST"> 
            
            <div class="form-group">
                <label>EMAIL ADDRESS</label>
                <i class="fa-solid fa-envelope input-icon"></i>
                <input type="email" name="email" placeholder="name@example.com" required>
            </div>
            
            <div class="form-group">
                <label>PASSWORD</label>
                <i class="fa-solid fa-lock input-icon"></i>
                <div class="password-container">
                    <input type="password" name="password" id="password" placeholder="••••••••" required>
                    <i class="fa-solid fa-eye toggle-password" id="toggleEye"></i>
                </div>
            </div>
            
            <button type="submit" class="login-btn">Login</button>

            <div class="footer-links">
                <div>Forgot Password? <a href="forgot_pw.php">Reset here</a></div>
                <div style="margin-top: 10px;">Don't have an account? <a href="../register/register.php">
                    Sign up now</a></div>
            </div>
        </form>
    </div>
</div>

<script>
    const toggleEye = document.querySelector('#toggleEye');
    const passwordInput = document.querySelector('#password');

    toggleEye.addEventListener('click', function () {
        const isPassword = passwordInput.getAttribute('type') === 'password';
        passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });

    const urlParams = new URLSearchParams(window.location.search);
    const error = urlParams.get('error');

    if (error) {
    let message = "";
    let title = "Login Failed";
    let iconType = 'error'; // Default icon

    if (error === "user_not_found") {
        message = "The email address entered does not exist in our system.";
    } else if (error === "invalid_password") {
        message = "The password you entered is incorrect. Please try again.";
        title = "Wrong Password!";
    } else if (error === "account_disabled") {
        title = "Account Deactivated";
        message = "Your account has been deactivated. Please contact the administrator for assistance.";
        iconType = 'warning'; // Tukar ikon kepada amaran
    } else if (error === "invalid_role") {
        message = "Your account has an invalid role. Please contact the administrator.";
    }

    Swal.fire({
        icon: iconType, // Gunakan pembolehubah dinamik
        title: title,
        text: message,
        confirmButtonColor: '#2b7a9e',
        confirmButtonText: 'Understood',
        borderRadius: '15px'
    }).then(() => {
        window.history.replaceState({}, document.title, window.location.pathname);
    });
}
</script>

</body>
</html>