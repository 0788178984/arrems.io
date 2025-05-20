<?php
header('Content-Type: application/json');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the raw POST data
$raw_input = file_get_contents('php://input');
error_log("Raw POST data: " . $raw_input);

// Try to decode JSON
$data = json_decode($raw_input, true);
$json_error = json_last_error_msg();
error_log("JSON decode error (if any): " . $json_error);

// Log the decoded data
error_log("Decoded data: " . print_r($data, true));

// Return debug information
echo json_encode([
    'raw_input' => $raw_input,
    'decoded_data' => $data,
    'json_error' => $json_error,
    'post_vars' => $_POST,
    'server_vars' => [
        'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
        'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
        'CONTENT_LENGTH' => $_SERVER['CONTENT_LENGTH'] ?? 'not set'
    ]
]);
?> 