<?php
require_once '../config.php';
require_role('admin');

$db = Database::getInstance()->getConnection();
$success = '';
$errors = [];

// --- 1. HANDLE FORM SUBMISSIONS ---

// Handle enrollment status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Invalid request';
    } else {
        $enrollment_id = (int)$_POST['enrollment_id'];
        $new_status = $_POST['status'];
        // Fix: Ensure notes is not null using trim
        $notes = trim($_POST['notes'] ?? '');
        
        $stmt = $db->prepare("UPDATE enrollments SET status = ?, notes = ? WHERE enrollment_id = ?");
        if ($stmt->execute([$new_status, $notes, $enrollment_id])) {
            log_activity(get_user_id(), 'ENROLLMENT_UPDATE', "Updated enrollment ID: $enrollment_id to status: $new_status");
            $success = 'Enrollment status updated successfully!';
        } else {
            $errors[] = 'Failed to update enrollment';
        }
    }
}

// Handle payment status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_payment'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Invalid request';
    } else {
        $enrollment_id = (int)$_POST['enrollment_id'];
        $payment_status = $_POST['payment_status'];
        $amount_paid = (float)$_POST['amount_paid'];
        
        $stmt = $db->prepare("UPDATE enrollments SET payment_status = ?, amount_paid = ? WHERE enrollment_id = ?");
        if ($stmt->execute([$payment_status, $amount_paid, $enrollment_id])) {
            log_activity(get_user_id(), 'PAYMENT_UPDATE', "Updated payment for enrollment ID: $enrollment_id");
            $success = 'Payment status updated successfully!';
        } else {
            $errors[] = 'Failed to update payment status';
        }
    }
}

// Handle delete
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $enrollment_id = (int)$_GET['id'];
    $stmt = $db->prepare("DELETE FROM enrollments WHERE enrollment_id = ?");
    if ($stmt->execute([$enrollment_id])) {
        log_activity(get_user_id(), 'ENROLLMENT_DELETE', "Deleted enrollment ID: $enrollment_id");
        $success = 'Enrollment deleted successfully!';
    } else {
        $errors[] = 'Failed to delete enrollment';
    }
}

