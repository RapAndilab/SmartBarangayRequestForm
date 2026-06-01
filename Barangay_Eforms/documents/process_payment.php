<?php
session_start();

$user_id = $_SESSION["user_id"];
$request_id = $_GET['request_id'] ?? '';

$data = [
    "user_id" => $user_id,
    "request_id" => $request_id,
];

$request = getDocumentRequest($data);
if (isset($request['error'])) {
    die("Failed to load document request. Not found");
}

function getDocumentRequest($data) {
    $json_data = json_encode($data);

    $api_url = "http://127.0.0.1:8000/api/manage_request/";
    $options = [
        "http" => [
            "header" => "Content-Type: application/json\r\n",
            "method" => "POST",
            "content" => $json_data
        ],
    ];
    $context = stream_context_create($options);
    $response = file_get_contents($api_url, false, $context);
    if ($response === false) return null;
    return json_decode($response, true);
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['payment_screenshot'])) {
    $file = $_FILES['payment_screenshot'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $tmp_path = $file['tmp_name'];
        $upload_result = uploadPaymentScreenshot($tmp_path, $user_id, $request_id);
        if ($upload_result && !isset($upload_result['error'])) {
            echo "<p>Screenshot uploaded successfully! Awaiting confirmation.</p>";
            // Refresh request info after upload
            $request = getDocumentRequest($data);
        } else {
            echo "<p>Failed to upload screenshot. Try again.</p>";
        }
    } else {
        echo "<p>Error uploading file.</p>";
    }
}
function uploadPaymentScreenshot($file_path, $user_id, $request_id) {
    $api_url = "http://127.0.0.1:8000/api/manage_request/";

    $boundary = uniqid();
    $delimiter = '-------------' . $boundary;

    $file_contents = file_get_contents($file_path);
    $filename = basename($file_path);

    $post_data = "--" . $delimiter . "\r\n"
        . 'Content-Disposition: form-data; name="request_id"' . "\r\n\r\n"
        . $request_id . "\r\n"

        . "--" . $delimiter . "\r\n"
        . 'Content-Disposition: form-data; name="user_id"' . "\r\n\r\n"
        . $user_id . "\r\n"

        . "--" . $delimiter . "\r\n"
        . 'Content-Disposition: form-data; name="payment_screenshot"; filename="' . $filename . '"' . "\r\n"
        . "Content-Type: image/jpeg\r\n\r\n"
        . $file_contents . "\r\n"

        . "--" . $delimiter . "--\r\n";

    $headers = [
        "Content-Type: multipart/form-data; boundary=" . $delimiter,
        "Content-Length: " . strlen($post_data)
    ];

    $options = [
        "http" => [
            "method" => "PATCH",
            "header" => implode("\r\n", $headers),
            "content" => $post_data,
            "ignore_errors" => true,
        ],
    ];
    $context = stream_context_create($options);
    $response = file_get_contents($api_url, false, $context);
    if ($response === false) return null;
    return json_decode($response, true);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Process Payment</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 600px;
            margin: 40px auto;
            padding: 20px 30px;
            background: #e7e7e7ff;
            border-radius: 10px;
            text-align:center;
            color: #333;
        }
        h1 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 30px;
        }
        p {
            font-size: 1.1rem;
            margin-bottom: 15px;
        }
        strong {
            color: #34495e;
        }
        .status {
            font-weight: bold;
            margin-bottom: 25px;
        }
        .status.confirmed {
            background-color: green;
        }
        .status.pending {
            background-color: #63af1cff;
        }
        .status.unpaid {
            background-color: red;
        }
        form {
            text-align: center;
        }
        label {
            font-size: 1.1rem;
            display: block;
            margin-bottom: 10px;
        }
        input[type="file"] {
            background-color: #ccc;
            text-align: center;
            margin: 0px auto;
            width: 200px;
        }
        button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 12px 25px;
            font-size: 1.1rem;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        button:hover {
            background-color: #2980b9;
        }
        a.download-link {
            display: inline-block;
            margin-top: 20px;
            font-size: 1.2rem;
            text-decoration: none;
            background-color: #2ecc71;
            color: white;
            padding: 12px 30px;
            border-radius: 6px;
            transition: background-color 0.3s ease;
        }
        a.download-link:hover {
            background-color: #27ae60;
        }
        .confirmation-msg {
            font-size: 1.2rem;
            color: #27ae60;
            text-align: center;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <h1>Payment Processing for Document Request #<?= htmlspecialchars($request_id) ?></h1>

    <p><strong>Document Type:</strong> <?= htmlspecialchars($request['document_type']) ?></p>
    <p><strong>Full Name:</strong> <?= htmlspecialchars($request['full_name']) ?></p>

    <br />
    
    <?php if (!$request['confirmed']): ?>
        <form method="POST" enctype="multipart/form-data" novalidate>
            <label for="payment_screenshot">Upload Payment Screenshot:</label>
            <input type="file" name="payment_screenshot" id="payment_screenshot" accept="image/*" required />
            <br />
            <br />  
            <button type="submit" class="status <?= $request['confirmed'] 
                    ? 'confirmed' 
                    : ($request['payment_screenshot'] == NULL 
                        ? 'unpaid' 
                        : 'pending') ?>">
                <?= $request['confirmed'] 
                    ? 'Paid ✅' 
                    : ($request['payment_screenshot'] == NULL 
                        ? 'Unpaid' 
                        : 'Paid') ?>
            </button>
        </form>
    <?php else: ?>
        <p class="confirmation-msg">Your payment has been confirmed.</p>
        <p style="text-align:center;">
            <a href="http://127.0.0.1:8000<?= $request['download_link'] ?>" download>📥 Download Your Document</a>
        </p>
    <?php endif; ?>
    <br />
    <h5 style="font-size:12px; cursor:pointer; font-weight: normal" onclick="window.location.href='../home.php'">Go To Home</h5>
</body>
</html>
