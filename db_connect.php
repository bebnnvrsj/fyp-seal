<?php
$host = "localhost"; // Ini kekal localhost kerana PHP dan MySQL duduk di server yang sama
$db_user = "sealpje"; // Username cPanel anda (Atau username db spesifik jika anda cipta di Wizard)
$db_pass = "WGbHq55G#*qBGR*n"; // Password cPanel / DB anda
$db_name = "sealpje_meddoqs"; // Nama database baharu di cPanel tadi

$conn = new mysqli($host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>