<?php
require_once '../config.php';
require_role('admin');

$db = Database::getInstance()->getConnection();
$success = '';
$errors = [];

// Handle Update Student Status
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Invalid request';
    } else {
        $student_id = (int)$_POST['student_id'];
        $status = $_POST['status'];
        
        $stmt = $db->prepare("UPDATE users SET status = ? WHERE user_id = ? AND role = 'student'");
        if ($stmt->execute([$status, $student_id])) {
            log_activity(get_user_id(), 'STUDENT_STATUS_UPDATE', "Updated student ID: $student_id status to: $status");
            $success = 'Student status updated successfully!';
        } else {
            $errors[] = 'Failed to update status';
        }
    }
}

// Handle Edit Student
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_student'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Invalid request';
    } else {
        $student_id = (int)$_POST['student_id'];
        $full_name = sanitize_input($_POST['full_name']);
        $email = sanitize_input($_POST['email']);
        $phone = sanitize_input($_POST['phone']);
        $branch_id = (int)$_POST['branch_id'];
        $status = $_POST['status'];
        
        // Validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address';
        }
        
        if (empty($errors)) {
            // Check if email exists for other users
            $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $stmt->execute([$email, $student_id]);
            
            if ($stmt->fetch()) {
                $errors[] = 'Email already exists';
            } else {
                $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, branch_id = ?, status = ? 
                                      WHERE user_id = ? AND role = 'student'");
                if ($stmt->execute([$full_name, $email, $phone, $branch_id, $status, $student_id])) {
                    log_activity(get_user_id(), 'STUDENT_UPDATE', "Updated student: $full_name");
                    $success = 'Student updated successfully!';
                } else {
                    $errors[] = 'Failed to update student';
                }
            }
        }
    }
}

// Handle Delete
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $student_id = (int)$_GET['id'];
    
    // Check if student has enrollments
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM enrollments WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $enrollment_count = $stmt->fetch()['count'];
    
    if ($enrollment_count > 0) {
        $errors[] = "Cannot delete student. They have $enrollment_count enrollment(s). Please remove enrollments first.";
    } else {
        $stmt = $db->prepare("DELETE FROM users WHERE user_id = ? AND role = 'student'");
        if ($stmt->execute([$student_id])) {
            log_activity(get_user_id(), 'STUDENT_DELETE', "Deleted student ID: $student_id");
            $success = 'Student deleted successfully!';
        } else {
            $errors[] = 'Failed to delete student';
        }
    }
}

