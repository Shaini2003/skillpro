<?php
// student/materials.php
require_once '../config.php';
require_role('student');

$student_id = $_SESSION['user_id'];
$db = Database::getInstance()->getConnection();

// 1. Get List of Enrolled Courses (Approved only)
// Used for the filter dropdown
$course_sql = "
    SELECT c.course_id, c.course_name, c.course_code 
    FROM enrollments e 
    JOIN courses c ON e.course_id = c.course_id 
    WHERE e.student_id = ? AND e.status = 'approved'
";
$course_stmt = $db->prepare($course_sql);
$course_stmt->execute([$student_id]);
$my_courses = $course_stmt->fetchAll();

// 2. Fetch Materials
// Filter by specific course if selected, otherwise show all from enrolled courses
$selected_course = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

$mat_sql = "
    SELECT m.*, c.course_name, c.course_code, u.full_name as instructor_name
    FROM course_materials m
    JOIN courses c ON m.course_id = c.course_id
    JOIN users u ON m.uploaded_by = u.user_id
    JOIN enrollments e ON c.course_id = e.course_id
    WHERE e.student_id = ? AND e.status = 'approved'
";
$params = [$student_id];

if ($selected_course > 0) {
    $mat_sql .= " AND c.course_id = ?";
    $params[] = $selected_course;
}

$mat_sql .= " ORDER BY m.uploaded_at DESC";

$stmt = $db->prepare($mat_sql);
$stmt->execute($params);
$materials = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Course Materials</h2>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Dashboard
        </a>
    </div>

    <div class="row">
        <div class="col-lg-3 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0 fs-6">Filter by Course</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="materials.php" class="list-group-item list-group-item-action <?= $selected_course === 0 ? 'active' : '' ?>">
                        All Materials
                    </a>
                    <?php foreach ($my_courses as $course): ?>
                        <a href="materials.php?course_id=<?= $course['course_id'] ?>" 
                           class="list-group-item list-group-item-action <?= $selected_course == $course['course_id'] ? 'active' : '' ?>">
                            <?= htmlspecialchars($course['course_code']) ?>
                            <small class="d-block text-muted"><?= htmlspecialchars($course['course_name']) ?></small>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="card mt-3 bg-light border-0">
                <div class="card-body">
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        Only materials for courses with <strong>Approved</strong> enrollment status are visible here.
                    </small>
                </div>
            </div>
        </div>

        <div class="col-lg-9">
            <?php if (empty($materials)): ?>
                <div class="alert alert-info text-center py-5">
                    <i class="bi bi-folder2-open fs-1 d-block mb-3"></i>
                    <h4>No materials found.</h4>
                    <p>Your instructors haven't uploaded any resources for the selected criteria yet.</p>
                </div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-md-2 g-3">
                    <?php foreach ($materials as $mat): ?>
                        <?php 
                            // Determine icon based on file type
                            $icon = 'bi-file-earmark'; // default
                            $color = 'text-secondary';
                            switch($mat['file_type']) {
                                case 'pdf': $icon = 'bi-file-earmark-pdf'; $color = 'text-danger'; break;
                                case 'doc': 
                                case 'docx': $icon = 'bi-file-earmark-word'; $color = 'text-primary'; break;
                                case 'jpg':
                                case 'png':
                                case 'jpeg': $icon = 'bi-file-earmark-image'; $color = 'text-success'; break;
                                case 'zip':
                                case 'rar': $icon = 'bi-file-earmark-zip'; $color = 'text-warning'; break;
                            }
                        ?>
                        <div class="col">
                            <div class="card h-100 shadow-sm hover-shadow">
                                <div class="card-body d-flex align-items-start">
                                    <div class="me-3">
                                        <i class="bi <?= $icon ?> fs-1 <?= $color ?>"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="card-title mb-1 fw-bold">
                                            <?= htmlspecialchars($mat['title']) ?>
                                        </h6>
                                        <span class="badge bg-light text-dark border mb-2">
                                            <?= htmlspecialchars($mat['course_code']) ?>
                                        </span>
                                        <p class="card-text small text-muted mb-2">
                                            <?= htmlspecialchars($mat['description'] ?: 'No description provided.') ?>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center small text-secondary">
                                            <span><i class="bi bi-person"></i> <?= htmlspecialchars($mat['instructor_name']) ?></span>
                                            <span><i class="bi bi-calendar3"></i> <?= format_date($mat['uploaded_at']) ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer bg-white border-top-0 text-end">
                                    <a href="../<?= $mat['file_path'] ?>" class="btn btn-sm btn-outline-primary" target="_blank" download>
                                        <i class="bi bi-download me-1"></i> Download
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>