<?php
session_start();
$username = $_SESSION['username'] ?? 'guest';

// Simulated payment flag (replace with real confirmation logic later)
$paymentConfirmed = true;

// Path to the generated PDF (this must be created earlier in submit_residency.php)
$pdfPath = "forms/residency_request_" . $username . ".pdf";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>GCash Payment - Barangay E-Forms</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 40px;
            text-align: center;
        }
        .container {
            background: white;
            max-width: 600px;
            margin: auto;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        h2 {
            color: #0d3b66;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #0d3b66;
            color: white;
            border-radius: 8px;
            font-size: 16px;
            text-decoration: none;
            margin-top: 20px;
        }
        .btn:hover {
            background-color: #06294d;
        }
        img.qr {
            width: 250px;
            border-radius: 8px;
            margin-top: 20px;
        }
        p {
            font-size: 15px;
        }
    </style>
</head>
<body>
<div class="container">
    <?php if ($paymentConfirmed && file_exists($pdfPath)) : ?>
        <h2>✅ Payment Confirmed!</h2>
        <p>Your Barangay Residency form is ready for download.</p>
        <a href="<?= $pdfPath ?>" class="btn" download>Download Form</a>
    <?php else: ?>
        <h2>📌 Please Complete Payment</h2>
        <p>Scan the QR code below to pay ₱20 using GCash.</p>
        <img src="images/gcash_qr.png" alt="GCash QR Code" class="qr">
        <p>Once your payment is confirmed, this page will unlock the download link.</p>
        <p><i>Tip: Press <b>F5</b> after paying to refresh.</i></p>
    <?php endif; ?>
</div>
</body>
</html>
