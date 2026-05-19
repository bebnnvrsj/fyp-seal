<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../login.php");
    exit();
}
require '../db_connect.php'; 

// Fetch current user details for the sidebar/hero
$userID = $_SESSION['userID']; 
$sql = "SELECT * FROM users WHERE userID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userID);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
    
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Documents - SEAL</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root {
            --sidebar-width: 260px;
            --header-bg: #2b7a9e;
            --main-bg: linear-gradient(to bottom, #caf0f8, #90e0ef, #48cae4);
            --dark-blue: #183055;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, sans-serif;
            background: var(--main-bg);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
        }

        /* ====== SIDEBAR ====== */
        .sidebar { width: var(--sidebar-width); height: 100vh; background-color: var(--dark-blue); color: white; position: fixed; left: 0; top: 0; transition: transform 0.3s ease; z-index: 1000; display: flex; flex-direction: column; }
        .sidebar.closed { transform: translateX(-100%); }
        .sidebar-header { padding: 20px; background-color: #122542; display: flex; align-items: center; gap: 15px; font-weight: bold; }
        .sidebar-menu { list-style: none; padding: 0; margin: 20px 0; }
        .sidebar-menu li a { padding: 15px 25px; display: flex; align-items: center; gap: 15px; color: #d1d9e6; text-decoration: none; transition: 0.2s; }
        .sidebar-menu li a:hover { background-color: #2b7a9e; color: white; }
        .sidebar-menu li a.active { background-color: #2b7a9e; color: white; border-left: 4px solid #fff; }

        /* ====== MAIN WRAPPER ====== */
        .main-wrapper { flex: 1; display: flex; flex-direction: column; margin-left: var(--sidebar-width); transition: margin-left 0.3s ease; width: 100%; }
        .main-wrapper.full-width { margin-left: 0; }

        .header { height: 56px; background-color: var(--header-bg); display: flex; align-items: center; justify-content: space-between; padding: 0 24px; color: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .toggle-btn { cursor: pointer; font-size: 20px; }

        /* ====== CONTAINER ====== */
        .container { 
            width: 95%; 
            max-width: 1600px; 
            margin: 30px auto; 
            padding: 0 20px; 
            display: flex; 
            flex-direction: column; 
            gap: 25px; 
        }

        /* Hero section */
        .page-hero {
            background: white;
            border-radius: 15px;
            padding: 35px;
            display: flex;
            align-items: center;
            gap: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .hero-avatar {
            width: 80px; height: 80px;
            background: #e3f2fd; color: var(--header-bg);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 35px; border: 4px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .hero-text h1 { margin: 0; color: var(--dark-blue); font-size: 24px; }
        .hero-text p { margin: 5px 0; color: #666; font-size: 14px; }

        /* Selection Cards */
        .selection-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            width: 100%;
        }

        .selection-card {
            background: white;
            border-radius: 15px;
            padding: 40px 30px;
            text-align: center;
            text-decoration: none;
            color: var(--dark-blue);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .selection-card:hover {
            transform: translateY(-8px);
            background: #f8fdff;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            border-color: var(--header-bg);
        }

        .selection-card i { font-size: 60px; color: var(--header-bg); margin-bottom: 20px; }
        .selection-card h2 { font-size: 20px; margin: 0; font-weight: 700; }
        .selection-card p { font-size: 14px; color: #777; margin-top: 10px; line-height: 1.4; max-width: 300px; }

        @media (max-width: 768px) { 
            .selection-grid { grid-template-columns: 1fr; }
            .page-hero { flex-direction: column; text-align: center; }
        }
        /* ====== SUBMENU STYLES ====== */
        .has-submenu {
            position: relative;
        }

        /* Hide submenu by default */
        .submenu {
            list-style: none;
            padding: 0;
            margin: 0;
            max-height: 0; /* Hidden state */
            overflow: hidden;
            background-color: #122542; /* Slightly darker than sidebar for contrast */
            transition: max-height 0.4s ease-out; /* Smooth slide effect */
        }

        /* Show submenu when hovering over the parent LI */
        .has-submenu:hover .submenu {
            max-height: 200px; /* Adjust based on content size */
        }

        /* Submenu Link Styling */
        .submenu li a {
            padding: 12px 25px 12px 60px !important; /* Extra padding to the left to indent */
            font-size: 13px !important;
            color: #a0aec0 !important;
        }

        .submenu li a:hover {
            color: white !important;
            background-color: #2b7a9e !important;
        }

        /* Optional: Add an arrow icon to indicate a submenu exists */
        .has-submenu > a::after {
            content: '\f107'; /* FontAwesome Angle Down */
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            float: right;
            font-size: 12px;
            transition: transform 0.3s;
        }

        .has-submenu:hover > a::after {
            transform: rotate(180deg);
        }
    </style>
</head>
<body>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header"><i class="fa-solid fa-user-doctor"></i> <span>SEAL</span></div>
    <ul class="sidebar-menu">
        <li><a href="home_doctor.php"><i class="fa-solid fa-house"></i> Dashboard</a></li>
        
        <li class="has-submenu">
            <a href="create_document.php" class="active"><i class="fa-solid fa-plus"></i> Create Document</a>
            <ul class="submenu">
                <li><a href="create_mc.php"><i class="fa-solid fa-file-medical"></i> Medical Certificate</a></li>
                <li><a href="create_timeslip.php"><i class="fa-solid fa-clock-rotate-left"></i> Time Slip</a></li>
            </ul>
        </li>

        <li><a href="manage_documents.php"><i class="fa-solid fa-file-pen"></i> Manage Documents</a></li>
        <li><a href="view_history.php"><i class="fa-solid fa-database"></i> Issuance History</a></li>
        <li><a href="../profile.php"><i class="fa-solid fa-user-gear"></i> Profile Settings</a></li>
        <li><a href="../login/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
    </ul>
</div>
<div class="main-wrapper" id="mainWrapper">
    <div class="header">
        <div class="header-left">
            <i class="fa-solid fa-bars toggle-btn" onclick="toggleSidebar()"></i>
            <span style="font-weight: 600; margin-left: 15px;">Doctor Portal</span>
        </div>
    </div>

    <div class="container">
        <div class="page-hero">
            <div class="hero-avatar">
                <i class="fa-solid fa-file-medical"></i>
            </div>
            <div class="hero-text">
                <h1>Document Issuance Center</h1>
                <p>Welcome, Dr. <?php echo htmlspecialchars($user['name']); ?></p>
                <p style="font-size: 13px; color: #888;">Select the type of document you wish to generate for your patient.</p>
            </div>
        </div>

        <div class="selection-grid">
            <a href="create_mc.php" class="selection-card">
                <i class="fa-solid fa-file-medical"></i>
                <h2>Medical Certificate</h2>
                <p>Issue an official MC for patients requiring medical leave.</p>
            </a>

            <a href="create_timeslip.php" class="selection-card">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <h2>Time Slip</h2>
                <p>Issue a time slip for patients attending short clinic consultations.</p>
            </a>
        </div>
    </div>
</div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const mainWrapper = document.getElementById('mainWrapper');
        sidebar.classList.toggle('closed');
        mainWrapper.classList.toggle('full-width');
    }
</script>

</body>
</html>