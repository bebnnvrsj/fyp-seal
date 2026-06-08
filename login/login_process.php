<?php
session_start();
require '../db_connect.php';

if (isset($_POST['email']) && isset($_POST['password'])) {
    $username_input = trim($_POST['email']);
    $password_input = $_POST['password'];

    // SQL dengan LEFT JOIN untuk menarik nama daripada jadual profil yang berkaitan
    $sql = "SELECT u.*, 
                   d.name AS doctor_name, 
                   a.name AS admin_name, 
                   v.name AS verifier_name,
                   d.staff_number AS doctor_staff,
                   a.staff_number AS admin_staff
            FROM users u
            LEFT JOIN doctor_profiles d ON u.userID = d.doctorID
            LEFT JOIN admin_profiles a ON u.userID = a.adminID
            LEFT JOIN verifier_profiles v ON u.userID = v.verifierID
            WHERE u.username = ?";
            
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("s", $username_input);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $userData = $result->fetch_assoc();

            // Semak jika akaun tidak aktif
            if ($userData['status'] === 'inactive') {
                header("Location: login.php?error=account_disabled");
                exit();
            }

            // Sahkan kata laluan
            if (password_verify($password_input, $userData['password'])) {
                
                // Tentukan nama sebenar dan ID staf mengikut peranan (role)
                $realName = 'User';
                $staffID = '';

                if ($userData['role'] == 'doctor') {
                    $realName = $userData['doctor_name'] ?? 'Doctor';
                    $staffID = $userData['doctor_staff'] ?? '';
                } elseif ($userData['role'] == 'admin') {
                    $realName = $userData['admin_name'] ?? 'Admin';
                    $staffID = $userData['admin_staff'] ?? '';
                } elseif ($userData['role'] == 'verifier') {
                    $realName = $userData['verifier_name'] ?? 'Verifier';
                    $staffID = ''; // Verifier biasanya tiada staff number
                }

                // Simpan maklumat ke dalam session pending untuk pengesahan 2FA
                $_SESSION['2fa_pending'] = [
                    'userID'       => $userData['userID'],
                    'username'     => $userData['username'],
                    'role'         => $userData['role'],
                    'name'         => $realName,
                    'staff_number' => $staffID
                ];

                header("Location: verify_2fa.php");
                exit();
            } else {
                header("Location: login.php?error=invalid_password");
                exit();
            }
        } else {
            header("Location: login.php?error=user_not_found");
            exit();
        }
    } else {
        die("Ralat SQL: " . $conn->error);
    }
} else {
    header("Location: login.php");
    exit();
}
?>