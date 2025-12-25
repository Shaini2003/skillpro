<?php
require_once '../config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$course_id = (int)$data['course_id'];
$student_id = get_user_id();

$db = Database::getInstance()->getConnection();

// Check if already enrolled
$stmt = $db->prepare("SELECT enrollment_id FROM enrollments WHERE student_id = ? AND course_id = ?");
$stmt->execute([$student_id, $course_id]);

if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Already enrolled in this course']);
    exit;
}

// Insert enrollment
$stmt = $db->prepare("INSERT INTO enrollments (student_id, course_id, status) VALUES (?, ?, 'pending')");

if ($stmt->execute([$student_id, $course_id])) {
    log_activity($student_id, 'ENROLL', "Enrolled in course ID: $course_id");
    echo json_encode(['success' => true, 'message' => 'Enrollment successful']);
} else {
    echo json_encode(['success' => false, 'message' => 'Enrollment failed']);
}