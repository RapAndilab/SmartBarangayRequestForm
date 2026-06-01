<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $options = [
        "http" => [
            "header"  => "Content-type: application/x-www-form-urlencoded\r\n",
            "method"  => "POST",
            "content" => http_build_query([
                "username" => $username,
                "password" => $password
            ])
        ]
    ];
    
    $context  = stream_context_create($options);
    $response = file_get_contents("http://127.0.0.1:8000/api/login/", false, $context);
    $data = json_decode($response, true);
    
    if (isset($data['error'])) {
        // Unverified accounts get sent to the email verification page.
        if (!empty($data['verification_required'])) {
            header("Location: verify_email.php?username=" . urlencode($data['username'] ?? $username));
            exit();
        }
        $error = $data['error'];
    } else {
        $_SESSION['user_id'] = $data['user_id'];
        $_SESSION['username'] = $data['username'];
        $_SESSION['address'] = $data['address'];
        $_SESSION['birthdate'] = $data['birthdate'];
        $_SESSION['first_name'] = $data['first_name'];
        $_SESSION['last_name'] = $data['last_name'];
        $_SESSION['role'] = $data['role'] ?? 'user';

        if ($_SESSION['role'] === 'admin') {
            header("Location: admin/admin_dashboard.php");
        } else {
            header("Location: home.php");
        }
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Barangay E-Form Request - Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            /* background: url('../logos/background.jpg') no-repeat center center fixed; */
            background-color: white;
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
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .logo-row img {
            width: 80px;
            height: 80px;
            object-fit: contain;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.45);
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border-radius: 20px;
            padding: 40px;
            width: 95%;
            max-width: 400px;
            text-align: center;
            color: #333;
            margin-top: 30px;
        }

        .login-container h1 {
            font-size: 32px;
            background: #0d3b66;
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .login-container h2 {
            margin-bottom: 30px;
            color: #333;
            font-size: 24px;
        }

        .login-container input[type="text"],
        .login-container input[type="password"] {
            width: 90%;
            padding: 12px;
            margin: 10px 0;
            border: none;
            border-radius: 8px;
            outline: none;
            font-size: 16px;
        }

        .login-container button {
            width: 90%;
            padding: 12px;
            margin-top: 20px;
            background-color: #0d3b66;
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 18px;
            cursor: pointer;
        }

        .login-container button:hover {
            background-color: #2a5298;
        }

        .login-container p {
            margin-top: 15px;
            font-size: 16px;
        }

        .error-message {
            color: red;
            margin-bottom: 10px;
            font-weight: bold;
        }

        .forgot-link {
            text-align: right;
            width: 90%;
            margin: 5px auto 10px;
        }

        .forgot-link a {
            font-size: 14px;
            text-decoration: none;
            color: #0d3b66;
        }

        .forgot-link a:hover {
            text-decoration: underline;
        }

        footer {
            margin-top: 20px;
            text-align: center;
            color: white;
            font-size: 12px;
        }

        a {
            color: #0d3b66;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        /* Mobile Responsiveness */
        @media (max-width: 480px) {
            .login-container {
                padding: 20px;
            }

            .login-container h1 {
                font-size: 24px;
                padding: 15px;
            }

            .login-container h2 {
                font-size: 20px;
            }

            .login-container button {
                font-size: 16px;
            }

            .logo-row img {
                width: 60px;
                height: 60px;
            }
        }
    </style>
</head>
<body>

    <!-- LOGO ROW -->
    <div class="logo-row">
        <img src="assets/dapitanlogo.png" alt="City of Dapitan Logo">
        <img src="assets/jrmsulogo.png" alt="School Logo">
        <img src="assets/coelogo.png" alt="Department Logo">
    </div>

    <!-- LOGIN FORM -->
    <div class="login-container">
        <h1>Barangay E-Form Request</h1>
        <h2>Login</h2>

        <?php if (isset($_GET['verified'])): ?>
            <p style="color:#1e7e34; font-weight:bold;">Email verified! You can now log in.</p>
        <?php endif; ?>

        <?php if ($error): ?>
            <p class="error-message"><?= $error ?></p>
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="username" required placeholder="Username"><br>
            <input type="password" name="password" required placeholder="Password"><br>

            <div class="forgot-link">
                <a href="forgot_password.php">Forgot Password?</a>
            </div>

            <button type="submit">Log in</button>
        </form>

        <p>Don't have an account? <a href="register.php">Click to Register.</a></p>
    </div>

    <footer>
        © 2025 Barangay E-Form System. By JRMSU Students Batch 2025
    </footer>

</body>
</html>
