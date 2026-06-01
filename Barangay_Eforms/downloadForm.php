<?php
session_start();
$username = $_SESSION['username'];  // Must be set during login
$imagePath = "face_data/face_1_" . $username . ".png";  // Assume image was saved already

// Call face verification script
$verify = file_get_contents("http://localhost/verify_face.php?username=$username&image=$imagePath");
$result = json_decode($verify, true);

// Allow download if matched
if ($result['status'] === 'matched') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="barangay_clearance.pdf"');
    readfile("generated_forms/$username/barangay_clearance.pdf");
    exit;
} else {
    echo "<script>alert('Face verification failed. Cannot download form.'); window.location.href='userDashboard.php';</script>";
    exit;
}
?>
