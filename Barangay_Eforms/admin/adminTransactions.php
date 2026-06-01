<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

include 'admin_header.php';

$db = new SQLite3('users.sqlite');

$results = $db->query("
    SELECT r.id, u.first_name, u.last_name, r.form_type, r.status, r.timestamp
    FROM requests r
    JOIN users u ON r.user_id = u.id
    ORDER BY r.timestamp DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transactions - Admin Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background: rgb(121, 115, 115);
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

        .page-title {
            margin-top: 30px;
            text-align: center;
            font-size: 32px;
            color: white;
        }

        .dashboard-container {
            background: white;
            margin: 30px auto;
            padding: 30px 20px;
            border-radius: 15px;
            max-width: 1200px;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
            min-height: 200px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
        }

        .transaction-box {
            background: #fff;
            width: 300px;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            justify-content: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .transaction-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
            background: #eaf3fb;
        }

        .transaction-box h4 {
            color: #0d3b66;
            margin: 0 0 8px;
        }

        .transaction-box p {
            margin: 5px 0;
            font-size: 15px;
            color: #333;
        }

        .status-paid {
            color: green;
            font-weight: bold;
        }

        .status-pending {
            color: orange;
            font-weight: bold;
        }

        .no-transactions {
            font-size: 20px;
            text-align: center;
            width: 100%;
            color: #555;
            padding: 50px 0;
        }

        @media (max-width: 768px) {
            .page-title {
                font-size: 24px;
            }

            .transaction-box {
                width: 90%;
            }
        }

        @media (max-width: 480px) {
            .page-title {
                font-size: 20px;
            }

            .logo-row img {
                width: 60px;
                height: 60px;
            }

            .dashboard-container {
                padding: 20px 10px;
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

<!-- Page Title -->
<h1 class="page-title">Transactions</h1>

<!-- Transaction Boxes -->
<div class="dashboard-container">
    <?php
    $hasTransactions = false;
    while ($row = $results->fetchArray(SQLITE3_ASSOC)):
        $hasTransactions = true;
    ?>
        <div class="transaction-box">
            <h4><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></h4>
            <p><strong>Form:</strong> <?= htmlspecialchars($row['form_type']) ?></p>
            <p><strong>Date:</strong> <?= date('F j, Y, g:i A', strtotime($row['timestamp'])) ?></p>
            <p><strong>Status:</strong>
                <span class="<?= $row['status'] === 'paid' ? 'status-paid' : 'status-pending' ?>">
                    <?= strtoupper($row['status']) ?>
                </span>
            </p>
        </div>
    <?php endwhile; ?>

    <?php if (!$hasTransactions): ?>
        <div class="no-transactions">📭 No transactions this time.</div>
    <?php endif; ?>
</div>

</body>
</html>
