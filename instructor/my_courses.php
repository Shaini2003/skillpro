<?php
// instructor/my_courses.php
require_once '../config.php';
require_role('instructor');

$instructor_id = get_user_id();
$db = Database::getInstance()->getConnection();

$query = "
    SELECT c.*, b.branch_name, cat.category_name,
    (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.course_id AND e.status = 'approved') as student_count
    FROM courses c
    LEFT JOIN branches b ON c.branch_id = b.branch_id
    LEFT JOIN categories cat ON c.category_id = cat.category_id
    WHERE c.instructor_id = ?
    ORDER BY c.status, c.course_name
";

$stmt = $db->prepare($query);
$stmt->execute([$instructor_id]);
$courses = $stmt->fetchAll();

$page_title = 'My Courses';
include '../includes/header.php';
?>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-journal-bookmark-fill text-primary"></i> My Courses</h2>
        <span class="badge bg-primary fs-6"><?= count($courses) ?> Assigned</span>
    </div>

    <?php if (empty($courses)): ?>
        <div class="alert alert-info text-center py-5">
            <h4>No courses assigned yet.</h4>
            <p>Please contact the administrator to get courses assigned to your profile.</p>
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php foreach ($courses as $course): ?>
            <div class="col">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-header bg-white border-bottom-0 pt-4">
                        <div class="d-flex justify-content-between">
                            <span class="badge bg-info bg-opacity-10 text-info border border-info">
                                <?= htmlspecialchars($course['course_code']) ?>
                            </span>
                            <span class="badge bg-<?= $course['status'] == 'active' ? 'success' : 'secondary' ?>">
                                <?= ucfirst($course['status']) ?>
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title fw-bold mb-2"><?= htmlspecialchars($course['course_name']) ?></h5>
                        <p class="text-muted small mb-3">
                            <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($course['branch_name'] ?? 'Main Branch') ?>
                        </p>
                        
                        <div class="row g-2 mb-3 text-center">
                            <div class="col-6">
                                <div class="p-2 border rounded bg-light">
                                    <small class="d-block text-muted">Students</small>
                                    <span class="fw-bold"><?= $course['student_count'] ?></span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 border rounded bg-light">
                                    <small class="d-block text-muted">Mode</small>
                                    <span class="fw-bold"><?= ucfirst($course['mode']) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-top-0 pb-4">
                        <div class="d-grid gap-2">
                            <a href="students.php?course_id=<?= $course['course_id'] ?>" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-people"></i> View Students
                            </a>
                            <div class="d-flex gap-2">
                                <a href="materials.php?course_id=<?= $course['course_id'] ?>" class="btn btn-outline-secondary btn-sm flex-fill">
                                    <i class="bi bi-folder"></i> Materials
                                </a>
                                <a href="schedule.php?course_id=<?= $course['course_id'] ?>" class="btn btn-outline-secondary btn-sm flex-fill">
                                    <i class="bi bi-calendar"></i> Schedule
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>