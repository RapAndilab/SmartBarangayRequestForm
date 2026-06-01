<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}
include 'header.php';

$username = $_SESSION['username'];

$db = new SQLite3('users.sqlite');
$user = $db->querySingle("SELECT * FROM users WHERE username = '$username'", true);
$fullName = $user['first_name'] . ' ' . $user['last_name'];
$address = $user['address'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Barangay Certification - Barangay E-Forms</title>
    <style>
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
        }
        .logo-row img {
            width: 80px;
            height: 80px;
            object-fit: contain;
        }
        .content-container {
            background: rgba(255, 255, 255, 0.97);
            margin-top: 30px;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            width: 95%;
            max-width: 800px;
        }
        .certificate {
            position: relative;
            width: 100%;
            background: url('certification.webp') no-repeat center center;
            background-size: cover;
            height: 700px;
            text-align: justify;
            padding: 340px 90px 60px;
            box-sizing: border-box;
        }
        .certificate-content {
            font-size: 16px;
            color: #000;
            line-height: 1.9;
        }
        .qr-section, .camera-section {
            margin-top: 25px;
            text-align: center;
        }
        .qr-section img {
            width: 200px;
            margin: 10px 0;
        }
        video, img.preview {
            width: 100%;
            max-width: 280px;
            margin: 10px auto;
            border-radius: 10px;
            display: block;
        }
        .btn {
            width: 100%;
            padding: 12px;
            background-color: #0d3b66;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            margin-top: 20px;
            cursor: pointer;
        }
        .btn:disabled {
            background-color: #999;
            cursor: not-allowed;
        }
        footer {
            margin-top: 40px;
            text-align: center;
            color: white;
            font-size: 12px;
            padding: 20px;
        }
    </style>
</head>
<body>
<div class="logo-row">
    <img src="logos/dapitanlogo.png" alt="City of Dapitan Logo">
    <img src="logos/jrmsulogo.png" alt="School Logo">
    <img src="logos/coelogo.png" alt="Department Logo">
</div>

<div class="content-container">
    <h1 style="text-align:center;">Barangay Certification Request</h1>

    <div class="certificate">
        <div class="certificate-content">
            <p>This is to certify that <strong><?= htmlspecialchars($fullName) ?></strong>, of legal age and a resident of <strong><?= htmlspecialchars($address) ?></strong>, is known to me as a person of good moral character and has no derogatory records before this office as of this date.</p>
            <p>This is to certify further that the above-name is currently working as <u>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</u> on the fishing vessel <u>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</u>, owned and operated by <u>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</u> for <u>_____</u> years.</p>
            <p>This certification is issued this <strong><?= date('jS') ?></strong> day of <strong><?= date('F') ?></strong>, <strong><?= date('Y') ?></strong> upon request of the undersigned for application of license as <u>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</u>.</p>
        </div>
    </div>

    <div class="qr-section">
        <h3>Scan to Pay</h3>
        <img src="logos/payment.png" alt="QR Code for Payment">
        <p><em>Please scan this QR code and complete the payment to continue.</em></p>
    </div>

    <div class="camera-section">
        <h3>Facial Verification</h3>
        <video id="video" autoplay playsinline></video>
        <img id="facePreview" class="preview" style="display:none;" alt="Face Preview">
        <input type="hidden" name="face_data" id="faceData">
        <button class="btn" type="button" onclick="captureFace()">Capture Face</button>
    </div>

    <form method="POST" action="submit_certification.php">
        <input type="hidden" name="face_data" id="finalFaceData">
        <button class="btn" id="submitBtn" type="submit" disabled>Submit Request</button>
    </form>
</div>

<footer>
    © 2025 Barangay E-Form System. By JRMSU Students Batch 2025
</footer>

<script>
    const video = document.getElementById('video');
    const facePreview = document.getElementById('facePreview');
    const faceData = document.getElementById('faceData');
    const finalFaceData = document.getElementById('finalFaceData');
    const submitBtn = document.getElementById('submitBtn');

    navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" } })
        .then(stream => { video.srcObject = stream; })
        .catch(() => alert("Unable to access camera."));

    function captureFace() {
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        const imageData = canvas.toDataURL('image/png');

        facePreview.src = imageData;
        facePreview.style.display = 'block';
        faceData.value = imageData;
        finalFaceData.value = imageData;

        submitBtn.disabled = false;
    }
</script>
</body>
</html>
