<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}
include 'header.php';

function getUserDocumentRequests($user_id) {
    $data = ["user_id" => $user_id];
    $json_data = json_encode($data);

    $api_url = "http://127.0.0.1:8000/api/manage_request/";
    $options = [
        "http" => [
            "header" => "Content-Type: application/json\r\n",
            "method" => "POST",
            "content" => $json_data
        ],
    ];
    $context = stream_context_create($options);
    $response = file_get_contents($api_url, false, $context);
    if ($response === false) return null;
    return json_decode($response, true);
}

$user_id = $_SESSION['user_id'];
$requests = getUserDocumentRequests($user_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Home - Barangay E-Forms</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', sans-serif;
            background: url('assets/background.jpg') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
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

        .main-container {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 30px;
            padding: 40px 20px;
        }

        .option-box {
            background: rgba(255, 255, 255, 0.9);
            width: 280px;
            height: 180px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 24px;
            font-weight: bold;
            text-align: center;
            color: #0d3b66;
            text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .option-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.15);
            background: rgba(234, 243, 251, 0.57);
        }

        footer {
            margin-top: auto;
            text-align: center;
            color: white;
            font-size: 12px;
            padding: 20px;
        }

        @media (max-width: 768px) {
            .option-box {
                width: 90%;
                height: 150px;
                font-size: 20px;
            }
            .logo-row img {
                width: 60px;
                height: 60px;
            }
        }

        @media (max-width: 480px) {
            .option-box {
                height: 120px;
                font-size: 18px;
            }
            .main-container {
                gap: 20px;
                padding: 30px 10px;
            }
        }
    </style>
</head>
<body>

<!-- Logo Row -->
<div class="logo-row">
    <img src="assets/dapitanlogo.png" alt="City of Dapitan Logo">
    <img src="assets/jrmsulogo.png" alt="School Logo">
    <img src="assets/coelogo.png" alt="Department Logo">
</div>

<!-- Main Section -->
<div class="main-container">
    <a href="documents/barangayClearance.php" class="option-box">Barangay Clearance</a>
    <a href="documents/barangayResidency.php" class="option-box">Barangay Residency</a>
    <a href="documents/barangayCertification.php" class="option-box">Barangay Certification</a>
</div>

<!-- Requests Section -->
<div class="main-container">
    <h2>Your Document Requests</h2>
    <table border="1" cellpadding="6" style="border-collapse: collapse; width: 100%;">
        <thead>
            <tr style="background: #f5f5f5;">
                <th>Request ID</th>
                <th>Document Type</th>
                <th>Status</th>
                <th>Download</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($requests)): ?>
                <?php foreach ($requests as $req): ?>
                    <tr>
                        <td><?= htmlspecialchars($req['id']) ?></td>
                        <td><?= htmlspecialchars($req['document_type']) ?></td>
                        <td>
                            <?php if ($req['confirmed']): ?>
                                <span style="color:green;">Approved ✅</span>
                            <?php else: ?>
                                <span style="color:orange;">Pending Approval ⏳</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($req['confirmed'] && !empty($req['download_link'])): ?>
                                <a href="http://127.0.0.1:8000/api/download/<?= $req['id'] ?>/" download>📥 Download</a>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4">No document requests found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Footer -->
<footer>
    © 2025 Barangay E-Form System. By JRMSU Students Batch 2025
</footer>

</body>
</html>
