<?php
// instructor/my_courses.php
require_once '../config.php';
require_role('instructor');

$instructor_id = $_SESSION['user_id'];
$db = Database::getInstance()->getConnection();

// Fetch courses assigned to this instructor with student counts
// We only count 'approved' enrollments for the student count
$query = "
    SELECT c.*, 
           b.branch_name, 
           cat.category_name,
           (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.course_id AND e.status = 'approved') as active_students,
           (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.course_id AND e.status = 'pending') as pending_requests
    FROM courses c 
    LEFT JOIN branches b ON c.branch_id = b.branch_id
    LEFT JOIN categories cat ON c.category_id = cat.category_id
    WHERE c.instructor_id = ?
    ORDER BY c.status ASC, c.created_at DESC
";

$stmt = $db->prepare($query);
$stmt->execute([$instructor_id]);
$courses = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>My Courses</h2>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Dashboard
        </a>
    </div>

    <?php if (empty($courses)): ?>
        <div class="alert alert-info text-center py-5">
            <i class="bi bi-journal-x fs-1 d-block mb-3"></i>
            <h4>No courses assigned yet.</h4>
            <p>Contact the administrator if you believe this is an error.</p>
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php foreach ($courses as $course): ?>
                <div class="col">
                    <div class="card h-100 shadow-sm border-top-0 border-end-0 border-bottom-0 border-start border-4 border-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge bg-light text-primary border border-primary">
                                    <?= htmlspecialchars($course['course_code']) ?>
                                </span>
                                <?php if ($course['status'] === 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?= ucfirst($course['status']) ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <h5 class="card-title mb-3"><?= htmlspecialchars($course['course_name']) ?></h5>
                            
                            <div class="mb-3 small text-muted">
                                <div class="mb-1">
                                    <i class="bi bi-geo-alt-fill me-1"></i> <?= htmlspecialchars($course['branch_name']) ?>
                                </div>
                                <div>
                                    <i class="bi bi-folder-fill me-1"></i> <?= htmlspecialchars($course['category_name']) ?>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between text-center bg-light rounded p-2 mb-3">
                                <div>
                                    <h6 class="mb-0 fw-bold"><?= $course['active_students'] ?></h6>
                                    <small class="text-muted" style="font-size: 0.75rem;">Students</small>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold"><?= $course['max_students'] ?></h6>
                                    <small class="text-muted" style="font-size: 0.75rem;">Capacity</small>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold text-warning"><?= $course['pending_requests'] ?></h6>
                                    <small class="text-muted" style="font-size: 0.75rem;">Pending</small>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <a href="students.php?course_id=<?= $course['course_id'] ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-people me-1"></i> View Students
                                </a>
                                <div class="btn-group">
                                    <a href="schedule.php?course_id=<?= $course['course_id'] ?>" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-calendar-week me-1"></i> Schedule
                                    </a>
                                    <a href="materials.php?course_id=<?= $course['course_id'] ?>" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-upload me-1"></i> Materials
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-white text-muted small">
                            <i class="bi bi-clock"></i> Duration: <?= htmlspecialchars($course['duration']) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>