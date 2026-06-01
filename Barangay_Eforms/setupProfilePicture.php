<?php
session_start();
$db = new SQLite3('users.sqlite');
$user = $db->querySingle("SELECT * FROM users ORDER BY id DESC LIMIT 1", true);

$upload_error = '';
$upload_success = '';
$redirect_script = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['face_data'])) {
    $data = $_POST['face_data'];

    if (strpos($data, 'data:image/png;base64,') === 0) {
        $data = str_replace('data:image/png;base64,', '', $data);
        $data = str_replace(' ', '+', $data);
        $imageData = base64_decode($data);

        if ($imageData) {
            $filename = 'profile_' . $user['id'] . '.png';
            $target = 'profile_pics/' . $filename;

            if (file_put_contents($target, $imageData)) {
                $db->exec("UPDATE users SET profile_pic = '$filename' WHERE id = {$user['id']}");
                $upload_success = "Profile picture saved!";
                $redirect_script = "<script>
                    setTimeout(function() {
                        window.location.href = 'home.php';
                    }, 2000);
                </script>";
            } else {
                $upload_error = "Failed to save image.";
            }
        } else {
            $upload_error = "Invalid image data.";
        }
    } else {
        $upload_error = "Invalid image format.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Setup Profile Picture</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
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
            gap: 30px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .logo-row img {
            width: 80px;
            height: 80px;
            object-fit: contain;
        }
        .profile-box {
            background: #fff;
            padding: 30px;
            width: 95%;
            max-width: 480px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            text-align: center;
            margin-top: 30px;
        }
        .profile-box h2 {
            margin-bottom: 10px;
            color: #0d3b66;
        }
        .profile-box p {
            color: #555;
        }
        .btn {
            display: inline-block;
            background: #0d3b66;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
            width: 100%;
        }
        .btn:hover {
            background: #2a5298;
        }
        video {
            transform: scaleX(-1);
            margin: 10px auto;
            width: 100%;
            max-width: 300px;
            border-radius: 12px;
            box-shadow: 0 0 10px rgba(0,0,0,0.15);
            display: block;
        }
        img.preview {
            margin: 10px auto;
            width: 100%;
            max-width: 300px;
            border-radius: 12px;
            box-shadow: 0 0 10px rgba(0,0,0,0.15);
            display: block;
        }
        .success {
            color: green;
            font-weight: bold;
        }
        .error {
            color: red;
            font-weight: bold;
        }
        @media (max-width: 480px) {
            .logo-row img {
                width: 60px;
                height: 60px;
            }
        }
    </style>
</head>
<body>

<div class="logo-row">
    <img src="logos/dapitanlogo.png" alt="City of Dapitan Logo">
    <img src="logos/jrmsulogo.png" alt="School Logo">
    <img src="logos/coelogo.png" alt="Department Logo">
</div>

<div class="profile-box">
    <h2>Hello, <?= htmlspecialchars($user['first_name']) ?>!</h2>
    <p>Capture your profile picture below</p>

    <?php if ($upload_success): ?>
        <p class="success"><?= $upload_success ?></p>
    <?php elseif ($upload_error): ?>
        <p class="error"><?= $upload_error ?></p>
    <?php endif; ?>

    <?= $redirect_script ?>

    <form method="POST">
        <input type="hidden" name="face_data" id="finalFaceData">

        <video id="video" autoplay playsinline></video>
        <img id="facePreview" class="preview" style="display:none;" alt="Captured Preview">
        <button class="btn" type="button" onclick="captureFace()">Capture Face</button>
        <button class="btn" id="submitBtn" type="submit" disabled>Save Profile Picture</button>
    </form>
</div>

<script>
    const video = document.getElementById('video');
    const facePreview = document.getElementById('facePreview');
    const finalFaceData = document.getElementById('finalFaceData');
    const submitBtn = document.getElementById('submitBtn');

    let streamRef = null;
    let captured = false;

    function startCamera() {
        navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" } })
            .then(stream => {
                video.srcObject = stream;
                streamRef = stream;
                video.style.display = 'block';
            })
            .catch(() => alert("Camera access denied or unavailable."));
    }

    function stopCamera() {
        if (streamRef) {
            streamRef.getTracks().forEach(track => track.stop());
            streamRef = null;
        }
        video.style.display = 'none';
    }

    function captureFace() {
        if (captured) {
            if (!confirm("You already captured your face. Capture again?")) return;

            facePreview.style.display = 'none';
            finalFaceData.value = '';
            submitBtn.disabled = true;
            captured = false;
            startCamera();
            return;
        }

        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');

        // Flip the canvas to correct the mirrored live preview
        ctx.save();
        ctx.scale(-1, 1);
        ctx.drawImage(video, -canvas.width, 0, canvas.width, canvas.height);
        ctx.restore();

        const imageData = canvas.toDataURL('image/png');
        facePreview.src = imageData;
        facePreview.style.display = 'block';
        finalFaceData.value = imageData;

        captured = true;
        stopCamera();
        submitBtn.disabled = false;
    }

    startCamera();
</script>

</body>
</html>
