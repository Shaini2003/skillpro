<?php
// instructor/index.php
require_once '../config.php';
require_role('instructor');

$instructor_id = get_user_id();
$db = Database::getInstance()->getConnection();

// Get Instructor Info
$stmt = $db->prepare("SELECT full_name, email, phone FROM users WHERE user_id = ?");
$stmt->execute([$instructor_id]);
$instructor = $stmt->fetch();

// Statistics
// 1. Total Courses Taught
$stmt = $db->prepare("SELECT COUNT(*) FROM courses WHERE instructor_id = ? AND status = 'active'");
$stmt->execute([$instructor_id]);
$total_courses = $stmt->fetchColumn();

// 2. Total Students
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT e.student_id) 
    FROM enrollments e 
    JOIN courses c ON e.course_id = c.course_id 
    WHERE c.instructor_id = ? AND e.status = 'approved'
");
$stmt->execute([$instructor_id]);
$total_students = $stmt->fetchColumn();

// 3. Weekly Schedule
$stmt = $db->prepare("
    SELECT s.*, c.course_name, c.course_code 
    FROM schedules s 
    JOIN courses c ON s.course_id = c.course_id 
    WHERE c.instructor_id = ? 
    ORDER BY FIELD(s.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), s.start_time ASC
    LIMIT 5
");
$stmt->execute([$instructor_id]);
$weekly_schedule = $stmt->fetchAll();

// 4. Recent Enrollments (Pending)
$stmt = $db->prepare("
    SELECT e.*, c.course_name, u.full_name as student_name
    FROM enrollments e
    JOIN courses c ON e.course_id = c.course_id
    JOIN users u ON e.student_id = u.user_id
    WHERE c.instructor_id = ? AND e.status = 'pending'
    ORDER BY e.enrollment_date DESC
    LIMIT 3
");
$stmt->execute([$instructor_id]);
$pending_enrollments = $stmt->fetchAll();

// 5. Recent Materials
$stmt = $db->prepare("
    SELECT m.*, c.course_code 
    FROM course_materials m 
    JOIN courses c ON m.course_id = c.course_id 
    WHERE c.instructor_id = ? 
    ORDER BY m.uploaded_at DESC 
    LIMIT 4
");
$stmt->execute([$instructor_id]);
$recent_materials = $stmt->fetchAll();

$page_title = 'Instructor Dashboard';
include '../includes/header.php';
?>

<style>
    /* Add hover effect to cards */
    .hover-card { transition: transform 0.2s ease-in-out; }
    .hover-card:hover { transform: translateY(-5px); cursor: pointer; }
</style>

<div class="container-fluid mt-4 mb-5">
    <div class="card shadow-lg border-0 mb-4" style="background: linear-gradient(135deg, #2b5876 0%, #4e4376 100%); color: white;">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="fw-bold mb-1">Hello, <?= htmlspecialchars($instructor['full_name']) ?>! 👨‍🏫</h2>
                    <p class="mb-0 opacity-75">Here is your teaching summary.</p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <a href="materials.php" class="btn btn-light text-primary fw-bold shadow-sm me-2">
                        <i class="bi bi-folder-plus"></i> Add Material
                    </a>
                    <a href="schedule.php" class="btn btn-outline-light fw-bold shadow-sm">
                        <i class="bi bi-calendar-plus"></i> Schedule
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 hover-card">
                <div class="card-body d-flex align-items-center">
                    <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3">
                        <i class="bi bi-book-fill text-primary fs-4"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-0">Active Courses</h6>
                        <h3 class="fw-bold mb-0"><?= $total_courses ?></h3>
                        <a href="my_courses.php" class="stretched-link"></a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 hover-card">
                <div class="card-body d-flex align-items-center">
                    <div class="bg-success bg-opacity-10 p-3 rounded-circle me-3">
                        <i class="bi bi-people-fill text-success fs-4"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-0">Total Students</h6>
                        <h3 class="fw-bold mb-0"><?= $total_students ?></h3>
                        <a href="students.php" class="stretched-link"></a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 hover-card">
                <div class="card-body d-flex align-items-center">
                    <div class="bg-warning bg-opacity-10 p-3 rounded-circle me-3">
                        <i class="bi bi-person-plus-fill text-warning fs-4"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-0">Pending Requests</h6>
                        <h3 class="fw-bold mb-0"><?= count($pending_enrollments) ?></h3>
                        <a href="students.php" class="stretched-link"></a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100 hover-card">
                <div class="card-body d-flex align-items-center">
                    <div class="bg-info bg-opacity-10 p-3 rounded-circle me-3">
                        <i class="bi bi-folder2-open text-info fs-4"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-0">Recent Uploads</h6>
                        <h3 class="fw-bold mb-0"><?= count($recent_materials) ?></h3>
                        <a href="materials.php" class="stretched-link"></a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0"><i class="bi bi-calendar-week text-primary"></i> Weekly Schedule</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($weekly_schedule)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-calendar-x fs-1 text-muted"></i>
                            <p class="text-muted mt-2">No weekly classes scheduled.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Day</th>
                                        <th>Time</th>
                                        <th>Course</th>
                                        <th>Room</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($weekly_schedule as $class): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <?= htmlspecialchars($class['day_of_week']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= date('g:i A', strtotime($class['start_time'])) ?> - <?= date('g:i A', strtotime($class['end_time'])) ?>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($class['course_code']) ?></strong>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($class['room_number'] ?? 'Online') ?>
                                            </small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-white border-top text-center">
                    <a href="schedule.php" class="text-decoration-none">Manage Full Schedule &rarr;</a>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-hourglass-split text-warning"></i> Recent Enrollments</h5>
                    <a href="students.php" class="btn btn-sm btn-link p-0">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_enrollments)): ?>
                        <p class="text-muted text-center my-4">No pending enrollments.</p>
                    <?php else: ?>
                        <?php foreach ($pending_enrollments as $req): ?>
                        <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                            <div class="flex-shrink-0">
                                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                                    <span class="fw-bold text-primary"><?= strtoupper(substr($req['student_name'], 0, 1)) ?></span>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0"><?= htmlspecialchars($req['student_name']) ?></h6>
                                <small class="text-muted">For: <?= htmlspecialchars($req['course_name']) ?></small>
                            </div>
                            <div>
                                <a href="students.php" class="btn btn-sm btn-outline-primary">View</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-folder-check text-info"></i> Recent Uploads</h5>
                    <a href="materials.php" class="btn btn-sm btn-link p-0">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recent_materials)): ?>
                        <div class="text-center py-4">
                            <p class="text-muted mb-0 small">No materials uploaded yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_materials as $file): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div class="overflow-hidden">
                                    <h6 class="mb-0 text-truncate" title="<?= htmlspecialchars($file['title']) ?>">
                                        <i class="bi bi-file-earmark-text text-secondary me-1"></i>
                                        <?= htmlspecialchars($file['title']) ?>
                                    </h6>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($file['course_code']) ?> • 
                                        <?= date('M d', strtotime($file['uploaded_at'])) ?>
                                    </small>
                                </div>
                                <a href="../uploads/materials/<?= $file['file_path'] ?>" class="btn btn-sm btn-light text-primary" download>
                                    <i class="bi bi-download"></i>
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>