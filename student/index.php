<?php
require_once '../config.php';
require_role('student');

$student_id = get_user_id();
$db = Database::getInstance()->getConnection();

// Get student info
$stmt = $db->prepare("SELECT u.*, b.branch_name FROM users u 
                      LEFT JOIN branches b ON u.branch_id = b.branch_id 
                      WHERE u.user_id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

// Get enrolled courses count
$stmt = $db->prepare("SELECT COUNT(*) as count FROM enrollments WHERE student_id = ? AND status != 'rejected'");
$stmt->execute([$student_id]);
$enrolled_count = $stmt->fetch()['count'];

// Get pending enrollments
$stmt = $db->prepare("SELECT COUNT(*) as count FROM enrollments WHERE student_id = ? AND status = 'pending'");
$stmt->execute([$student_id]);
$pending_count = $stmt->fetch()['count'];

// Get completed courses
$stmt = $db->prepare("SELECT COUNT(*) as count FROM enrollments WHERE student_id = ? AND status = 'completed'");
$stmt->execute([$student_id]);
$completed_count = $stmt->fetch()['count'];

// Get enrolled courses with details
$stmt = $db->prepare("SELECT e.*, c.course_name, c.course_code, c.duration, c.mode, c.fee,
                      cat.category_name, b.branch_name, u.full_name as instructor_name
                      FROM enrollments e
                      JOIN courses c ON e.course_id = c.course_id
                      LEFT JOIN categories cat ON c.category_id = cat.category_id
                      LEFT JOIN branches b ON c.branch_id = b.branch_id
                      LEFT JOIN users u ON c.instructor_id = u.user_id
                      WHERE e.student_id = ?
                      ORDER BY e.enrollment_date DESC
                      LIMIT 5");
$stmt->execute([$student_id]);
$recent_enrollments = $stmt->fetchAll();

// Get upcoming events
$stmt = $db->query("SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 4");
$upcoming_events = $stmt->fetchAll();

// Get recent notices
$stmt = $db->query("SELECT * FROM notices WHERE status = 'published' AND (expiry_date IS NULL OR expiry_date >= CURDATE()) ORDER BY publish_date DESC LIMIT 3");
$recent_notices = $stmt->fetchAll();

$page_title = 'Student Dashboard - SkillPro Institute';
include '../includes/header.php';
?>

<div class="container-fluid mt-4 mb-5">
    <div class="col-12 mb-4">
        <div class="card shadow-lg border-0"
            style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff;">
            <div class="card-body p-4">
                <div class="row align-items-center">

                    <div class="col-md-8">
                        <h3 class="mb-2 text-white fw-bold">
                            Welcome back, <?= htmlspecialchars($student['full_name']) ?> 👋
                        </h3>

                        <p class="mb-0 text-white-50">
                            <i class="bi bi-building"></i>
                            <?= htmlspecialchars($student['branch_name'] ?? 'Main') ?>
                        </p>
                    </div>

                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <small class="text-white-50 d-block mb-1">Member since</small>
                        <h6 class="mb-3 text-white fw-semibold">
                            <?= date('F Y', strtotime($student['created_at'])) ?>
                        </h6>
                        
                        <a href="profile.php" class="btn btn-light text-primary fw-bold btn-sm shadow-sm px-3 rounded-pill">
                            <i class="bi bi-person-circle me-1"></i> View My Profile
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </div>


    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-1">Enrolled Courses</p>
                            <h2 class="mb-0 text-primary"><?= $enrolled_count ?></h2>
                        </div>
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                            <i class="bi bi-book-fill text-primary fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-1">Pending Approvals</p>
                            <h2 class="mb-0 text-warning"><?= $pending_count ?></h2>
                        </div>
                        <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                            <i class="bi bi-clock-fill text-warning fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-1">Completed</p>
                            <h2 class="mb-0 text-success"><?= $completed_count ?></h2>
                        </div>
                        <div class="rounded-circle bg-success bg-opacity-10 p-3">
                            <i class="bi bi-check-circle-fill text-success fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-1">Available Courses</p>
                            <h2 class="mb-0 text-info">
                                <?php
                                $stmt = $db->query("SELECT COUNT(*) as count FROM courses WHERE status = 'active'");
                                echo $stmt->fetch()['count'];
                                ?>
                            </h2>
                        </div>
                        <div class="rounded-circle bg-info bg-opacity-10 p-3">
                            <i class="bi bi-grid-fill text-info fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- My Courses -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-journal-bookmark-fill text-primary"></i> My Courses
                        </h5>
                        <a href="my_courses.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_enrollments)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox fs-1 text-muted"></i>
                            <p class="text-muted mt-3 mb-3">You haven't enrolled in any courses yet</p>
                            <a href="../courses.php" class="btn btn-primary">Browse Courses</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Course</th>
                                        <th>Category</th>
                                        <th>Mode</th>
                                        <th>Status</th>
                                        <th>Payment</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_enrollments as $enrollment):
                                        $status_colors = [
                                            'pending' => 'warning',
                                            'approved' => 'success',
                                            'rejected' => 'danger',
                                            'completed' => 'info'
                                        ];
                                        $payment_colors = [
                                            'pending' => 'warning',
                                            'partial' => 'info',
                                            'paid' => 'success'
                                        ];
                                    ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($enrollment['course_name']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($enrollment['course_code']) ?></small>
                                            </td>
                                            <td><?= htmlspecialchars($enrollment['category_name']) ?></td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?= ucfirst($enrollment['mode']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $status_colors[$enrollment['status']] ?>">
                                                    <?= ucfirst($enrollment['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $payment_colors[$enrollment['payment_status']] ?>">
                                                    <?= ucfirst($enrollment['payment_status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="../course_details.php?id=<?= $enrollment['course_id'] ?>"
                                                    class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card shadow-sm border-0 mt-4">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="bi bi-lightning-fill text-warning"></i> Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3 col-6">
                            <a href="../courses.php" class="btn btn-outline-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3">
                                <i class="bi bi-search fs-3 mb-2"></i>
                                <span>Browse Courses</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="enroll.php" class="btn btn-outline-success w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3">
                                <i class="bi bi-plus-circle fs-3 mb-2"></i>
                                <span>Enroll Course</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="schedule.php" class="btn btn-outline-info w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3">
                                <i class="bi bi-calendar-week fs-3 mb-2"></i>
                                <span>My Schedule</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="materials.php" class="btn btn-outline-danger w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3">
                                <i class="bi bi-file-earmark-text fs-3 mb-2"></i>
                                <span>Materials</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Upcoming Events -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="bi bi-calendar-event text-success"></i> Upcoming Events
                    </h5>
                </div>
                <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                    <?php if (empty($upcoming_events)): ?>
                        <p class="text-muted text-center mb-0">No upcoming events</p>
                    <?php else: ?>
                        <?php foreach ($upcoming_events as $event): ?>
                            <div class="event-item mb-3 pb-3 <?= $event !== end($upcoming_events) ? 'border-bottom' : '' ?>">
                                <div class="d-flex">
                                    <div class="text-center me-3" style="min-width: 50px;">
                                        <div class="bg-success text-white rounded p-2">
                                            <div class="fw-bold"><?= date('d', strtotime($event['event_date'])) ?></div>
                                            <small><?= date('M', strtotime($event['event_date'])) ?></small>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?= htmlspecialchars($event['event_title']) ?></h6>
                                        <small class="text-muted">
                                            <i class="bi bi-clock"></i> <?= date('g:i A', strtotime($event['start_time'])) ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-white text-center border-top">
                    <a href="../events.php" class="btn btn-sm btn-outline-success">View All Events</a>
                </div>
            </div>

            <!-- Recent Notices -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="bi bi-megaphone text-warning"></i> Recent Notices
                    </h5>
                </div>
                <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                    <?php if (empty($recent_notices)): ?>
                        <p class="text-muted text-center mb-0">No notices available</p>
                    <?php else: ?>
                        <?php foreach ($recent_notices as $notice): ?>
                            <div class="notice-item mb-3 pb-3 <?= $notice !== end($recent_notices) ? 'border-bottom' : '' ?>">
                                <div class="d-flex justify-content-between mb-1">
                                    <h6 class="mb-0"><?= htmlspecialchars($notice['title']) ?></h6>
                                    <span class="badge bg-<?= $notice['priority'] == 'high' ? 'danger' : ($notice['priority'] == 'medium' ? 'warning' : 'secondary') ?>">
                                        <?= ucfirst($notice['priority']) ?>
                                    </span>
                                </div>
                                <small class="text-muted"><?= format_date($notice['publish_date']) ?></small>
                                <p class="mb-0 mt-1 small"><?= substr(htmlspecialchars($notice['content']), 0, 100) ?>...</p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-white text-center border-top">
                    <a href="../notices.php" class="btn btn-sm btn-outline-warning">View All Notices</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>