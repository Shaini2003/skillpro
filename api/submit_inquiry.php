<?php
// api/submit_inquiry.php
require_once '../config.php';

// Set header to return JSON
header('Content-Type: application/json');

// Ensure the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// Retrieve data: Try to decode JSON input first (common for modern fetch API)
$input = json_decode(file_get_contents('php://input'), true);

// If JSON decoding failed (e.g., standard FormData was sent), use $_POST
if (json_last_error() !== JSON_ERROR_NONE) {
    $input = $_POST;
}

// Sanitize inputs
$name = sanitize_input($input['name'] ?? '');
$email = sanitize_input($input['email'] ?? '');
$phone = sanitize_input($input['phone'] ?? '');
$subject = sanitize_input($input['subject'] ?? '');
$message_text = sanitize_input($input['message'] ?? '');

// Validation
$errors = [];

if (empty($name)) {
    $errors[] = "Name is required.";
}
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Valid email is required.";
}
if (empty($subject)) {
    $errors[] = "Subject is required.";
}
if (empty($message_text)) {
    $errors[] = "Message cannot be empty.";
}

// If there are validation errors, return them
if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Prepare SQL statement
    $stmt = $db->prepare("INSERT INTO inquiries (name, email, phone, subject, message, status) VALUES (?, ?, ?, ?, ?, 'new')");
    
    // Execute
    if ($stmt->execute([$name, $email, $phone, $subject, $message_text])) {
        echo json_encode(['success' => true, 'message' => 'Thank you! Your inquiry has been submitted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save inquiry. Please try again later.']);
    }

} catch (PDOException $e) {
    // In production, log the error ($e->getMessage()) to a file instead of showing it
    echo json_encode(['success' => false, 'message' => 'A database error occurred.']);
}
?>