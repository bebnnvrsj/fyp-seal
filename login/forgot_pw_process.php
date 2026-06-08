<?php
// Masukkan library PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// PANGGIL FAIL RAHSIA DARI FOLDER UTAMA (ROOT)
if (file_exists('../config_smtp.php')) {
    require_once '../config_smtp.php';
} else {
    define('SMTP_USER', 'placeholder_github@gmail.com');
    define('SMTP_PASS', 'placeholder');
}

require '../vendor/phpmailer/src/Exception.php';
require '../vendor/phpmailer/src/PHPMailer.php';
require '../vendor/phpmailer/src/SMTP.php';
require '../db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = mysqli_real_escape_string($conn, $_POST['email']);

    // Semak jika emel wujud dalam jadual users
    $sql = "SELECT userID FROM users WHERE username = ?";   
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Jana Token Kriptografi Selamat & Tempoh Luput (30 Minit)
        $token = bin2hex(random_bytes(32));
        $expires = date("Y-m-d H:i:s", strtotime("+30 minutes"));

        // Simpan token ke dalam database untuk keselamatan cross-device
        $updateSql = "UPDATE users SET reset_token = ?, token_expires = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE username = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("ss", $token, $email);
        $updateStmt->execute();

        $mail = new PHPMailer(true);

        try {
            //  SMTP Gmail Server Set
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER; 
            $mail->Password   = SMTP_PASS;    // App Password dari Google
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Sender & Receiver Settings
            $mail->setFrom('adamuqrii@gmail.com', 'SEAL Medical Portal');
            $mail->addReplyTo('no-reply@seal-system.com', 'SEAL Medical Portal');
            $mail->addAddress($email);

            // Email content
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request - SEAL';
            
            // Masukkan parameter token ke dalam pautan URL hantaran emel
            $resetLink = "https://seal-uthm.site/login/reset_pw.php?token=" . $token; 
            
            // Styled HTML Box Template
            $mail->Body = "
            <div style='background-color: #caf0f8; padding: 40px 20px; font-family: \"Segoe UI\", Tahoma, sans-serif;'>
                <div style='max-width: 550px; margin: 0 auto; background-color: #ffffff; border-radius: 20px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.08); border: 1px solid #ddd;'>
                    
                    <div style='background-color: #183055; padding: 30px; text-align: center;'>
                        <h1 style='color: #ffffff; margin: 0; font-size: 28px; letter-spacing: 2px; font-weight: 800;'>SEAL</h1>
                        <p style='color: #48cae4; margin: 5px 0 0 0; font-size: 13px; font-weight: 600;'>MEDICAL DOCUMENT PORTAL</p>
                    </div>
                    
                    <div style='padding: 40px 35px; color: #333333; line-height: 1.6;'>
                        <h2 style='color: #183055; font-size: 22px; margin-top: 0; margin-bottom: 15px;'>Password Reset Request</h2>
                        <p style='font-size: 15px;'>Hello,</p>
                        <p style='font-size: 15px;'>We received a request to reset the password associated with your account. Click the button below to secure your profile with a new password:</p>
                        
                        <div style='text-align: center; margin: 35px 0;'>
                            <a href='$resetLink' style='background-color: #2b7a9e; color: #ffffff; text-decoration: none; padding: 14px 30px; font-size: 16px; font-weight: bold; border-radius: 10px; display: inline-block; box-shadow: 0 4px 10px rgba(43, 122, 158, 0.25);'>Reset My Password</a>
                        </div>
                        
                        <p style='font-size: 13px; color: #666666;'>If the button above does not work, copy and paste this URL into your web browser:</p>
                        <p style='font-size: 12px; background-color: #f8f9fa; padding: 10px; border-radius: 6px; word-break: break-all; border: 1px solid #eee;'>
                            <a href='$resetLink' style='color: #2b7a9e;'>$resetLink</a>
                        </p>
                        
                        <hr style='border: 0; border-top: 1px solid #eeeeee; margin: 30px 0;'>
                        <p style='font-size: 13px; color: #999999; margin-bottom: 0;'>If you did not request this modification, no further action is required. Your account security remains intact.</p>
                    </div>
                    
                    <div style='background-color: #f4f6f9; padding: 20px; text-align: center; font-size: 12px; color: #777777; border-top: 1px solid #eeeeee;'>
                        &copy; " . date('Y') . " SEAL System. All rights reserved.
                    </div>
                </div>
            </div>";

            $mail->send();
            
            header("Location: forgot_pw.php?status=sent");   
        } catch (Exception $e) {
            header("Location: forgot_pw.php?status=error");
        }
    } else {
        header("Location: forgot_pw.php?status=not_found");
    }
    exit();
}
?>