<?php
    session_start();
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header('Location: login.php');
        exit();
    }
?>
<?php include 'admin_header.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Barangay E-Forms</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Font Awesome CDN for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background: rgb(121, 115, 115);
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }

        .logo-row {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 30px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .logo-row img {
            width: 80px;
            height: 80px;
            object-fit: contain;
        }

        .dashboard-container {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 30px;
            padding: 40px 20px;
            width: 100%;
            max-width: 1200px;
        }

        .admin-box {
            background: white;
            width: 250px;
            height: 180px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            font-size: 18px;
            font-weight: bold;
            text-align: center;
            color: #0d3b66;
            text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .admin-box i {
            font-size: 40px;
            margin-bottom: 15px;
            color: #0d3b66;
        }

        .admin-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.15);
            background: #eaf3fb;
        }

        /* Tablet */
        @media (max-width: 768px) {
            .logo-row img {
                width: 60px;
                height: 60px;
            }

            .dashboard-container {
                padding: 20px;
                gap: 20px;
            }

            .admin-box {
                width: 100%;
                max-width: 300px;
                height: 160px;
                font-size: 16px;
            }

            .admin-box i {
                font-size: 30px;
            }
        }

        /* Phone */
        @media (max-width: 480px) {
            .logo-row img {
                width: 50px;
                height: 50px;
            }

            .dashboard-container {
                padding: 15px;
                gap: 15px;
            }

            .admin-box {
                height: 140px;
                font-size: 15px;
            }

            .admin-box i {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>

<!-- Logo Row -->
<div class="logo-row">
    <img src="dapitanlogo.png" alt="City of Dapitan Logo">
    <img src="jrmsulogo.png" alt="School Logo">
    <img src="coelogo.png" alt="Department Logo">
</div>

<!-- Dashboard Boxes -->
<div class="dashboard-container">
    <a href="adminTransactions.php" class="admin-box">
        <i class="fas fa-file-invoice-dollar"></i>
        Transactions
    </a>
    <a href="adminRequests.php" class="admin-box">
        <i class="fas fa-envelope-open-text"></i>
        Requests
    </a>
    <a href="adminUsers.php" class="admin-box">
        <i class="fas fa-users"></i>
        Registered Users
    </a>
</div>

</body>
</html>
