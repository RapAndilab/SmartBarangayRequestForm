<?php
session_start();
$db = new SQLite3('users.sqlite');

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);

    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND email = ?");
    $stmt->bindValue(1, $username, SQLITE3_TEXT);
    $stmt->bindValue(2, $email, SQLITE3_TEXT);
    $user = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    if ($user) {
        // Simulate verification email
        $verificationCode = rand(100000, 999999);
        $_SESSION['reset_code'] = $verificationCode;
        $_SESSION['reset_username'] = $username;

        // In real implementation, send this code via email
        $message = "✅ A verification code has been sent to your email: <strong>$email</strong>.<br>Your code: <strong>$verificationCode</strong> (for testing)";
    } else {
        $message = "❌ Username or email not found.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            background: url('logos/background.jpg') no-repeat center center fixed;
            background-size: cover;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }
        .logo-row {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 30px;
        }
        .logo-row img {
            width: 80px;
            height: 80px;
            object-fit: contain;
        }
        .form-box {
            background: rgba(255, 255, 255, 0.45);
            backdrop-filter: blur(10px);
            margin-top: 30px;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 400px;
            text-align: center;
        }
        h2 {
            color: #0d3b66;
            margin-bottom: 20px;
        }
        input {
            width: 90%;
            padding: 12px;
            margin: 10px 0;
            border: none;
            border-radius: 8px;
            font-size: 16px;
        }
        button {
            width: 90%;
            padding: 12px;
            background-color: #0d3b66;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
        }
        button:hover {
            background-color: #2a5298;
        }
        .msg {
            margin-top: 15px;
            font-weight: bold;
            color: #333;
        }
        a {
            display: inline-block;
            margin-top: 20px;
            text-decoration: none;
            color: #0d3b66;
        }
    </style>
</head>
<body>

<!-- Logos -->
<div class="logo-row">
    <img src="logos/dapitanlogo.png" alt="City of Dapitan Logo">
    <img src="logos/jrmsulogo.png" alt="School Logo">
    <img src="logos/coelogo.png" alt="Department Logo">
</div>

<!-- Forgot Password Form -->
<div class="form-box">
    <h2>Forgot Password</h2>
    <form method="POST">
        <input type="text" name="username" placeholder="Enter your username" required><br>
        <input type="email" name="email" placeholder="Enter your registered email" required><br>
        <button type="submit">Send Verification</button>
    </form>
    <div class="msg"><?= $message ?></div>
    <a href="login.php">← Back to Home</a>
</div>

</body>
</html>
