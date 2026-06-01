<?php

$userid = $_POST['user_id'] ?? '';
$username = $_POST['username'] ?? '';
$document_type = $_POST['document_type'] ?? '';
$full_name = $_POST['full_name'] ?? '';
$address = $_POST['address'] ?? '';
$birth_date = $_POST['birth_date'] ?? '';
$birth_place = $_POST['birth_place'] ?? '';
$civil_status = $_POST['civil_status'] ?? '';
$citizenship = $_POST['citizenship'] ?? '';
$purpose = $_POST['purpose'] ?? '';
$years = $_POST['years'] ?? '';

$data = [
    "user_id" => $userid,
    "username" => $username,
    "document_type" => $document_type,
    "full_name" => $full_name,
    "address" => $address,
    "birth_date" => $birth_date,
    "birth_place" => $birth_place,
    "civil_status" => $civil_status,
    "citizenship" => $citizenship,
    "purpose" => $purpose,
    "years" => $years,
];

// Convert to JSON
$json_data = json_encode($data);

// Setup HTTP POST options for file_get_contents
$options = [
    "http" => [
        "header" => "Content-Type: application/json\r\n",
        "method" => "POST",
        "content" => $json_data,
        "ignore_errors" => true,
    ],
];

$context = stream_context_create($options);
$api_url = "http://127.0.0.1:8000/api/create_document_request/";

$response = file_get_contents($api_url, false, $context);
$response_data = json_decode($response, true);

if (isset($response_data['error'])) {
    die("Error: " . htmlspecialchars($response_data['error']));
}

$request_id = $response_data['request_id'];
// Request created as pending — send the user to the confirmation page.
echo json_encode(["redirect" => "request_submitted.php?request_id=$request_id"]);
exit;
?>