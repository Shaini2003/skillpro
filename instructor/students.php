<?php
// instructor/students.php
require_once '../config.php';
require_role('instructor');

$instructor_id = $_SESSION['user_id'];
$db = Database::getInstance()->getConnection();
$message = '';
$message_type = '';

// Handle Enrollment Actions (Approve/Reject)
if (isset($_GET['action']) && isset($_GET['eid'])) {
    $action = $_GET['action'];
    $enrollment_id = (int)$_GET['eid'];
    
    // Validate that this enrollment belongs to a course taught by this instructor
    $check_stmt = $db->prepare("
        SELECT e.enrollment_id 
        FROM enrollments e 
        JOIN courses c ON e.course_id = c.course_id 
        WHERE e.enrollment_id = ? AND c.instructor_id = ?
    ");
    $check_stmt->execute([$enrollment_id, $instructor_id]);
    
    if ($check_stmt->fetch()) {
        $new_status = ($action === 'approve') ? 'approved' : 'rejected';
        
        $update_stmt = $db->prepare("UPDATE enrollments SET status = ? WHERE enrollment_id = ?");
        if ($update_stmt->execute([$new_status, $enrollment_id])) {
            $message = "Enrollment status updated to " . ucfirst($new_status);
            $message_type = "success";
        } else {
            $message = "Failed to update status.";
            $message_type = "danger";
        }
    } else {
        $message = "Permission denied or invalid enrollment.";
        $message_type = "danger";
    }
}

// Get Instructor's Courses for Filter
$course_stmt = $db->prepare("SELECT course_id, course_name, course_code FROM courses WHERE instructor_id = ?");
$course_stmt->execute([$instructor_id]);
$my_courses = $course_stmt->fetchAll();

// Build Query for Students
$selected_course = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$selected_status = isset($_GET['status']) ? $_GET['status'] : '';

$sql = "
    SELECT e.*, u.full_name, u.email, u.phone, u.profile_image, c.course_name, c.course_code 
    FROM enrollments e 
    JOIN users u ON e.student_id = u.user_id 
    JOIN courses c ON e.course_id = c.course_id 
    WHERE c.instructor_id = ? 
";
$params = [$instructor_id];

if ($selected_course > 0) {
    $sql .= " AND c.course_id = ?";
    $params[] = $selected_course;
}

if ($selected_status) {
    $sql .= " AND e.status = ?";
    $params[] = $selected_status;
}

$sql .= " ORDER BY e.enrollment_date DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Student Management</h2>
        <a href="index.php" class="btn btn-outline-secondary">Dashboard</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card mb-4 shadow-sm bg-light">
        <div class="card-body py-3">
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-md-4">
                    <label class="visually-hidden">Filter by Course</label>
                    <select name="course_id" class="form-select">
                        <option value="">All My Courses</option>
                        <?php foreach ($my_courses as $course): ?>
                            <option value="<?= $course['course_id'] ?>" <?= $selected_course == $course['course_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="visually-hidden">Filter by Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="pending" <?= $selected_status == 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= $selected_status == 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= $selected_status == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="completed" <?= $selected_status == 'completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
                <div class="col-md-2">
                    <a href="students.php" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Student</th>
                            <th>Contact Info</th>
                            <th>Course</th>
                            <th>Enrolled Date</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-people fs-1 d-block mb-2"></i>
                                    No students found matching your criteria.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($students as $row): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                                <i class="bi bi-person text-secondary"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold"><?= htmlspecialchars($row['full_name']) ?></div>
                                                <small class="text-muted">ID: #<?= $row['student_id'] ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small">
                                            <i class="bi bi-envelope me-1"></i> <a href="mailto:<?= htmlspecialchars($row['email']) ?>" class="text-decoration-none"><?= htmlspecialchars($row['email']) ?></a><br>
                                            <i class="bi bi-telephone me-1"></i> <?= htmlspecialchars($row['phone']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?= htmlspecialchars($row['course_code']) ?>
                                        </span>
                                    </td>
                                    <td><?= format_date($row['enrollment_date']) ?></td>
                                    <td>
                                        <?php
                                            $badge_class = 'secondary';
                                            if ($row['status'] == 'approved') $badge_class = 'success';
                                            if ($row['status'] == 'pending') $badge_class = 'warning text-dark';
                                            if ($row['status'] == 'rejected') $badge_class = 'danger';
                                        ?>
                                        <span class="badge bg-<?= $badge_class ?>"><?= ucfirst($row['status']) ?></span>
                                    </td>
                                    <td>
                                        <?php if($row['payment_status'] == 'paid'): ?>
                                            <span class="text-success"><i class="bi bi-check-circle-fill"></i> Paid</span>
                                        <?php else: ?>
                                            <span class="text-warning"><i class="bi bi-hourglass-split"></i> Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($row['status'] == 'pending'): ?>
                                            <div class="btn-group btn-group-sm">
                                                <a href="students.php?action=approve&eid=<?= $row['enrollment_id'] ?>" class="btn btn-success" title="Approve" onclick="return confirm('Approve this student?');">
                                                    <i class="bi bi-check-lg"></i>
                                                </a>
                                                <a href="students.php?action=reject&eid=<?= $row['enrollment_id'] ?>" class="btn btn-danger" title="Reject" onclick="return confirm('Reject this student?');">
                                                    <i class="bi bi-x-lg"></i>
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-secondary" disabled>Managed</button>
                                        <?php endif; ?>
                                    </td>
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