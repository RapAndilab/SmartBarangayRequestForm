<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
$error = '';
$success = '';
$show_camera = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $boundary = uniqid();
    $delimiter = '-------------' . $boundary;

    $fields = [
        'first_name' => $_POST['first_name'],
        'last_name' => $_POST['last_name'],
        'birthdate' => $_POST['birthdate'],
        'gender' => $_POST['gender'],
        'address' => $_POST['address'],
        'phone' => $_POST['phone'],
        'email' => $_POST['email'],
        'username' => $_POST['username'],
        'password' => $_POST['password']
    ];

    if (!isset($_POST['confirm_password']) || $_POST['password'] !== $_POST['confirm_password']) {
        $error = "Passwords do not match.";
    }
    // Validate username has no whitespace
    else if (!preg_match('/^[A-Za-z0-9_]+$/', $_POST['username'])) {
        $error = "Username can only contain letters, numbers, and underscores.";
    }
    else if (!isset($_FILES['profile_image'])) {
        $error = "Your face is needed to proceed, take a picture";
    }
    else {
        $data = '';
        foreach ($fields as $name => $value) {
            $data .= "--$delimiter\r\n";
            $data .= "Content-Disposition: form-data; name=\"$name\"\r\n\r\n$value\r\n";
        }

        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $image_content = file_get_contents($_FILES['profile_image']['tmp_name']);
            $data .= "--$delimiter\r\n";
            $data .= "Content-Disposition: form-data; name=\"image\"; filename=\"" . basename($_FILES['profile_image']['name']) . "\"\r\n";
            $data .= "Content-Type: image/jpeg\r\n\r\n" . $image_content . "\r\n";
        }

        $data .= "--$delimiter--\r\n";

        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: multipart/form-data; boundary=$delimiter\r\n",
                'content' => $data
            ]
        ]);

        $response = file_get_contents("http://127.0.0.1:8000/api/register/", false, $context);

        if ($response === false) {
            $error = "Failed to connect to server.";
        } else {
            $result = json_decode($response, true);
            if (isset($result['id'])) {
                $success = "Account created successfully!";
            } elseif (isset($result['error'])) {
                $error = $result['error'];
            } else {
                $error = "Registration failed. Please check your input. Possible Username already exist";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Barangay E-Form Request - Register</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
        }
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background: url('logos/background.jpg') no-repeat center center fixed;
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
            gap: 20px;
            margin-top: 10px;
            flex-wrap: wrap;
        }
        .logo-row img {
            width: 70px;
            height: 70px;
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
            max-width: 800px;
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
            margin-bottom: 20px;
            color: #333;
        }
        .form-grid {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .form-col {
            flex: 1 1 45%;
            display: flex;
            flex-direction: column;
        }
        .form-col input,
        .form-col select {
            width: 100%;
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
        }
        .success-message {
            color: green;
            margin-bottom: 10px;
            font-weight: bold;
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

        @media (max-width: 768px) {
            .form-col {
                flex: 1 1 100%;
            }
            .login-container h1 {
                font-size: 28px;
            }
        }

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
                width: 100%;
                font-size: 16px;
            }
        }
    </style>
</head>
<body>

<div class="logo-row">
    <img src="assets/dapitanlogo.png" alt="City of Dapitan Logo">
    <img src="assets/jrmsulogo.png" alt="School Logo">
    <img src="assets/coelogo.png" alt="Department Logo">
</div>

<div class="login-container">
    <h1>Barangay E-Form Request</h1>
    <h2>Register</h2>
    <?php if ($error): ?><p class="error-message"><?= $error ?></p><?php endif; ?>
    <?php if ($success): ?><p class="success-message"><?= $success ?></p><?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="form-grid">
            <div class="form-col">
                <input type="text" name="first_name" required placeholder="First Name">
                <input type="date" name="birthdate" required>
                <input type="text" name="address" required placeholder="Address">
                <input type="email" name="email" required placeholder="Email">
                <input type="password" name="password" required placeholder="Password">
            </div>
            <div class="form-col">
                <input type="text" name="last_name" required placeholder="Last Name">
                <select name="gender" required>
                    <option value="">Gender</option>
                    <option value="Female">Female</option>
                    <option value="Male">Male</option>
                </select>
                <input type="text" name="phone" required placeholder="Phone Number">
                <input type="text" name="username" required placeholder="Username">
                <input type="password" name="confirm_password" required placeholder="Confirm Password">
            </div>
        </div>
        
        <!-- Profile Picture Upload -->
        <div style="margin-top: 10px;">
            <label>Profile Picture:</label>
            <br />
            <video id="video" width="320" height="240" autoplay></video>
            <canvas id="canvas" width="320" height="240" style="display:none;"></canvas>
            <br />
            <button type="button" id="snap">Take Picture</button>
            <br />
            <img id="photo" alt="Captured Image" style="margin-top:10px; max-width:320px;" />
            <input type="file" name="profile_image" id="profile_image" style="display:none;" />
        </div>
        
        <button type="submit">Register</button>
    </form>
    <p>Already have an account? <a href="login.php">Login here</a></p>
</div>

<footer>
    © 2025 Barangay E-Form System. By JRMSU Students Batch 2025
</footer>

<script>
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const snapBtn = document.getElementById('snap');
    const photo = document.getElementById('photo');

    // Get access to the webcam
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
    navigator.mediaDevices.getUserMedia({ video: true }).then(stream => {
        video.srcObject = stream;
        video.play();
    });
    }

    function dataURLtoFile(dataurl, filename) {
        let arr = dataurl.split(','), mime = arr[0].match(/:(.*?);/)[1],
            bstr = atob(arr[1]), n = bstr.length, u8arr = new Uint8Array(n);
        while(n--) u8arr[n] = bstr.charCodeAt(n);
        return new File([u8arr], filename, {type:mime});
    }

    // Trigger photo take
    snapBtn.addEventListener('click', () => {
        const context = canvas.getContext('2d');
        context.drawImage(video, 0, 0, canvas.width, canvas.height);

        // Convert canvas to base64 image
        const dataURL = canvas.toDataURL('image/png');
        photo.src = dataURL;

        // Convert base64 to File object
        const file = dataURLtoFile(dataURL, 'profile.jpg');

        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);

        // Assign file to hidden file input
        const fileInput = document.getElementById('profile_image');
        fileInput.files = dataTransfer.files;
    });
</script>

</body>
</html>