// --- 2. PREPARE DATA ---

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$course_filter = $_GET['course'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT e.*, c.course_name, c.course_code, c.fee, u.full_name as student_name, 
          u.email as student_email, u.phone as student_phone, b.branch_name
          FROM enrollments e
          JOIN courses c ON e.course_id = c.course_id
          JOIN users u ON e.student_id = u.user_id
          LEFT JOIN branches b ON c.branch_id = b.branch_id
          WHERE 1=1";

$params = [];

if ($status_filter) {
    $query .= " AND e.status = ?";
    $params[] = $status_filter;
}

if ($course_filter) {
    $query .= " AND e.course_id = ?";
    $params[] = $course_filter;
}

if ($search) {
    $query .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR c.course_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY e.enrollment_date DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$enrollments = $stmt->fetchAll();

// Get courses for dropdown
$courses = $db->query("SELECT course_id, course_name FROM courses WHERE status = 'active' ORDER BY course_name")->fetchAll();

// Get statistics
$stats = [];
$stats['pending'] = $db->query("SELECT COUNT(*) FROM enrollments WHERE status = 'pending'")->fetchColumn();
$stats['approved'] = $db->query("SELECT COUNT(*) FROM enrollments WHERE status = 'approved'")->fetchColumn();
$stats['rejected'] = $db->query("SELECT COUNT(*) FROM enrollments WHERE status = 'rejected'")->fetchColumn();
$stats['completed'] = $db->query("SELECT COUNT(*) FROM enrollments WHERE status = 'completed'")->fetchColumn();

$page_title = 'Manage Enrollments - Admin Panel';
include '../includes/header.php';
?>

<style>
    /* Prevent body shift when modal opens */
    body.modal-open {
        overflow-y: scroll !important;
        padding-right: 0 !important;
    }
    /* Stop textarea resize causing layout issues */
    textarea.no-resize {
        resize: none;
    }
</style>

<div class="container-fluid mt-4 mb-5">
    
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Manage Enrollments</li>
                </ol>
            </nav>

            <h2 class="mb-4">
                <i class="bi bi-clipboard-check-fill text-info"></i> Manage Enrollments
            </h2>

            <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill"></i> <?= $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                    <li><?= $error; ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-warning border-2">
                <div class="card-body text-center">
                    <i class="bi bi-clock-history fs-1 text-warning"></i>
                    <h3 class="mt-2 mb-1"><?= $stats['pending']; ?></h3>
                    <small class="text-muted">Pending</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-success border-2">
                <div class="card-body text-center">
                    <i class="bi bi-check-circle fs-1 text-success"></i>
                    <h3 class="mt-2 mb-1"><?= $stats['approved']; ?></h3>
                    <small class="text-muted">Approved</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-danger border-2">
                <div class="card-body text-center">
                    <i class="bi bi-x-circle fs-1 text-danger"></i>
                    <h3 class="mt-2 mb-1"><?= $stats['rejected']; ?></h3>
                    <small class="text-muted">Rejected</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-info border-2">
                <div class="card-body text-center">
                    <i class="bi bi-flag-fill fs-1 text-info"></i>
                    <h3 class="mt-2 mb-1"><?= $stats['completed']; ?></h3>
                    <small class="text-muted">Completed</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Student or course..." 
                           value="<?= htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="pending" <?= $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?= $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?= $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="completed" <?= $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Course</label>
                    <select name="course" class="form-select">
                        <option value="">All Courses</option>
                        <?php foreach ($courses as $course): ?>
                        <option value="<?= $course['course_id']; ?>" 
                                <?= $course_filter == $course['course_id'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($course['course_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Search
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Enrollment List (<?= count($enrollments); ?> records)</h5>
                <a href="reports.php" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-file-earmark-bar-graph"></i> Generate Report
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($enrollments)): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox fs-1 text-muted"></i>
                <p class="text-muted mt-3">No enrollments found</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Branch</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $status_colors = [
                            'pending' => 'warning', 'approved' => 'success',
                            'rejected' => 'danger', 'completed' => 'info'
                        ];
                        $payment_colors = [
                            'pending' => 'warning', 'partial' => 'info', 'paid' => 'success'
                        ];
                        
                        // LOOP 1: GENERATE ONLY THE TABLE ROWS
                        foreach ($enrollments as $enrollment): 
                        ?>
                        <tr>
                            <td><strong>#<?= $enrollment['enrollment_id']; ?></strong></td>
                            <td>
                                <strong><?= htmlspecialchars($enrollment['student_name']); ?></strong><br>
                                <small class="text-muted"><?= htmlspecialchars($enrollment['student_email']); ?></small>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($enrollment['course_name']); ?></strong><br>
                                <small class="text-muted"><?= htmlspecialchars($enrollment['course_code']); ?></small>
                            </td>
                            <td><?= htmlspecialchars($enrollment['branch_name'] ?? '-'); ?></td>
                            <td><?= format_date($enrollment['enrollment_date']); ?></td>
                            <td>
                                <span class="badge bg-<?= $status_colors[$enrollment['status']]; ?>">
                                    <?= ucfirst($enrollment['status']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?= $payment_colors[$enrollment['payment_status']]; ?>">
                                    <?= ucfirst($enrollment['payment_status']); ?>
                                </span><br>
                                <small class="text-muted">Rs. <?= number_format($enrollment['amount_paid'], 2); ?></small>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" 
                                        data-bs-target="#editModal<?= $enrollment['enrollment_id']; ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <a href="?action=delete&id=<?= $enrollment['enrollment_id']; ?>" 
                                   class="btn btn-sm btn-outline-danger"
                                   onclick="return confirm('Are you sure?');">
                                    <i class="bi bi-trash"></i>
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
</div>

<?php if (!empty($enrollments)): ?>
    <?php foreach ($enrollments as $enrollment): ?>
    <div class="modal fade" id="editModal<?= $enrollment['enrollment_id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Enrollment #<?= $enrollment['enrollment_id']; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6 class="text-primary">Student Details</h6>
                            <p class="mb-1"><strong>Name:</strong> <?= htmlspecialchars($enrollment['student_name']); ?></p>
                            <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($enrollment['student_email']); ?></p>
                            <p class="mb-1"><strong>Phone:</strong> <?= htmlspecialchars($enrollment['student_phone'] ?? 'N/A'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-primary">Course Details</h6>
                            <p class="mb-1"><strong>Course:</strong> <?= htmlspecialchars($enrollment['course_name']); ?></p>
                            <p class="mb-1"><strong>Code:</strong> <?= htmlspecialchars($enrollment['course_code']); ?></p>
                            <p class="mb-1"><strong>Total Fee:</strong> Rs. <?= number_format($enrollment['fee'], 2); ?></p>
                        </div>
                    </div>

                    <hr>

                    <form method="POST" class="mb-3">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                        <input type="hidden" name="enrollment_id" value="<?= $enrollment['enrollment_id']; ?>">
                        
                        <h6 class="fw-bold">Update Enrollment Status</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status *</label>
                                <select name="status" class="form-select" required>
                                    <option value="pending" <?= $enrollment['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?= $enrollment['status'] == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?= $enrollment['status'] == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    <option value="completed" <?= $enrollment['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control no-resize" rows="2" placeholder="Add notes here..."><?= htmlspecialchars($enrollment['notes'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="text-end">
                            <button type="submit" name="update_status" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save Status
                            </button>
                        </div>
                    </form>

                    <hr>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                        <input type="hidden" name="enrollment_id" value="<?= $enrollment['enrollment_id']; ?>">
                        
                        <h6 class="fw-bold">Update Payment Status</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Status *</label>
                                <select name="payment_status" class="form-select" required>
                                    <option value="pending" <?= $enrollment['payment_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="partial" <?= $enrollment['payment_status'] == 'partial' ? 'selected' : ''; ?>>Partial</option>
                                    <option value="paid" <?= $enrollment['payment_status'] == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Amount Paid (Rs) *</label>
                                <input type="number" name="amount_paid" class="form-control" step="0.01" 
                                       value="<?= $enrollment['amount_paid']; ?>" required>
                            </div>
                        </div>
                        <div class="text-end">
                            <button type="submit" name="update_payment" class="btn btn-success">
                                <i class="bi bi-currency-exchange"></i> Save Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>