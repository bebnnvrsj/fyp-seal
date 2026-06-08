<?php
$host = "localhost"; 
$db_user = "sealpje"; 
$db_pass = "WGbHq55G#*qBGR*n"; 
$db_name = "sealpje_meddoqs"; 

$conn = new mysqli($host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>