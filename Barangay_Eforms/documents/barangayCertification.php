<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit();
}
include '../header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Barangay Certification - Barangay E-Forms</title>
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

        .content-container {
            background: rgba(255, 255, 255, 0.95);
            margin-top: 30px;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            width: 90%;
            max-width: 800px;
        }

        h1 {
            color: #0d3b66;
            margin-bottom: 20px;
            text-align: center;
        }

        form { margin-top: 20px; }

        label {
            display: block;
            margin-top: 10px;
            font-weight: bold;
        }

        input[type="text"], textarea {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 16px;
        }
        input[type="password"] {
            width: 100%;
            padding: 5px;
            margin-top: 5px;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 16px;
        }

        .camera-section {
            margin-top: 25px;
            text-align: center;
        }

        video {
            transform: scaleX(-1); /* Flip live preview only */
            width: 100%;
            max-width: 280px;
            margin: 10px auto;
            border-radius: 10px;
            display: block;
        }

        img.preview {
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

        @media (max-width: 768px) {
            .logo-row img { width: 60px; height: 60px; }
            .content-container { padding: 20px; margin-top: 20px; }
            h1 { font-size: 22px; }
            input[type="text"], textarea { font-size: 14px; padding: 8px; }
            .btn { font-size: 14px; padding: 10px; }
        }

        @media (max-width: 480px) {
            .logo-row { gap: 15px; justify-content: center; }
            .logo-row img { width: 50px; height: 50px; }
            .content-container { padding: 15px; }
            footer { font-size: 10px; }
        }
    </style>
</head>
<body>

<div class="logo-row">
    <img src="../assets/dapitanlogo.png" alt="City of Dapitan Logo">
    <img src="../assets/jrmsulogo.png" alt="School Logo">
    <img src="../assets/coelogo.png" alt="Department Logo">
</div>

<div class="content-container">
    <h1>Barangay Certification Request</h1>

    <form id="docForm" method="POST">
        <input type="hidden" name="document_type" id="document_type" value="certificate">
        <input type="hidden" name="user_id" id="user_id" value=<?= $_SESSION["user_id"] ?>>
        <input type="hidden" name="username" id="username" value=<?= $_SESSION["username"] ?>>

        <label>Full Name:</label>
        <input type="text" name="full_name" value="<?= $_SESSION["first_name"] . ' ' . $_SESSION["last_name"] ?>" required>

        <label>Address:</label>
        <input type="text" name="address" value="<?= $_SESSION['address'] ?>"  placeholder="Enter your full address" required>

        <label>Purpose:</label>
        <textarea name="purpose" placeholder="e.g. Job Application, Travel, Government Requirement" required></textarea>

        
       
        

        <button class="btn" id="submitBtn" type="submit">Submit Request</button>
    </form>
</div>

<footer>
    © 2025 Barangay E-Form System. By JRMSU Students Batch 2025
</footer>
<!-- PASSWORD MODAL -->
<div id="passwordModal" style="
    display:none; 
    position:fixed; 
    top:0; left:0; width:100%; height:100%;
    background:rgba(0,0,0,0.5); 
    justify-content:center; 
    align-items:center;
">
  <div style="background:white; padding:20px; border-radius:8px; width:300px;">
    <h3>Enter Password</h3>

    <div style="position:relative; width:100%; margin-top:10px;">
      <input id="modalPassword" type="password" placeholder="Password"
             style="width:100%; padding:8px 40px 8px 8px;">

      <!-- PADLOCK TOGGLE ICON -->
      <span id="togglePassword" 
            style="
              position:absolute; 
              right:10px; 
              top:50%; 
              transform:translateY(-50%);
              cursor:pointer;
              font-size:20px;
            ">
        🔒
      </span>
    </div>

    <button id="modalSubmit" style="margin-top:15px; width:100%;">Submit</button>
  </div>
</div>


<!-- This is where process document request is sent -->
<script src="../utils/document_submit.js"></script>

</body>
</html>
