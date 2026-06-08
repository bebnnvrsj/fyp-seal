<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up | SEAL</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary-blue: #2b7a9e;
            --dark-blue: #183055;
            --bg-gradient: linear-gradient(to bottom right, #caf0f8, #90e0ef, #48cae4);
        }

        * { box-sizing: border-box; }

        body { 
            margin: 0; font-family: "Segoe UI", Tahoma, sans-serif; 
            background: var(--bg-gradient); 
            min-height: 100vh; display: flex; justify-content: center; align-items: center; 
            padding: 20px;
        }
        
        .register-wrapper { 
            display: flex; 
            width: 1100px; 
            height: 700px; /* Diubah suai sedikit agar muat dengan input baru */
            background: #ffffff; border-radius: 30px; overflow: hidden; 
            box-shadow: 0px 15px 35px rgba(0,0,0,0.2); 
        }
        
        .brand-side { 
            flex: 1; background-color: var(--dark-blue); color: white; 
            display: flex; flex-direction: column; justify-content: center; 
            padding: 60px; position: relative; 
        }

        .brand-side::after {
            content: ""; position: absolute; bottom: -50px; right: -50px;
            width: 200px; height: 200px; background: rgba(255,255,255,0.05); border-radius: 50%;
        }

        .brand-side h1 { font-size: 42px; margin: 0; letter-spacing: 3px; font-weight: 800; }
        .brand-side p { font-size: 18px; opacity: 0.8; margin-top: 15px; line-height: 1.6; margin-bottom: 30px; }

        .form-side { 
            width: 500px; 
            padding: 40px 50px; 
            display: flex; flex-direction: column; justify-content: center; 
            overflow-y: auto; 
        }
        
        .form-group { position: relative; margin-bottom: 15px; }
        .form-group label { display: block; font-size: 11px; font-weight: 700; color: var(--dark-blue); margin-bottom: 6px; margin-left: 2px; text-transform: uppercase; }
        
        input, select { 
            width: 100%; padding: 12px 15px 12px 42px; 
            border-radius: 12px; border: 1px solid #ddd; font-size: 14px; 
            box-sizing: border-box; background: #fcfcfc; position: relative; z-index: 1; 
            transition: 0.3s;
        }

        input:focus, select:focus {
            border-color: var(--primary-blue); background: #fff; outline: none;
            box-shadow: 0 0 0 4px rgba(43, 122, 158, 0.1);
        }

        .form-group i.input-icon { position: absolute; left: 15px; top: 38px; color: #aaa; z-index: 10; pointer-events: none; }
        .password-wrapper { position: relative; display: flex; align-items: center; width: 100%; }
        .password-wrapper i.input-icon { top: 14px; }
        .password-wrapper input { padding-right: 45px; }
        .toggle-password { position: absolute; right: 15px; cursor: pointer; color: #888; z-index: 10; }
        
        .requirement-container { margin-top: 8px; display: flex; flex-wrap: wrap; gap: 5px; }
        .requirement { font-size: 10px; padding: 3px 8px; border-radius: 6px; background: #eee; transition: 0.3s; color: #777; font-weight: 600; }
        .valid { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .invalid { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        
        .reg-btn { 
            background-color: var(--primary-blue); border: none; color: white; 
            padding: 14px; width: 100%; font-size: 16px; font-weight: 700; border-radius: 12px; 
            cursor: pointer; transition: 0.3s ease; margin-top: 15px; 
            box-shadow: 0 4px 12px rgba(43, 122, 158, 0.3);
        }
        
        .reg-btn:hover:not(:disabled) { background-color: var(--dark-blue); transform: translateY(-2px); }
        .reg-btn:disabled { opacity: 0.5; cursor: not-allowed; box-shadow: none; }
        
        .footer-link { text-align: center; margin-top: 20px; font-size: 13px; color: #666; }
        .footer-link a { color: var(--primary-blue); font-weight: bold; text-decoration: none; }
        .footer-link a:hover { text-decoration: underline; }

        /* Animasi Transisi Slaid bagi MMC & Organisasi */
        .dynamic-container {
            max-height: 0; overflow: hidden; opacity: 0; transform: translateY(-8px);
            transition: max-height 0.4s ease-in-out, opacity 0.4s ease-in-out, transform 0.4s ease-in-out, margin-bottom 0.4s ease-in-out;
        }
        .dynamic-container.show {
            max-height: 85px; opacity: 1; transform: translateY(0); margin-bottom: 15px;
        }

        @media (max-width: 1050px) {
            body { padding: 10px; }
            .register-wrapper { width: 100%; max-width: 500px; height: auto; max-height: 95vh; }
            .brand-side { display: none; } 
            .form-side { width: 100%; padding: 40px 30px; }
        }
    </style>
</head>
<body>

<div class="register-wrapper">
    <div class="brand-side">
        <h1>JOIN SEAL</h1>
        <p>Blockchain-Powered Medical Document Verification System.</p>
        <div style="background: rgba(255,255,255,0.1); padding: 18px; border-radius: 12px; border-left: 4px solid #48cae4; font-size: 14px; line-height: 1.5;">
            <i class="fa-solid fa-circle-info" style="color: #48cae4; margin-right: 5px;"></i> Use email <strong>@uthm.edu.my</strong> to sign up.
        </div>
    </div>

    <div class="form-side">
        <h2 style="color: var(--dark-blue); margin: 0; font-size: 28px; font-weight: 800;">Create Account</h2>
        <p style="color: #777; font-size: 13px; margin-bottom: 25px;">Sila lengkapkan butiran pendaftaran di bawah.</p>

        <form action="register_process.php" method="POST" id="registerForm">
            <div class="form-group">
                <label>Full Name</label>
                <i class="fa-solid fa-user input-icon"></i>
                <input type="text" name="name" placeholder="Enter Full Name" required>
            </div>

            <div class="form-group">
                <label>UTHM Email</label>
                <i class="fa-solid fa-envelope input-icon"></i>
                <input type="email" name="email" placeholder="staff@uthm.edu.my" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 15px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Staff ID</label>
                    <i class="fa-solid fa-id-card input-icon"></i>
                    <input type="text" name="staff_number" placeholder="12345" required>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label>Role</label>
                    <i class="fa-solid fa-user-tag input-icon"></i>
                    <select name="role" id="roleSelect" required style="padding-left: 42px;">
                        <option value="" disabled selected>Select Role</option>
                        <option value="doctor">Doctor</option>
                        <option value="verifier">Verifier</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>

            <div id="mmcContainer" class="form-group dynamic-container">
                <label>MMC Number</label>
                <i class="fa-solid fa-user-doctor input-icon" style="top: 38px;"></i>
                <input type="text" name="mmc_number" id="mmcInput" placeholder="e.g. 12345" pattern="\d{4,6}">
            </div>

            <div id="orgContainer" class="form-group dynamic-container">
                <label>Organization / Faculty / Center</label>
                <i class="fa-solid fa-building-user input-icon" style="top: 38px;"></i>
                <select name="organization_name" id="orgSelect" style="padding-left: 42px;">
                    <option value="" disabled selected>Choose Dept / Faculty</option>
                    <option value="Fakulti Kejuruteraan Awam dan Alam Sekitar (FKAAS)">Fakulti Kejuruteraan Awam dan Alam Sekitar (FKAAS)</option>
                    <option value="Fakulti Kejuruteraan Elektrik dan Elektronik (FKEE)">Fakulti Kejuruteraan Elektrik dan Elektronik (FKEE)</option>
                    <option value="Fakulti Kejuruteraan Mekanikal dan Pembuatan (FKMP)">Fakulti Kejuruteraan Mekanikal dan Pembuatan (FKMP)</option>
                    <option value="Fakulti Pengurusan Teknologi dan Perniagaan (FPTP)">Fakulti Pengurusan Teknologi dan Perniagaan (FPTP)</option>
                    <option value="Fakulti Pendidikan Teknikal dan Vokasional (FPTV)">Fakulti Pendidikan Teknikal dan Vokasional (FPTV)</option>
                    <option value="Fakulti Sains Komputer dan Teknologi Maklumat (FSKTM)">Fakulti Sains Komputer dan Teknologi Maklumat (FSKTM)</option>
                    <option value="Fakulti Sains Gunaan dan Teknologi (FAST)">Fakulti Sains Gunaan dan Teknologi (FAST)</option>
                    <option value="Fakulti Teknologi Kejuruteraan (FTK)">Fakulti Teknologi Kejuruteraan (FTK)</option>
                    <option value="Pusat Pengajian Diploma (PPD)">Pusat Pengajian Diploma (PPD)</option>
                    <option value="Pusat Pengajian Umum dan Kokurikulum (PPUK)">Pusat Pengajian Umum dan Kokurikulum (PPUK)</option>
                    <option value="Pusat Pengajian Bahasa (PPB)">Pusat Pengajian Bahasa (PPB)</option>
                    <option value="Pejabat Antarabangsa (IO)">Pejabat Antarabangsa (IO / International Office)</option>
                    <option value="Pusat Pembelajaran Maya (CVL)">Pusat Pembelajaran Maya (CVL)</option>
                    <option value="Pusat Pembelajaran Berterusan dan APEL (PPB APEL)">Pusat Pembelajaran Berterusan dan APEL</option>
                    <option value="Pejabat Pengurusan Akademik (PPA)">Pejabat Pengurusan Akademik (PPA)</option>
                    <option value="Teaching Factory (TF)">Teaching Factory (TF UTHM)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Password</label>
                <div class="password-wrapper">
                    <i class="fa-solid fa-lock input-icon" style="left: 15px;"></i>
                    <input type="password" name="password" id="password" placeholder="••••••••" required>
                    <i class="fa-solid fa-eye toggle-password" onclick="togglePass('password', this)"></i>
                </div>
                <div class="requirement-container">
                    <span class="requirement" id="length">Min 12 Chars</span>
                    <span class="requirement" id="upper">Uppercase</span>
                    <span class="requirement" id="lower">Lowercase</span>
                    <span class="requirement" id="number">Number</span>
                    <span class="requirement" id="special">Symbol (@$!%*?&_)</span>
                </div>
            </div>

            <div class="form-group">
                <label>Confirm Password</label>
                <div class="password-wrapper">
                    <i class="fa-solid fa-lock input-icon" style="left: 15px;"></i>
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="••••••••" required>
                    <i class="fa-solid fa-eye toggle-password" onclick="togglePass('confirm_password', this)"></i>
                </div>
                <div id="match_indicator" style="font-size: 11px; margin-top: 6px; font-weight: bold; padding-left: 2px;"></div>
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

    const roleSelect = document.getElementById('roleSelect');
    const mmcContainer = document.getElementById('mmcContainer');
    const mmcInput = document.getElementById('mmcInput');
    const orgContainer = document.getElementById('orgContainer');
    const orgSelect = document.getElementById('orgSelect');

    // Menguruskan paparan input secara dinamik berdasarkan peranan pilihan pengguna
    roleSelect.addEventListener('change', function() {
        if (this.value === 'doctor') {
            mmcContainer.classList.add('show');
            mmcInput.setAttribute('required', 'required');
            
            orgContainer.classList.remove('show');
            orgSelect.removeAttribute('required');
            orgSelect.value = '';
        } else if (this.value === 'verifier') {
            orgContainer.classList.add('show');
            orgSelect.setAttribute('required', 'required');
            
            mmcContainer.classList.remove('show');
            mmcInput.removeAttribute('required');
            mmcInput.value = '';
        } else {
            mmcContainer.classList.remove('show');
            mmcInput.removeAttribute('required');
            mmcInput.value = '';
            
            orgContainer.classList.remove('show');
            orgSelect.removeAttribute('required');
            orgSelect.value = '';
        }
    });

    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    const submitBtn = document.getElementById('submitBtn');
    const matchIndicator = document.getElementById('match_indicator');

    const requirements = {
        length: /^.{12,}$/,
        upper: /[A-Z]/,
        lower: /[a-z]/,
        number: /[0-9]/,
        special: /[@$!%*?&_]/
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
            matchIndicator.textContent = isMatch ? "✓ Passwords Match" : "✗ Passwords do not match";
            matchIndicator.style.color = isMatch ? "#15803d" : "#b91c1c";
        }

        submitBtn.disabled = !(isPassValid && isMatch);
    }

    password.addEventListener('input', validate);
    confirmPassword.addEventListener('input', validate);

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('error') === 'invalid_domain') {
        Swal.fire({ icon: 'error', title: 'Unauthorized Domain', text: 'Sila gunakan e-mel rasmi @uthm.edu.my sahaja.', confirmButtonColor: '#2b7a9e' });
    }
</script>
</body>
</html>