<?php
// instructor/students.php
require_once '../config.php';
require_role('instructor');

$instructor_id = get_user_id();
$db = Database::getInstance()->getConnection();
$course_id = $_GET['course_id'] ?? '';

// Get Filter Options
$stmt = $db->prepare("SELECT course_id, course_name FROM courses WHERE instructor_id = ?");
$stmt->execute([$instructor_id]);
$my_courses = $stmt->fetchAll();

// Build Query
$query = "
    SELECT e.*, u.full_name, u.email, u.phone, c.course_name
    FROM enrollments e
    JOIN users u ON e.student_id = u.user_id
    JOIN courses c ON e.course_id = c.course_id
    WHERE c.instructor_id = ? AND e.status = 'approved'
";
$params = [$instructor_id];

if ($course_id) {
    $query .= " AND c.course_id = ?";
    $params[] = $course_id;
}
$query .= " ORDER BY u.full_name ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

$page_title = 'My Students';
include '../includes/header.php';
?>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-people-fill text-info"></i> Enrolled Students</h2>
        <form class="d-flex" method="GET">
            <select name="course_id" class="form-select me-2" onchange="this.form.submit()">
                <option value="">All Courses</option>
                <?php foreach ($my_courses as $c): ?>
                    <option value="<?= $c['course_id'] ?>" <?= $course_id == $c['course_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['course_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Student Name</th>
                            <th>Contact Info</th>
                            <th>Course Enrolled</th>
                            <th>Enrolled Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">No students found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($students as $stu): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-light rounded-circle p-2 me-2 text-primary fw-bold">
                                            <?= strtoupper(substr($stu['full_name'], 0, 1)) ?>
                                        </div>
                                        <?= htmlspecialchars($stu['full_name']) ?>
                                    </div>
                                </td>
                                <td>
                                    <small class="d-block"><i class="bi bi-envelope"></i> <?= htmlspecialchars($stu['email']) ?></small>
                                    <small class="d-block text-muted"><i class="bi bi-telephone"></i> <?= htmlspecialchars($stu['phone']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($stu['course_name']) ?></td>
                                <td><?= date('M d, Y', strtotime($stu['enrollment_date'])) ?></td>
                                <td><span class="badge bg-success">Active</span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>