<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

include 'admin_header.php';

$dbPath = "C:/Users/Vincent Laluna/Desktop/Barangay_Eforms/users.sqlite";
$db = new SQLite3($dbPath);

// Check if `created_at` column exists
$hasCreatedAt = false;
$result = $db->query("PRAGMA table_info(users)");
while ($col = $result->fetchArray(SQLITE3_ASSOC)) {
    if ($col['name'] === 'created_at') {
        $hasCreatedAt = true;
        break;
    }
}

$orderBy = $hasCreatedAt ? "ORDER BY datetime(created_at) DESC" : "ORDER BY id DESC";
$users = $db->query("SELECT * FROM users $orderBy");
$totalUsers = $db->querySingle("SELECT COUNT(*) FROM users");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registered Users - Admin Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background: #f4f4f4;
        }

        .logo-row {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 20px;
        }

        .logo-row img {
            width: 80px;
            height: 80px;
            object-fit: contain;
        }

        .dashboard-container {
            padding: 40px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .user-count {
            color: white;
            font-size: 22px;
            margin-bottom: 20px;
            background-color: #0d3b66;
            padding: 10px 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
            max-width: 1200px;
            border-radius: 8px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            background: white;
        }

        table {
            width: 100%;
            min-width: 800px;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #0d3b66;
            color: white;
        }

        tr:hover {
            background-color: #f1f1f1;
        }

        .no-users {
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            font-size: 18px;
            margin-top: 40px;
            text-align: center;
        }

        @media (max-width: 768px) {
            .user-count {
                font-size: 18px;
                padding: 8px 16px;
            }

            th, td {
                font-size: 14px;
                padding: 10px;
            }
        }
    </style>
</head>
<body>

<!-- Logo Row -->
<div class="logo-row">
    <img src="../logos/dapitanlogo.png" alt="City of Dapitan Logo">
    <img src="../logos/jrmsulogo.png" alt="School Logo">
    <img src="../logos/coelogo.png" alt="Department Logo">
</div>

<!-- Dashboard Content -->
<div class="dashboard-container">
    <div class="user-count">
        👥 Total Registered Users: <?= $totalUsers ?>
    </div>

    <?php if ($totalUsers > 0): ?>
        <div class="table-responsive">
            <table>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Birthdate</th>
                    <th>Gender</th>
                    <th>Address</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Date Registered</th>
                </tr>
                <?php while ($row = $users->fetchArray(SQLITE3_ASSOC)): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                        <td><?= $row['birthdate'] ?></td>
                        <td><?= $row['gender'] ?></td>
                        <td><?= $row['address'] ?></td>
                        <td><?= $row['phone'] ?></td>
                        <td><?= $row['email'] ?></td>
                        <td><?= htmlspecialchars($row['username']) ?></td>
                        <td><?= htmlspecialchars($row['role']) ?></td>
                        <td><?= $hasCreatedAt ? htmlspecialchars($row['created_at'] ?? '-') : '-' ?></td>
                    </tr>
                <?php endwhile; ?>
            </table>
        </div>
    <?php else: ?>
        <div class="no-users">📭 No users found.</div>
    <?php endif; ?>
</div>

</body>
</html>
