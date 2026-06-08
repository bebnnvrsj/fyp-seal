<?php
session_start();

// 1. Kosongkan semua data sesi
$_SESSION = [];

// 2. Padam cookie sesi pada pelayar untuk keselamatan ekstra
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// 3. Musnahkan sesi sepenuhnya di server
session_destroy();

// 4. Halang butang 'Back' pelayar daripada memaparkan data lama (Security)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 5. Redirect to login page
header("Location: login.php"); 
exit();
?>