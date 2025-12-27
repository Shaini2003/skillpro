<?php
require_once '../config.php';
require_role('admin');

$db = Database::getInstance()->getConnection();

// Get statistics
$stats = [];

// Total courses
$stmt = $db->query("SELECT COUNT(*) as count FROM courses WHERE status = 'active'");
$stats['total_courses'] = $stmt->fetch()['count'];

// Total students
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'student' AND status = 'active'");
$stats['total_students'] = $stmt->fetch()['count'];

// Total instructors
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'instructor' AND status = 'active'");
$stats['total_instructors'] = $stmt->fetch()['count'];

// Total enrollments
$stmt = $db->query("SELECT COUNT(*) as count FROM enrollments");
$stats['total_enrollments'] = $stmt->fetch()['count'];

// Pending enrollments
$stmt = $db->query("SELECT COUNT(*) as count FROM enrollments WHERE status = 'pending'");
$stats['pending_enrollments'] = $stmt->fetch()['count'];

// New inquiries
$stmt = $db->query("SELECT COUNT(*) as count FROM inquiries WHERE status = 'new'");
$stats['new_inquiries'] = $stmt->fetch()['count'];

// Revenue (total fees from paid enrollments)
$stmt = $db->query("SELECT SUM(c.fee) as revenue FROM enrollments e 
                    JOIN courses c ON e.course_id = c.course_id 
                    WHERE e.payment_status = 'paid'");
$stats['total_revenue'] = $stmt->fetch()['revenue'] ?? 0;

// Recent enrollments
$stmt = $db->query("SELECT e.*, c.course_name, c.course_code, u.full_name as student_name, u.email
                    FROM enrollments e
                    JOIN courses c ON e.course_id = c.course_id
                    JOIN users u ON e.student_id = u.user_id
                    ORDER BY e.enrollment_date DESC
                    LIMIT 10");
$recent_enrollments = $stmt->fetchAll();

// Recent inquiries
$stmt = $db->query("SELECT * FROM inquiries ORDER BY submitted_at DESC LIMIT 5");
$recent_inquiries = $stmt->fetchAll();

// Course enrollment stats
$stmt = $db->query("SELECT c.course_name, c.course_code, COUNT(e.enrollment_id) as enrollment_count
                    FROM courses c
                    LEFT JOIN enrollments e ON c.course_id = e.course_id
                    WHERE c.status = 'active'
                    GROUP BY c.course_id
                    ORDER BY enrollment_count DESC
                    LIMIT 5");
$popular_courses = $stmt->fetchAll();

// Monthly enrollment trend (last 6 months)
$stmt = $db->query("SELECT DATE_FORMAT(enrollment_date, '%Y-%m') as month, COUNT(*) as count
                    FROM enrollments
                    WHERE enrollment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    GROUP BY month
                    ORDER BY month");
$monthly_enrollments = $stmt->fetchAll();

$page_title = 'Admin Dashboard - SkillPro Institute';
include '../includes/header.php';
?>

<div class="container-fluid mt-4 mb-5">
    <div class="row">
        <div class="col-12 mb-4">
            <h2 class="mb-0">
                <i class="bi bi-speedometer2 text-primary"></i> Admin Dashboard
            </h2>
            <p class="text-muted">Welcome back, <?php echo htmlspecialchars(get_user_name()); ?>!</p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-1">Total Courses</p>
                            <h2 class="mb-0 text-primary"><?php echo $stats['total_courses']; ?></h2>
                            <small class="text-success">
                                <i class="bi bi-arrow-up"></i> Active courses
                            </small>
                        </div>
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                            <i class="bi bi-book-fill text-primary fs-3"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white border-top-0">
                    <a href="manage_courses.php" class="text-decoration-none small">
                        View All <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-1">Total Students</p>
                            <h2 class="mb-0 text-success"><?php echo $stats['total_students']; ?></h2>
                            <small class="text-success">
                                <i class="bi bi-arrow-up"></i> Active students
                            </small>
                        </div>
                        <div class="rounded-circle bg-success bg-opacity-10 p-3">
                            <i class="bi bi-people-fill text-success fs-3"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white border-top-0">
                    <a href="manage_students.php" class="text-decoration-none small">
                        View All <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-1">Total Enrollments</p>
                            <h2 class="mb-0 text-info"><?php echo $stats['total_enrollments']; ?></h2>
                            <?php if ($stats['pending_enrollments'] > 0): ?>
                            <small class="text-warning">
                                <i class="bi bi-clock"></i> <?php echo $stats['pending_enrollments']; ?> pending
                            </small>
                            <?php else: ?>
                            <small class="text-muted">All processed</small>
                            <?php endif; ?>
                        </div>
                        <div class="rounded-circle bg-info bg-opacity-10 p-3">
                            <i class="bi bi-clipboard-check-fill text-info fs-3"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white border-top-0">
                    <a href="manage_enrollments.php" class="text-decoration-none small">
                        View All <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-1">Total Revenue</p>
                            <h2 class="mb-0 text-warning">Rs. <?php echo number_format($stats['total_revenue'], 0); ?></h2>
                            <small class="text-success">
                                <i class="bi bi-graph-up"></i> From paid courses
                            </small>
                        </div>
                        <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                            <i class="bi bi-currency-exchange text-warning fs-3"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white border-top-0">
                    <a href="reports.php" class="text-decoration-none small">
                        View Reports <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card border-warning border-2 h-100">
                <div class="card-body text-center">
                    <i class="bi bi-clock-history fs-1 text-warning"></i>
                    <h3 class="mt-2 mb-1"><?php echo $stats['pending_enrollments']; ?></h3>
                    <p class="text-muted mb-0">Pending Approvals</p>
                    <a href="manage_enrollments.php?status=pending" class="btn btn-sm btn-warning mt-2">
                        Process Now
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card border-danger border-2 h-100">
                <div class="card-body text-center">
                    <i class="bi bi-chat-dots fs-1 text-danger"></i>
                    <h3 class="mt-2 mb-1"><?php echo $stats['new_inquiries']; ?></h3>
                    <p class="text-muted mb-0">New Inquiries</p>
                    <a href="manage_inquiries.php?status=new" class="btn btn-sm btn-danger mt-2">
                        Respond Now
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card border-info border-2 h-100">
                <div class="card-body text-center">
                    <i class="bi bi-person-badge fs-1 text-info"></i>
                    <h3 class="mt-2 mb-1"><?php echo $stats['total_instructors']; ?></h3>
                    <p class="text-muted mb-0">Active Instructors</p>
                    <a href="manage_instructors.php" class="btn btn-sm btn-info mt-2">
                        Manage
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Enrollments -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-clock-history text-primary"></i> Recent Enrollments
                        </h5>
                        <a href="manage_enrollments.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Student</th>
                                    <th>Course</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $status_colors = [
                                    'pending' => 'warning',
                                    'approved' => 'success',
                                    'rejected' => 'danger',
                                    'completed' => 'info'
                                ];
                                
                                foreach ($recent_enrollments as $enrollment): 
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($enrollment['student_name']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($enrollment['email']); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($enrollment['course_name']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($enrollment['course_code']); ?></small>
                                    </td>
                                    <td><?php echo format_date($enrollment['enrollment_date']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $status_colors[$enrollment['status']]; ?>">
                                            <?php echo ucfirst($enrollment['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="manage_enrollments.php?id=<?php echo $enrollment['enrollment_id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Popular Courses -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="bi bi-graph-up text-success"></i> Popular Courses
                    </h5>
                </div>
                <div class="card-body">
                    <?php foreach ($popular_courses as $course): ?>
                    <div class="mb-3 pb-3 <?php echo ($course !== end($popular_courses)) ? 'border-bottom' : ''; ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1"><?php echo htmlspecialchars($course['course_name']); ?></h6>
                                <small class="text-muted"><?php echo htmlspecialchars($course['course_code']); ?></small>
                            </div>
                            <span class="badge bg-success"><?php echo $course['enrollment_count']; ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Recent Inquiries -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="bi bi-chat-dots text-danger"></i> Recent Inquiries
                    </h5>
                </div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <?php if (empty($recent_inquiries)): ?>
                    <p class="text-muted text-center mb-0">No inquiries</p>
                    <?php else: ?>
                    <?php foreach ($recent_inquiries as $inquiry): ?>
                    <div class="inquiry-item mb-3 pb-3 <?php echo ($inquiry !== end($recent_inquiries)) ? 'border-bottom' : ''; ?>">
                        <div class="d-flex justify-content-between mb-1">
                            <h6 class="mb-0"><?php echo htmlspecialchars($inquiry['name']); ?></h6>
                            <?php
                            $inquiry_status_colors = [
                                'new' => 'danger',
                                'in_progress' => 'warning',
                                'resolved' => 'success'
                            ];
                            ?>
                            <span class="badge bg-<?php echo $inquiry_status_colors[$inquiry['status']]; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $inquiry['status'])); ?>
                            </span>
                        </div>
                        <small class="text-muted d-block mb-1"><?php echo htmlspecialchars($inquiry['subject']); ?></small>
                        <small class="text-muted"><?php echo format_datetime($inquiry['submitted_at']); ?></small>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-white text-center border-top">
                    <a href="manage_inquiries.php" class="btn btn-sm btn-outline-danger">View All</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-lightning-fill text-warning"></i> Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-2 col-6">
                            <a href="manage_courses.php?action=add" class="btn btn-outline-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3">
                                <i class="bi bi-plus-circle fs-3 mb-2"></i>
                                <span>Add Course</span>
                            </a>
                        </div>
                        <div class="col-md-2 col-6">
                            <a href="manage_students.php" class="btn btn-outline-success w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3">
                                <i class="bi bi-people fs-3 mb-2"></i>
                                <span>Manage Students</span>
                            </a>
                        </div>
                        <div class="col-md-2 col-6">
                            <a href="manage_enrollments.php" class="btn btn-outline-info w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3">
                                <i class="bi bi-clipboard-check fs-3 mb-2"></i>
                                <span>Enrollments</span>
                            </a>
                        </div>
                        <div class="col-md-2 col-6">
                            <a href="manage_notices.php?action=add" class="btn btn-outline-warning w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3">
                                <i class="bi bi-megaphone fs-3 mb-2"></i>
                                <span>Add Notice</span>
                            </a>
                        </div>
                        <div class="col-md-2 col-6">
                            <a href="manage_events.php?action=add" class="btn btn-outline-danger w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3">
                                <i class="bi bi-calendar-plus fs-3 mb-2"></i>
                                <span>Add Event</span>
                            </a>
                        </div>
                        <div class="col-md-2 col-6">
                            <a href="reports.php" class="btn btn-outline-secondary w-100 h-100 d-flex flex-column align-items-center justify-content-center py-3">
                                <i class="bi bi-bar-chart fs-3 mb-2"></i>
                                <span>Reports</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>