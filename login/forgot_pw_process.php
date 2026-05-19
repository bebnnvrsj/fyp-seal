<?php
// Masukkan library PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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
        $mail = new PHPMailer(true);

        try {
            //  SMTP Gmail Server Set
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'adamuqrii@gmail.com'; 
            $mail->Password   = 'jaujitzxavbqcvic';    // App Password dari Google
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Receiver
            $mail->setFrom('no-reply@seal-system.com', 'SEAL System');
            $mail->addAddress($email);

            //Email content
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request - SEAL';
            $resetLink = "http://localhost/fyp/login/reset_pw.php"; 
            
            $mail->Body    = "Please click the link below to reset your password:<br><br>
                              <a href='$resetLink'>$resetLink</a><br><br>
                              If you did not request this, please ignore this email.";

            $mail->send();
            
            session_start();
            $_SESSION['reset_email'] = $email; // Simpan untuk reset_pw.php
            header("Location: forgot_pw.php?status=sent");   
        } catch (Exception $e) {
            header("Location: forgot_pw.php?status=error");
        }
    } else {
        header("Location: forgot_pw.php?status=not_found");
    }
    exit();
}