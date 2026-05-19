<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign Up | SEAL</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary-blue: #2b7a9e;
            --dark-blue: #183055;
            --bg-gradient: linear-gradient(to bottom right, #caf0f8, #90e0ef, #48cae4);
        }

        body { margin: 0; font-family: "Segoe UI", sans-serif; background: var(--bg-gradient); height: 100vh; display: flex; justify-content: center; align-items: center; }
        .register-wrapper { display: flex; width: 1100px; height: 750px; background: #ffffff; border-radius: 30px; overflow: hidden; box-shadow: 0px 15px 35px rgba(0,0,0,0.2); }
        .brand-side { flex: 1; background-color: var(--dark-blue); color: white; display: flex; flex-direction: column; justify-content: center; padding: 60px; position: relative; }
        .form-side { width: 500px; padding: 30px 50px; display: flex; flex-direction: column; justify-content: center; overflow-y: auto; }
        .form-group { position: relative; margin-bottom: 12px; }
        .form-group label { display: block; font-size: 11px; font-weight: 700; color: var(--dark-blue); margin-bottom: 4px; }
        input, select { width: 100%; padding: 10px 15px 10px 40px; border-radius: 10px; border: 1px solid #ddd; font-size: 14px; box-sizing: border-box; background: #fcfcfc; position: relative; z-index: 1; }
        .form-group i.input-icon { position: absolute; left: 15px; top: 32px; color: #aaa; z-index: 10; pointer-events: none; }
        .password-wrapper { position: relative; display: flex; align-items: center; }
        .toggle-password { position: absolute; right: 15px; cursor: pointer; color: #888; z-index: 10; }
        
        /* Requirement badges */
        .requirement-container { margin-top: 5px; display: flex; flex-wrap: wrap; gap: 5px; }
        .requirement { font-size: 10px; padding: 2px 8px; border-radius: 4px; background: #eee; transition: 0.3s; color: #777; }
        .valid { background: #d4edda; color: #155724; }
        .invalid { background: #f8d7da; color: #721c24; }
        
        .reg-btn { background-color: var(--primary-blue); border: none; color: white; padding: 12px; width: 100%; font-size: 16px; font-weight: 700; border-radius: 10px; cursor: pointer; transition: 0.3s; margin-top: 10px; }
        .reg-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .footer-link { text-align: center; margin-top: 15px; font-size: 13px; color: #666; }
        .footer-link a { color: var(--primary-blue); font-weight: bold; text-decoration: none; }
    </style>
</head>
<body>

<div class="register-wrapper">
    <div class="brand-side">
        <h1>JOIN SEAL</h1>
        <p>Blockchain-Powered Medical Document Verification System.</p>
        <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 10px; border-left: 4px solid #48cae4;">
            <i class="fa-solid fa-circle-info"></i> Use <strong>@uthm.edu.my</strong> to register.
        </div>
    </div>

    <div class="form-side">
        <h2 style="color: var(--dark-blue); margin: 0;">Create Account</h2>
        <p style="color: #777; font-size: 13px; margin-bottom: 20px;">Complete the form below.</p>

        <form action="register_process.php" method="POST" id="registerForm">
            <div class="form-group">
                <label>FULL NAME</label>
                <i class="fa-solid fa-user input-icon"></i>
                <input type="text" name="name" placeholder="Enter Full Name" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <div class="form-group">
                    <label>UTHM EMAIL</label>
                    <i class="fa-solid fa-envelope input-icon"></i>
                    <input type="email" name="email" placeholder="staff@uthm.edu.my" required>
                </div>
                <div class="form-group">
                    <label>PHONE NUMBER</label>
                    <i class="fa-solid fa-phone input-icon"></i>
                    <input type="text" name="phone_number" placeholder="0123456789" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <div class="form-group">
                    <label>STAFF / STUDENT ID</label>
                    <i class="fa-solid fa-id-card input-icon"></i>
                    <input type="text" name="staff_number" placeholder="PKU1001" required>
                </div>
                <div class="form-group">
                    <label>ROLE</label>
                    <i class="fa-solid fa-user-tag input-icon"></i>
                    <select name="role" required style="padding-left: 40px;">
                        <option value="" disabled selected>Select Role</option>
                        <option value="doctor">Doctor</option>
                        <option value="verifier">Verifier</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>PASSWORD</label>
                <div class="password-wrapper">
                    <i class="fa-solid fa-lock input-icon" style="top: 10px; left: 15px;"></i>
                    <input type="password" name="password" id="password" placeholder="••••••••" required>
                    <i class="fa-solid fa-eye toggle-password" onclick="togglePass('password', this)"></i>
                </div>
                <div class="requirement-container">
                    <span class="requirement" id="length">8+ Chars</span>
                    <span class="requirement" id="upper">Uppercase</span>
                    <span class="requirement" id="number">Number</span>
                    <span class="requirement" id="special">Symbol</span>
                </div>
            </div>

            <div class="form-group">
                <label>CONFIRM PASSWORD</label>
                <div class="password-wrapper">
                    <i class="fa-solid fa-shield-check input-icon" style="top: 10px; left: 15px;"></i>
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="••••••••" required>
                    <i class="fa-solid fa-eye toggle-password" onclick="togglePass('confirm_password', this)"></i>
                </div>
                <div id="match_indicator" style="font-size: 10px; margin-top: 5px; font-weight: bold;"></div>
            </div>

            <button type="submit" class="reg-btn" id="submitBtn" disabled>Sign Up Now</button>
        </form>

        <div class="footer-link">
            Already have an account? <a href="../login/login.php">Login here</a>
        </div>
    </div>
</div>

<script>
    function togglePass(id, icon) {
        const input = document.getElementById(id);
        const type = input.type === 'password' ? 'text' : 'password';
        input.type = type;
        icon.classList.toggle('fa-eye');
        icon.classList.toggle('fa-eye-slash');
    }

    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    const submitBtn = document.getElementById('submitBtn');
    const matchIndicator = document.getElementById('match_indicator');

    const requirements = {
        length: /^.{8,}$/,
        upper: /[A-Z]/,
        number: /[0-9]/,
        special: /[@$!%*?&]/
    };

    function validate() {
        let isPassValid = true;
        Object.keys(requirements).forEach(key => {
            const el = document.getElementById(key);
            if (requirements[key].test(password.value)) {
                el.classList.add('valid'); el.classList.remove('invalid');
            } else {
                el.classList.add('invalid'); el.classList.remove('valid');
                isPassValid = false;
            }
        });

        const isMatch = password.value === confirmPassword.value && password.value !== "";
        if (confirmPassword.value !== "") {
            matchIndicator.textContent = isMatch ? "✓ Passwords Match" : "✗ No Match";
            matchIndicator.style.color = isMatch ? "#1a7f4e" : "#d9534f";
        }

        submitBtn.disabled = !(isPassValid && isMatch);
    }

    password.addEventListener('input', validate);
    confirmPassword.addEventListener('input', validate);

    // URL Error check
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('error') === 'invalid_domain') {
        Swal.fire({ icon: 'error', title: 'Unauthorized Domain', text: 'Use @uthm.edu.my email only.', confirmButtonColor: '#2b7a9e' });
    }
</script>
</body>
</html>