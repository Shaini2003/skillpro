<?php
require_once '../config.php';

$query = $_GET['q'] ?? '';

if (empty($query)) {
    echo json_encode([]);
    exit;
}

$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT c.*, cat.category_name, b.branch_name 
                      FROM courses c 
                      LEFT JOIN categories cat ON c.category_id = cat.category_id
                      LEFT JOIN branches b ON c.branch_id = b.branch_id
                      WHERE c.status = 'active' 
                      AND (c.course_name LIKE ? OR c.description LIKE ?)
                      LIMIT 10");

$searchTerm = "%$query%";
$stmt->execute([$searchTerm, $searchTerm]);

$results = $stmt->fetchAll();

header('Content-Type: application/json');
echo json_encode($results);