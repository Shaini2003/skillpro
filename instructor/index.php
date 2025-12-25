<?php
require_once '../config.php';
header('Content-Type: application/json');

$query = $_GET['q'] ?? '';
$db = Database::getInstance()->getConnection();

try {
    $stmt = $db->prepare("SELECT c.course_id, c.course_name, c.fee, c.duration, c.mode, cat.category_name, b.branch_name, c.description 
                          FROM courses c 
                          LEFT JOIN categories cat ON c.category_id = cat.category_id 
                          LEFT JOIN branches b ON c.branch_id = b.branch_id 
                          WHERE c.course_name LIKE ? OR c.course_code LIKE ? OR cat.category_name LIKE ? 
                          LIMIT 10");
    
    $searchTerm = "%$query%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($results);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
