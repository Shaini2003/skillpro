<?php
// student/my_courses.php
require_once '../config.php';
require_role('student');

$student_id = $_SESSION['user_id'];
$db = Database::getInstance()->getConnection();

// Fetch all enrollments for this student with course details
$query = "
    SELECT e.*, 
           c.course_name, c.course_code, c.description, c.mode, c.duration,
           u.full_name as instructor_name,
           b.branch_name
    FROM enrollments e
    JOIN courses c ON e.course_id = c.course_id
    LEFT JOIN users u ON c.instructor_id = u.user_id
    LEFT JOIN branches b ON c.branch_id = b.branch_id
    WHERE e.student_id = ?
    ORDER BY e.enrollment_date DESC
";

$stmt = $db->prepare($query);
$stmt->execute([$student_id]);
$all_enrollments = $stmt->fetchAll();

// Separate courses by status for better UI organization
$active_courses = [];
$pending_courses = [];
$history_courses = [];

foreach ($all_enrollments as $course) {
    if ($course['status'] == 'approved') {
        $active_courses[] = $course;
    } elseif ($course['status'] == 'pending') {
        $pending_courses[] = $course;
    } else {
        // rejected or completed
        $history_courses[] = $course;
    }
}

include '../includes/header.php';
?>

<div class="container mt-4 mb-5">
    <div class="row align-items-center mb-4">
        <div class="col-md-8">
            <h2>My Learning Dashboard</h2>
            <p class="text-muted">Track your enrollments and access course content.</p>
        </div>
        <div class="col-md-4 text-md-end">
            <a href="enroll.php" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> Enroll in New Course
            </a>
        </div>
    </div>

    <ul class="nav nav-tabs mb-4" id="courseTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button" role="tab">
                Active Courses <span class="badge bg-primary ms-1"><?= count($active_courses) ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">
                Pending Requests <span class="badge bg-warning text-dark ms-1"><?= count($pending_courses) ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab">
                History
            </button>
        </li>
    </ul>

    <div class="tab-content" id="courseTabsContent">
        
        <div class="tab-pane fade show active" id="active" role="tabpanel">
            <?php if (empty($active_courses)): ?>
                <div class="text-center py-5 bg-light rounded">
                    <i class="bi bi-book fs-1 text-muted mb-3 d-block"></i>
                    <h5>No active courses.</h5>
                    <p class="text-muted">You are not currently studying any courses.</p>
                    <a href="enroll.php" class="btn btn-outline-primary">Browse Courses</a>
                </div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-lg-2 g-4">
                    <?php foreach ($active_courses as $course): ?>
                        <div class="col">
                            <div class="card h-100 shadow-sm border-start border-4 border-success">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="badge bg-success bg-opacity-10 text-success">
                                            <?= htmlspecialchars($course['course_code']) ?>
                                        </span>
                                        <small class="text-muted">Enrolled: <?= format_date($course['enrollment_date']) ?></small>
                                    </div>
                                    
                                    <h5 class="card-title fw-bold">
                                        <?= htmlspecialchars($course['course_name']) ?>
                                    </h5>
                                    
                                    <div class="row g-2 mb-3 mt-3">
                                        <div class="col-6">
                                            <small class="text-muted d-block">Instructor</small>
                                            <span class="fw-semibold text-dark"><?= htmlspecialchars($course['instructor_name']) ?></span>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">Mode</small>
                                            <span class="fw-semibold text-dark"><?= ucfirst($course['mode']) ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="alert alert-light border p-2 mb-3 small">
                                        <?php if ($course['payment_status'] == 'paid'): ?>
                                            <i class="bi bi-check-circle-fill text-success me-1"></i> Payment Complete
                                        <?php else: ?>
                                            <i class="bi bi-exclamation-circle-fill text-warning me-1"></i> Payment Pending 
                                            <a href="#" class="text-decoration-none ms-1">(Pay Now)</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card-footer bg-white pb-3 pt-0 border-top-0">
                                    <div class="d-grid gap-2 d-md-flex">
                                        <a href="materials.php?course_id=<?= $course['course_id'] ?>" class="btn btn-primary flex-fill">
                                            <i class="bi bi-folder2-open me-1"></i> Materials
                                        </a>
                                        <a href="schedule.php?course_id=<?= $course['course_id'] ?>" class="btn btn-outline-secondary flex-fill">
                                            <i class="bi bi-calendar-event me-1"></i> Schedule
                                        </a>
                                        <a href="../course_details.php?id=<?= $course['course_id'] ?>" class="btn btn-outline-info flex-fill">
                                            Info
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="tab-pane fade" id="pending" role="tabpanel">
            <?php if (empty($pending_courses)): ?>
                <div class="alert alert-success">No pending enrollment requests.</div>
            <?php else: ?>
                <div class="list-group shadow-sm">
                    <?php foreach ($pending_courses as $course): ?>
                        <div class="list-group-item list-group-item-action p-4">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <h5 class="mb-0"><?= htmlspecialchars($course['course_name']) ?></h5>
                                        <span class="badge bg-warning text-dark">Pending Approval</span>
                                    </div>
                                    <small class="text-muted">
                                        Requested on: <?= format_datetime($course['enrollment_date']) ?> | 
                                        Branch: <?= htmlspecialchars($course['branch_name']) ?>
                                    </small>
                                </div>
                                <a href="../course_details.php?id=<?= $course['course_id'] ?>" class="btn btn-sm btn-outline-secondary">View Details</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="tab-pane fade" id="history" role="tabpanel">
            <?php if (empty($history_courses)): ?>
                <div class="alert alert-info">No course history available.</div>
            <?php else: ?>
                <div class="table-responsive bg-white shadow-sm rounded">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Course</th>
                                <th>Enrolled Date</th>
                                <th>Status</th>
                                <th>Outcome</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history_courses as $course): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($course['course_code']) ?></strong><br>
                                        <?= htmlspecialchars($course['course_name']) ?>
                                    </td>
                                    <td><?= format_date($course['enrollment_date']) ?></td>
                                    <td>
                                        <?php if ($course['status'] == 'completed'): ?>
                                            <span class="badge bg-success">Completed</span>
                                        <?php elseif ($course['status'] == 'rejected'): ?>
                                            <span class="badge bg-danger">Rejected</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($course['status'] == 'completed'): ?>
                                            <a href="#" class="btn btn-sm btn-link">View Certificate</a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
    </div>
</div>

<?php include '../includes/footer.php'; ?>