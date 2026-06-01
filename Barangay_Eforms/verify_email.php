<?php
session_start();

$username = $_GET['username'] ?? $_POST['username'] ?? '';
$message = '';
$error = '';

function post_json($url, $payload) {
    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n",
            'content' => json_encode($payload),
            'ignore_errors' => true,
        ],
    ]);
    $resp = @file_get_contents($url, false, $context);
    return $resp === false ? null : json_decode($resp, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$username) {
        $error = "Missing username.";
    } else {
        $action = $_POST['action'] ?? 'verify';

        if ($action === 'resend') {
            $res = post_json("http://127.0.0.1:8000/api/resend_otp/", ['username' => $username]);
            if ($res === null) {
                $error = "Could not reach the server.";
            } elseif (isset($res['error'])) {
                $error = $res['error'];
            } else {
                $message = $res['message'] ?? "A new code has been sent to your email.";
            }
        } else {
            $otp = trim($_POST['otp'] ?? '');
            $res = post_json("http://127.0.0.1:8000/api/verify_email/", ['username' => $username, 'otp' => $otp]);
            if ($res === null) {
                $error = "Could not reach the server.";
            } elseif (isset($res['error'])) {
                $error = $res['error'];
            } else {
                header("Location: login.php?verified=1");
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f4f6f9; margin:0; }
        .card {
            max-width: 420px; margin: 80px auto; background:#fff; padding:36px;
            border-radius:12px; box-shadow:0 6px 20px rgba(0,0,0,0.08); text-align:center;
        }
        h1 { color:#0d3b66; margin:0 0 8px; }
        p { color:#444; line-height:1.5; }
        .otp-input {
            width: 100%; padding:14px; font-size:22px; text-align:center; letter-spacing:8px;
            border:1px solid #ccc; border-radius:8px; margin:14px 0; box-sizing:border-box;
        }
        .btn {
            width:100%; padding:12px; background:#0d3b66; color:#fff; border:none;
            border-radius:8px; font-size:16px; cursor:pointer; margin-top:6px;
        }
        .btn:hover { background:#09294a; }
        .link-btn {
            background:none; border:none; color:#0d3b66; cursor:pointer;
            text-decoration:underline; font-size:14px; margin-top:14px;
        }
        .error-message { color:#c0392b; font-weight:bold; }
        .success-message { color:#1e7e34; font-weight:bold; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Verify Your Email</h1>
        <p>We sent a 6-digit code to your email address. Enter it below to activate your account.</p>

        <?php if ($error): ?><p class="error-message"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <?php if ($message): ?><p class="success-message"><?= htmlspecialchars($message) ?></p><?php endif; ?>

        <form method="POST">
            <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">
            <input type="hidden" name="action" value="verify">
            <input class="otp-input" type="text" name="otp" inputmode="numeric" maxlength="6"
                   pattern="[0-9]{6}" placeholder="------" required autofocus>
            <button class="btn" type="submit">Verify</button>
        </form>

        <form method="POST">
            <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">
            <input type="hidden" name="action" value="resend">
            <button class="link-btn" type="submit">Didn't get a code? Resend</button>
        </form>
    </div>
</body>
</html>
