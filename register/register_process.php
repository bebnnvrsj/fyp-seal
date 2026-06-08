<?php
session_start();
require '../db_connect.php'; 
require_once '../GoogleAuthenticator.php';

$ga = new GoogleAuthenticator();

// Helper function to render uniform SweetAlert notifications
function triggerSweetAlert($icon, $title, $text, $redirectUrl) {
    echo "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        <style>
            body { font-family: 'Segoe UI', sans-serif; background-color: #caf0f8; }
        </style>
    </head>
    <body>
        <script>
            Swal.fire({
                icon: '{$icon}',
                title: '{$title}',
                text: '{$text}',
                confirmButtonColor: '#2b7a9e'
            }).then(() => {
                window.location.href = '{$redirectUrl}';
            });
        </script>
    </body>
    </html>
    ";
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize basic inputs from the registration portal payload
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $staff_number = mysqli_real_escape_string($conn, $_POST['staff_number']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Capture optional values depending on the role path selection
    $mmc_number = isset($_POST['mmc_number']) ? mysqli_real_escape_string($conn, trim($_POST['mmc_number'])) : null;
    $organization_name = isset($_POST['organization_name']) ? mysqli_real_escape_string($conn, $_POST['organization_name']) : null;

    // 1. Password mismatch fallback guard clause
    if ($password !== $confirm_password) {
        triggerSweetAlert('error', 'Mismatch Error', 'Passwords do not match!', 'register.php');
    }

    // 2. Validate UTHM Academic Domain parameters 
    if (!str_ends_with($email, '@uthm.edu.my')) {
        header("Location: register.php?error=invalid_domain");
        exit();
    } 

    // 3. Prevent duplicate account registrations inside the users database
    $check_sql = "SELECT userID FROM users WHERE username = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        triggerSweetAlert('warning', 'Email In Use', 'The email address is already associated with another account.', 'register.php');
    }

    // Generate secure keys for Google Authenticator TOTP configurations
    $secret = $ga->createSecret();

    // Secure the password using standard Bcrypt hashing mechanisms
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Initialize atomic safe SQL transaction mapping blocks
    $conn->begin_transaction();

    try {
        // 4. Populate the core parent authentication table (users) - note: 'name' is dropped here!
        $sql_users = "INSERT INTO users (username, password, role, google_auth_secret, status, created_at) 
                      VALUES (?, ?, ?, ?, 'active', NOW())";
        $stmt_u = $conn->prepare($sql_users);
        $stmt_u->bind_param("ssss", $email, $hashed_password, $role, $secret);
        $stmt_u->execute();
        
        // Grab the auto-increment primary key ID to map into child profiles
        $newUserID = $conn->insert_id;

        // 5. Route profile parameters into specific child tables based on roles
        if ($role === 'doctor') {
            $sql_prof = "INSERT INTO doctor_profiles (doctorID, name, staff_number, mmc_number) VALUES (?, ?, ?, ?)";
            $stmt_p = $conn->prepare($sql_prof);
            $stmt_p->bind_param("isss", $newUserID, $name, $staff_number, $mmc_number);
        } elseif ($role === 'admin') {
            $sql_prof = "INSERT INTO admin_profiles (adminID, name, staff_number) VALUES (?, ?, ?)";
            $stmt_p = $conn->prepare($sql_prof);
            $stmt_p->bind_param("iss", $newUserID, $name, $staff_number);
        } else {
            // Role: Verifier -> Now saves both staff_number and organization_name cleanly
            $sql_prof = "INSERT INTO verifier_profiles (verifierID, staff_number, name, organization_name) VALUES (?, ?, ?, ?)";
            $stmt_p = $conn->prepare($sql_prof);
            $stmt_p->bind_param("isss", $newUserID, $staff_number, $name, $organization_name);
        }
        $stmt_p->execute();

        // 6. Log entry initialization for systemic security audits
        $logAction = "USER_REGISTRATION";
        $logResource = "New Account Created: $email (Role: $role)";
        $audit_stmt = $conn->prepare("INSERT INTO auditlog (userID, action, resource) VALUES (?, ?, ?)");
        $audit_stmt->bind_param("iss", $newUserID, $logAction, $logResource);
        $audit_stmt->execute();

        // Commit all safe transactional queries to the database server
        $conn->commit();

        $_SESSION['temp_secret'] = $secret;
        $_SESSION['temp_email'] = $email;

        triggerSweetAlert('success', 'Registration Successful', 'Please setup your 2FA.', 'setup_2fa.php');

    } catch (Exception $e) {
        // Rollback and discard changes in case of any processing exceptions
        $conn->rollback();
        triggerSweetAlert('error', 'Registration Failed', 'An error occurred while registering your account: ' . $e->getMessage(), 'register.php');
    }
}
?>