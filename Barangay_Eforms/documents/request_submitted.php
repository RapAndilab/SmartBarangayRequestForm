<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$request_id = $_GET['request_id'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Submitted</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background:#f4f6f9; margin:0; }
        .card {
            max-width: 520px; margin: 80px auto; background:#fff; padding:40px;
            border-radius:12px; box-shadow:0 6px 20px rgba(0,0,0,0.08); text-align:center;
        }
        .icon { font-size:60px; color:#f0a500; margin-bottom:10px; }
        h1 { color:#0d3b66; margin:10px 0; }
        p { color:#444; line-height:1.6; }
        .ref { font-weight:bold; color:#0d3b66; }
        .btn {
            display:inline-block; margin-top:20px; padding:12px 24px; background:#0d3b66;
            color:#fff; text-decoration:none; border-radius:8px;
        }
        .btn:hover { background:#09294a; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon"><i class="fa-solid fa-clock"></i></div>
        <h1>Request Submitted</h1>
        <p>
            Your document request has been received and is now
            <strong>pending admin approval</strong>.
        </p>
        <?php if ($request_id): ?>
            <p>Your reference number is <span class="ref">#<?= htmlspecialchars($request_id) ?></span>.</p>
        <?php endif; ?>
        <p>
            You will be notified by email once it is approved. After approval,
            you can download your document from your requests page.
        </p>
        <a class="btn" href="../home.php">Back to Home</a>
    </div>
</body>
</html>
