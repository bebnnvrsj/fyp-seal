<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "meddoqs"; // Ganti dengan nama database anda

$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>