// Get filter parameters
$branch_filter = isset($_GET['branch']) ? $_GET['branch'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query
$query = "SELECT u.*, b.branch_name,
          (SELECT COUNT(*) FROM enrollments WHERE student_id = u.user_id) as enrollment_count,
          (SELECT COUNT(*) FROM enrollments WHERE student_id = u.user_id AND status = 'approved') as active_enrollments
          FROM users u
          LEFT JOIN branches b ON u.branch_id = b.branch_id
          WHERE u.role = 'student'";

$params = [];

if ($branch_filter) {
    $query .= " AND u.branch_id = ?";
    $params[] = $branch_filter;
}

if ($status_filter) {
    $query .= " AND u.status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $query .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY u.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Get branches for dropdown
$branches = get_branches();

// Get statistics
$stats = [];
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'student' AND status = 'active'");
$stats['active'] = $stmt->fetch()['count'];
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'student' AND status = 'inactive'");
$stats['inactive'] = $stmt->fetch()['count'];
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'student' AND status = 'pending'");
$stats['pending'] = $stmt->fetch()['count'];
$stmt = $db->query("SELECT COUNT(*) as count FROM enrollments WHERE status IN ('approved', 'pending')");
$stats['total_enrollments'] = $stmt->fetch()['count'];

$page_title = 'Manage Students - Admin Panel';
include '../includes/header.php';
?>

<div class="container-fluid mt-4 mb-5">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Manage Students</li>
                </ol>
            </nav>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">
                    <i class="bi bi-people-fill text-success"></i> Manage Students
                </h2>
                <a href="../register.php" class="btn btn-success" target="_blank">
                    <i class="bi bi-plus-circle"></i> Student Registration Page
                </a>
            </div>

            <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-success border-2">
                <div class="card-body text-center">
                    <i class="bi bi-person-check fs-1 text-success"></i>
                    <h3 class="mt-2 mb-1"><?php echo $stats['active']; ?></h3>
                    <small class="text-muted">Active Students</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-warning border-2">
                <div class="card-body text-center">
                    <i class="bi bi-hourglass-split fs-1 text-warning"></i>
                    <h3 class="mt-2 mb-1"><?php echo $stats['pending']; ?></h3>
                    <small class="text-muted">Pending Approval</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-secondary border-2">
                <div class="card-body text-center">
                    <i class="bi bi-person-x fs-1 text-secondary"></i>
                    <h3 class="mt-2 mb-1"><?php echo $stats['inactive']; ?></h3>
                    <small class="text-muted">Inactive Students</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-primary border-2">
                <div class="card-body text-center">
                    <i class="bi bi-clipboard-check fs-1 text-primary"></i>
                    <h3 class="mt-2 mb-1"><?php echo $stats['total_enrollments']; ?></h3>
                    <small class="text-muted">Total Enrollments</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Name, email, or username..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
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
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Search
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Students Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0">Students List (<?php echo count($students); ?> students)</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($students)): ?>
            <div class="text-center py-5">
                <i class="bi bi-people fs-1 text-muted"></i>
                <p class="text-muted mt-3">No students found</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Student</th>
                            <th>Contact</th>
                            <th>Branch</th>
                            <th>Enrollments</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                        <tr>
                            <td><strong>#<?php echo $student['user_id']; ?></strong></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center me-2" 
                                         style="width: 40px; height: 40px;">
                                        <strong class="text-success"><?php echo strtoupper(substr($student['full_name'], 0, 1)); ?></strong>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($student['full_name']); ?></strong><br>
                                        <small class="text-muted">@<?php echo htmlspecialchars($student['username']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <small class="d-block">
                                    <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($student['email']); ?>
                                </small>
                                <?php if ($student['phone']): ?>
                                <small class="d-block">
                                    <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($student['phone']); ?>
                                </small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($student['branch_name']); ?></td>
                            <td>
                                <span class="badge bg-primary">
                                    Total: <?php echo $student['enrollment_count']; ?>
                                </span>
                                <span class="badge bg-success">
                                    Active: <?php echo $student['active_enrollments']; ?>
                                </span>
                                <?php if ($student['enrollment_count'] > 0): ?>
                                <a href="manage_enrollments.php?student=<?php echo $student['user_id']; ?>" 
                                   class="btn btn-sm btn-link p-0 d-block">View</a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $status_colors = [
                                    'active' => 'success',
                                    'pending' => 'warning',
                                    'inactive' => 'secondary'
                                ];
                                $color = isset($status_colors[$student['status']]) ? $status_colors[$student['status']] : 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $color; ?>">
                                    <?php echo ucfirst($student['status']); ?>
                                </span>
                            </td>
                            <td><?php echo format_date($student['created_at']); ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" 
                                        data-bs-target="#editModal<?php echo $student['user_id']; ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" 
                                        data-bs-target="#statusModal<?php echo $student['user_id']; ?>">
                                    <i class="bi bi-gear"></i>
                                </button>
                                <a href="?action=delete&id=<?php echo $student['user_id']; ?>" 
                                   class="btn btn-sm btn-outline-danger"
                                   onclick="return confirm('Are you sure you want to delete this student?')">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>

                        <!-- Edit Modal -->
                        <div class="modal fade" id="editModal<?php echo $student['user_id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header bg-primary text-white">
                                        <h5 class="modal-title">Edit Student: <?php echo htmlspecialchars($student['full_name']); ?></h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="student_id" value="<?php echo $student['user_id']; ?>">
                                            
                                            <div class="alert alert-info">
                                                <strong>Account Info:</strong><br>
                                                Username: @<?php echo htmlspecialchars($student['username']); ?><br>
                                                Registered: <?php echo format_datetime($student['created_at']); ?>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Full Name *</label>
                                                    <input type="text" name="full_name" class="form-control" required 
                                                           value="<?php echo htmlspecialchars($student['full_name']); ?>">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Email *</label>
                                                    <input type="email" name="email" class="form-control" required 
                                                           value="<?php echo htmlspecialchars($student['email']); ?>">
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Phone</label>
                                                    <input type="tel" name="phone" class="form-control" 
                                                           value="<?php echo htmlspecialchars($student['phone']); ?>">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Branch *</label>
                                                    <select name="branch_id" class="form-select" required>
                                                        <?php foreach ($branches as $branch): ?>
                                                        <option value="<?php echo $branch['branch_id']; ?>" 
                                                                <?php echo $student['branch_id'] == $branch['branch_id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($branch['branch_name']); ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Status *</label>
                                                <select name="status" class="form-select" required>
                                                    <option value="active" <?php echo $student['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                    <option value="pending" <?php echo $student['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="inactive" <?php echo $student['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="edit_student" class="btn btn-primary">
                                                <i class="bi bi-save"></i> Update Student
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Status Modal -->
                        <div class="modal fade" id="statusModal<?php echo $student['user_id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header bg-secondary text-white">
                                        <h5 class="modal-title">Change Status</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="student_id" value="<?php echo $student['user_id']; ?>">
                                            
                                            <p>Change status for <strong><?php echo htmlspecialchars($student['full_name']); ?></strong></p>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">New Status *</label>
                                                <select name="status" class="form-select" required>
                                                    <option value="active" <?php echo $student['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                    <option value="pending" <?php echo $student['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="inactive" <?php echo $student['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="update_status" class="btn btn-primary">
                                                <i class="bi bi-check"></i> Update Status
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>