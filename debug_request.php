<?php
header('Content-Type: application/json');

echo json_encode([
    'POST_data' => $_POST,
    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
    'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? 'no-set',
    'INPUT' => file_get_contents('php://input'),
    'firstName_value' => $_POST['firstName'] ?? 'NOT_SET',
    'firstName_length' => isset($_POST['firstName']) ? strlen($_POST['firstName']) : 0,
    'email_value' => $_POST['email'] ?? 'NOT_SET',
    'phone_value' => $_POST['your-number'] ?? 'NOT_SET'
], JSON_PRETTY_PRINT);
?>