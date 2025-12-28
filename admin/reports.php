<?php
require_once '../config.php';
require_role('admin');

$db = Database::getInstance()->getConnection();

// Get date range filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$branch_filter = isset($_GET['branch']) ? $_GET['branch'] : '';

// General Statistics
$stats = [];

// Total Students
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
$stats['total_students'] = $stmt->fetch()['count'];

// Active Students
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'student' AND status = 'active'");
$stats['active_students'] = $stmt->fetch()['count'];

// Total Courses
$stmt = $db->query("SELECT COUNT(*) as count FROM courses WHERE status = 'active'");
$stats['total_courses'] = $stmt->fetch()['count'];

// Total Enrollments
$stmt = $db->prepare("SELECT COUNT(*) as count FROM enrollments 
                      WHERE enrollment_date BETWEEN ? AND ?");
$stmt->execute([$start_date, $end_date]);
$stats['total_enrollments'] = $stmt->fetch()['count'];

// Approved Enrollments
$stmt = $db->prepare("SELECT COUNT(*) as count FROM enrollments 
                      WHERE status = 'approved' AND enrollment_date BETWEEN ? AND ?");
$stmt->execute([$start_date, $end_date]);
$stats['approved_enrollments'] = $stmt->fetch()['count'];

// Revenue
$stmt = $db->prepare("SELECT SUM(c.fee) as revenue FROM enrollments e
                      JOIN courses c ON e.course_id = c.course_id
                      WHERE e.payment_status = 'paid' 
                      AND e.enrollment_date BETWEEN ? AND ?");
$stmt->execute([$start_date, $end_date]);
$stats['total_revenue'] = $stmt->fetch()['revenue'] ?? 0;

// Pending Payments
$stmt = $db->prepare("SELECT SUM(c.fee - e.amount_paid) as pending FROM enrollments e
                      JOIN courses c ON e.course_id = c.course_id
                      WHERE e.payment_status IN ('pending', 'partial')
                      AND e.enrollment_date BETWEEN ? AND ?");
$stmt->execute([$start_date, $end_date]);
$stats['pending_payments'] = $stmt->fetch()['pending'] ?? 0;

// Enrollments by Course
$query = "SELECT c.course_name, c.course_code, COUNT(e.enrollment_id) as enrollment_count,
          SUM(CASE WHEN e.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
          SUM(CASE WHEN e.payment_status = 'paid' THEN c.fee ELSE 0 END) as revenue
          FROM courses c
          LEFT JOIN enrollments e ON c.course_id = e.course_id 
          AND e.enrollment_date BETWEEN ? AND ?";

$params = [$start_date, $end_date];

if ($branch_filter) {
    $query .= " AND c.branch_id = ?";
    $params[] = $branch_filter;
}

$query .= " WHERE c.status = 'active'
            GROUP BY c.course_id
            ORDER BY enrollment_count DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$course_enrollments = $stmt->fetchAll();

// Enrollments by Branch
$stmt = $db->prepare("SELECT b.branch_name, COUNT(e.enrollment_id) as enrollment_count,
                      SUM(CASE WHEN e.payment_status = 'paid' THEN c.fee ELSE 0 END) as revenue
                      FROM branches b
                      LEFT JOIN courses c ON b.branch_id = c.branch_id
                      LEFT JOIN enrollments e ON c.course_id = e.course_id 
                      AND e.enrollment_date BETWEEN ? AND ?
                      WHERE b.status = 'active'
                      GROUP BY b.branch_id
                      ORDER BY enrollment_count DESC");
$stmt->execute([$start_date, $end_date]);
$branch_enrollments = $stmt->fetchAll();

// Monthly Enrollment Trend (Last 6 months)
$stmt = $db->query("SELECT DATE_FORMAT(enrollment_date, '%Y-%m') as month, 
                    DATE_FORMAT(enrollment_date, '%b %Y') as month_name,
                    COUNT(*) as count
                    FROM enrollments
                    WHERE enrollment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    GROUP BY month
                    ORDER BY month");
$monthly_trend = $stmt->fetchAll();

// Enrollment Status Breakdown
$stmt = $db->prepare("SELECT status, COUNT(*) as count FROM enrollments 
                      WHERE enrollment_date BETWEEN ? AND ?
                      GROUP BY status");
$stmt->execute([$start_date, $end_date]);
$status_breakdown = $stmt->fetchAll();

// Payment Status Breakdown
$stmt = $db->prepare("SELECT payment_status, COUNT(*) as count FROM enrollments 
                      WHERE enrollment_date BETWEEN ? AND ?
                      GROUP BY payment_status");
$stmt->execute([$start_date, $end_date]);
$payment_breakdown = $stmt->fetchAll();

// Top 5 Students by Enrollments
$stmt = $db->prepare("SELECT u.full_name, u.email, COUNT(e.enrollment_id) as enrollment_count
                      FROM users u
                      JOIN enrollments e ON u.user_id = e.student_id
                      WHERE e.enrollment_date BETWEEN ? AND ?
                      GROUP BY u.user_id
                      ORDER BY enrollment_count DESC
                      LIMIT 5");
$stmt->execute([$start_date, $end_date]);
$top_students = $stmt->fetchAll();

$branches = get_branches();
$page_title = 'Reports & Analytics - Admin Panel';
include '../includes/header.php';
?>

<div class="container-fluid mt-4 mb-5">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Reports & Analytics</li>
                </ol>
            </nav>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">
                    <i class="bi bi-bar-chart-fill text-primary"></i> Reports & Analytics
                </h2>
                <button onclick="window.print()" class="btn btn-secondary">
                    <i class="bi bi-printer"></i> Print Report
                </button>
            </div>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="card mb-4 shadow-sm no-print">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" 
                           value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" 
                           value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Branch</label>
                    <select name="branch" class="form-select">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $branch): ?>
                        <option value="<?php echo $branch['branch_id']; ?>" 
                                <?php echo $branch_filter == $branch['branch_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($branch['branch_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Generate
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Report Period -->
    <div class="alert alert-info mb-4">
        <i class="bi bi-calendar-range"></i>
        <strong>Report Period:</strong> <?php echo format_date($start_date); ?> to <?php echo format_date($end_date); ?>
        <?php if ($branch_filter): ?>
        | <strong>Branch:</strong> 
        <?php
        $selected_branch = array_filter($branches, function($b) use ($branch_filter) {
            return $b['branch_id'] == $branch_filter;
        });
        echo htmlspecialchars(reset($selected_branch)['branch_name']);
        ?>
        <?php endif; ?>
    </div>

    <!-- Summary Statistics -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-people fs-1 text-success mb-2"></i>
                    <h3 class="mb-1"><?php echo $stats['total_students']; ?></h3>
                    <p class="text-muted mb-0">Total Students</p>
                    <small class="text-success"><?php echo $stats['active_students']; ?> active</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-book fs-1 text-primary mb-2"></i>
                    <h3 class="mb-1"><?php echo $stats['total_courses']; ?></h3>
                    <p class="text-muted mb-0">Active Courses</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-clipboard-check fs-1 text-info mb-2"></i>
                    <h3 class="mb-1"><?php echo $stats['total_enrollments']; ?></h3>
                    <p class="text-muted mb-0">Total Enrollments</p>
                    <small class="text-success"><?php echo $stats['approved_enrollments']; ?> approved</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-currency-exchange fs-1 text-warning mb-2"></i>
                    <h3 class="mb-1">Rs. <?php echo number_format($stats['total_revenue'], 0); ?></h3>
                    <p class="text-muted mb-0">Total Revenue</p>
                    <small class="text-danger">Pending: Rs. <?php echo number_format($stats['pending_payments'], 0); ?></small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Enrollment Status Breakdown -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-pie-chart text-info"></i> Enrollment Status
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Status</th>
                                    <th>Count</th>
                                    <th>Percentage</th>
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
                                foreach ($status_breakdown as $status): 
                                    $percentage = $stats['total_enrollments'] > 0 
                                        ? ($status['count'] / $stats['total_enrollments']) * 100 
                                        : 0;
                                ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-<?php echo $status_colors[$status['status']]; ?>">
                                            <?php echo ucfirst($status['status']); ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo $status['count']; ?></strong></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-<?php echo $status_colors[$status['status']]; ?>" 
                                                 style="width: <?php echo $percentage; ?>%">
                                                <?php echo number_format($percentage, 1); ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Status Breakdown -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-cash-stack text-success"></i> Payment Status
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Status</th>
                                    <th>Count</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $payment_colors = [
                                    'pending' => 'warning',
                                    'partial' => 'info',
                                    'paid' => 'success'
                                ];
                                foreach ($payment_breakdown as $payment): 
                                    $percentage = $stats['total_enrollments'] > 0 
                                        ? ($payment['count'] / $stats['total_enrollments']) * 100 
                                        : 0;
                                ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-<?php echo $payment_colors[$payment['payment_status']]; ?>">
                                            <?php echo ucfirst($payment['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo $payment['count']; ?></strong></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-<?php echo $payment_colors[$payment['payment_status']]; ?>" 
                                                 style="width: <?php echo $percentage; ?>%">
                                                <?php echo number_format($percentage, 1); ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enrollments by Course -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">
                <i class="bi bi-book text-primary"></i> Enrollments by Course
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Course Name</th>
                            <th>Code</th>
                            <th>Total Enrollments</th>
                            <th>Approved</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($course_enrollments as $course): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($course['course_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                            <td><span class="badge bg-primary"><?php echo $course['enrollment_count']; ?></span></td>
                            <td><span class="badge bg-success"><?php echo $course['approved_count']; ?></span></td>
                            <td><strong class="text-success">Rs. <?php echo number_format($course['revenue'], 2); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="2"><strong>Total</strong></td>
                            <td><strong><?php echo $stats['total_enrollments']; ?></strong></td>
                            <td><strong><?php echo $stats['approved_enrollments']; ?></strong></td>
                            <td><strong class="text-success">Rs. <?php echo number_format($stats['total_revenue'], 2); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Enrollments by Branch -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">
                <i class="bi bi-building text-info"></i> Enrollments by Branch
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Branch Name</th>
                            <th>Total Enrollments</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($branch_enrollments as $branch): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($branch['branch_name']); ?></strong></td>
                            <td><span class="badge bg-primary"><?php echo $branch['enrollment_count']; ?></span></td>
                            <td><strong class="text-success">Rs. <?php echo number_format($branch['revenue'], 2); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Monthly Trend -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-graph-up text-success"></i> Monthly Enrollment Trend
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Month</th>
                                    <th>Enrollments</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($monthly_trend as $month): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($month['month_name']); ?></td>
                                    <td><strong><?php echo $month['count']; ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Students -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-star text-warning"></i> Top Students
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Student Name</th>
                                    <th>Email</th>
                                    <th>Enrollments</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_students as $student): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($student['full_name']); ?></strong></td>
                                    <td><small><?php echo htmlspecialchars($student['email']); ?></small></td>
                                    <td><span class="badge bg-success"><?php echo $student['enrollment_count']; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .no-print {
        display: none !important;
    }
    .card {
        page-break-inside: avoid;
    }
}
</style>

<?php include '../includes/footer.php'; ?>