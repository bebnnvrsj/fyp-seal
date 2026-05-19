<?php
session_start();
require '../db_connect.php'; 
require_once '../GoogleAuthenticator.php';

$ga = new GoogleAuthenticator();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone_number = mysqli_real_escape_string($conn, $_POST['phone_number']);
    $staff_number = mysqli_real_escape_string($conn, $_POST['staff_number']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // 1. Semak Password Match (Kritikal!)
    if ($password !== $confirm_password) {
        echo "<script>alert('Error: Passwords do not match!'); window.location.href='register.php';</script>";
        exit();
    }

    // 2. Semak Domain Email (@uthm.edu.my)
    $allowed_domain = "@uthm.edu.my";
    if (substr($email, -strlen($allowed_domain)) !== $allowed_domain) {
        header("Location: register.php?error=invalid_domain");
        exit();
    }

    // 3. Semak jika Email sudah wujud dalam jadual users
    $check_sql = "SELECT userID FROM users WHERE username = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo "<script>alert('Error: Email is already in use.'); window.location.href='register.php';</script>";
        exit();
    }

    // Jana secret key untuk 2FA dan Hash Password
    $secret = $ga->createSecret();
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // MULA TRANSAKSI SQL
    $conn->begin_transaction();

    try {
        // 4. Masukkan ke jadual 'users'
        // Simpan data asas: username, password, role, 2FA secret, phone, status
        $sql_users = "INSERT INTO users (username, password, role, google_auth_secret, phone_number, status, created_at) 
                      VALUES (?, ?, ?, ?, ?, 'active', NOW())";
        $stmt_u = $conn->prepare($sql_users);
        $stmt_u->bind_param("sssss", $email, $hashed_password, $role, $secret, $phone_number);
        $stmt_u->execute();
        
        $newUserID = $conn->insert_id;

        // 5. Masukkan ke jadual PROFIL mengikut Role
        if ($role === 'doctor') {
            $sql_prof = "INSERT INTO doctor_profiles (doctorID, name, staff_number, mmc_number) VALUES (?, ?, ?, 'PENDING')";
            $stmt_p = $conn->prepare($sql_prof);
            $stmt_p->bind_param("iss", $newUserID, $name, $staff_number);
        } elseif ($role === 'admin') {
            $sql_prof = "INSERT INTO admin_profiles (adminID, name, staff_number) VALUES (?, ?, ?)";
            $stmt_p = $conn->prepare($sql_prof);
            $stmt_p->bind_param("iss", $newUserID, $name, $staff_number);
        } else {
            // Role: Verifier (Staf UTHM)
            $sql_prof = "INSERT INTO verifier_profiles (verifierID, name) VALUES (?, ?)";
            $stmt_p = $conn->prepare($sql_prof);
            $stmt_p->bind_param("is", $newUserID, $name);
        }
        $stmt_p->execute();

        // 6. Audit Log (Rekod pendaftaran)
        $logAction = "USER_REGISTRATION";
        $logResource = "New Account Created: $email (Role: $role)";
        $audit_stmt = $conn->prepare("INSERT INTO auditlog (userID, action, resource) VALUES (?, ?, ?)");
        $audit_stmt->bind_param("iss", $newUserID, $logAction, $logResource);
        $audit_stmt->execute();

        // go to db when semua dah ok
        $conn->commit();

        $_SESSION['temp_secret'] = $secret;
        $_SESSION['temp_email'] = $email;

        echo "<script>alert('Registration Successful! Please setup your 2FA.'); window.location.href='setup_2fa.php';</script>";

    } catch (Exception $e) {
        // if ada error, cancel everything
        $conn->rollback();
        echo "<script>alert('Registration Failed: " . addslashes($e->getMessage()) . "'); window.location.href='register.php';</script>";
    }
}
?>