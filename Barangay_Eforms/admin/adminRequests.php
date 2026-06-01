<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}
include 'admin_header.php';

$db = new SQLite3('users.sqlite');

// Approve logic
if (isset($_GET['approve'])) {
    $id = intval($_GET['approve']);
    $db->exec("UPDATE requests SET status = 'approved' WHERE id = $id");
    header("Location: adminRequests.php");
    exit();
}

// Fetch all requests
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
    <title>Admin Requests - Barangay E-Forms</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background: rgb(121, 115, 115);
        }

        .container {
            padding: 40px 20px;
            max-width: 1200px;
            margin: auto;
        }

        h2 {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        table {
            width: 100%;
            min-width: 700px;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px;
            border-bottom: 1px solid #ccc;
            text-align: center;
        }

        th {
            background-color: #0d3b66;
            color: white;
        }

        .btn-approve {
            padding: 6px 12px;
            background-color: green;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        .btn-approve:hover {
            background-color: darkgreen;
        }

        .status {
            font-weight: bold;
        }

        .status.pending {
            color: orange;
        }

        .status.approved {
            color: green;
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px 10px;
            }

            h2 {
                font-size: 20px;
            }

            th, td {
                font-size: 14px;
                padding: 10px;
            }

            .btn-approve {
                font-size: 14px;
                padding: 5px 10px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Form Requests</h2>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Form Type</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $results->fetchArray(SQLITE3_ASSOC)): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                        <td><?= htmlspecialchars($row['form_type']) ?></td>
                        <td><?= date('F j, Y, g:i A', strtotime($row['timestamp'])) ?></td>
                        <td class="status <?= $row['status'] ?>"><?= strtoupper($row['status']) ?></td>
                        <td>
                            <?php if ($row['status'] === 'pending'): ?>
                                <a href="?approve=<?= $row['id'] ?>" class="btn-approve">Approve</a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
