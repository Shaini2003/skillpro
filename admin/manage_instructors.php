<?php
require_once '../config.php';
require_role('admin');

$db = Database::getInstance()->getConnection();
$success = '';
$errors = [];

// Handle Add/Edit Instructor
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_instructor'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Invalid request';
    } else {
        $instructor_id = isset($_POST['instructor_id']) ? (int)$_POST['instructor_id'] : 0;
        $username = sanitize_input($_POST['username']);
        $email = sanitize_input($_POST['email']);
        $full_name = sanitize_input($_POST['full_name']);
        $phone = sanitize_input($_POST['phone']);
        $branch_id = (int)$_POST['branch_id'];
        $password = $_POST['password'];
        $status = $_POST['status'];
        
        // Validation
        if (strlen($username) < 4) {
            $errors[] = 'Username must be at least 4 characters';
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address';
        }
        
        if (strlen($full_name) < 3) {
            $errors[] = 'Full name must be at least 3 characters';
        }
        
        if ($instructor_id == 0 && strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters';
        }
        
        if (empty($errors)) {
            if ($instructor_id > 0) {
                // Update existing instructor
                // Check if username/email exists for other users
                $stmt = $db->prepare("SELECT user_id FROM users WHERE (username = ? OR email = ?) AND user_id != ?");
                $stmt->execute([$username, $email, $instructor_id]);
                
                if ($stmt->fetch()) {
                    $errors[] = 'Username or email already exists';
                } else {
                    if (!empty($password)) {
                        // Update with new password
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, phone = ?, 
                                              branch_id = ?, password_hash = ?, status = ? WHERE user_id = ?");
                        $result = $stmt->execute([$username, $email, $full_name, $phone, $branch_id, 
                                                  $password_hash, $status, $instructor_id]);
                    } else {
                        // Update without changing password
                        $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, phone = ?, 
                                              branch_id = ?, status = ? WHERE user_id = ?");
                        $result = $stmt->execute([$username, $email, $full_name, $phone, $branch_id, 
                                                  $status, $instructor_id]);
                    }
                    
                    if ($result) {
                        log_activity(get_user_id(), 'INSTRUCTOR_UPDATE', "Updated instructor: $full_name");
                        $success = 'Instructor updated successfully!';
                    } else {
                        $errors[] = 'Failed to update instructor';
                    }
                }
            } else {
                // Insert new instructor
                // Check if username/email exists
                $stmt = $db->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                
                if ($stmt->fetch()) {
                    $errors[] = 'Username or email already exists';
                } else {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO users (username, password_hash, email, full_name, phone, 
                                          role, branch_id, status) VALUES (?, ?, ?, ?, ?, 'instructor', ?, ?)");
                    
                    if ($stmt->execute([$username, $password_hash, $email, $full_name, $phone, $branch_id, $status])) {
                        log_activity(get_user_id(), 'INSTRUCTOR_CREATE', "Created instructor: $full_name");
                        $success = 'Instructor created successfully!';
                    } else {
                        $errors[] = 'Failed to create instructor';
                    }
                }
            }
        }
    }
}

// Handle Delete
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $instructor_id = (int)$_GET['id'];
    
    // Check if instructor has assigned courses
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM courses WHERE instructor_id = ?");
    $stmt->execute([$instructor_id]);
    $course_count = $stmt->fetch()['count'];
    
    if ($course_count > 0) {
        $errors[] = "Cannot delete instructor. They are assigned to $course_count course(s). Please reassign courses first.";
    } else {
        $stmt = $db->prepare("DELETE FROM users WHERE user_id = ? AND role = 'instructor'");
        if ($stmt->execute([$instructor_id])) {
            log_activity(get_user_id(), 'INSTRUCTOR_DELETE', "Deleted instructor ID: $instructor_id");
            $success = 'Instructor deleted successfully!';
        } else {
            $errors[] = 'Failed to delete instructor';
        }
    }
}

// Get filter parameters
$branch_filter = isset($_GET['branch']) ? $_GET['branch'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query
$query = "SELECT u.*, b.branch_name,
          (SELECT COUNT(*) FROM courses WHERE instructor_id = u.user_id) as course_count
          FROM users u
          LEFT JOIN branches b ON u.branch_id = b.branch_id
          WHERE u.role = 'instructor'";

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
$instructors = $stmt->fetchAll();

// Get branches for dropdown
$branches = get_branches();

// Get statistics
$stats = [];
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'instructor' AND status = 'active'");
$stats['active'] = $stmt->fetch()['count'];
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'instructor' AND status = 'inactive'");
$stats['inactive'] = $stmt->fetch()['count'];
$stmt = $db->query("SELECT COUNT(*) as count FROM courses WHERE instructor_id IS NOT NULL");
$stats['assigned_courses'] = $stmt->fetch()['count'];

$page_title = 'Manage Instructors - Admin Panel';
include '../includes/header.php';
?>

<style>
    body.modal-open { overflow-y: scroll !important; padding-right: 0 !important; }
</style>

<div class="container-fluid mt-4 mb-5">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Manage Instructors</li>
                </ol>
            </nav>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">
                    <i class="bi bi-person-badge-fill text-info"></i> Manage Instructors
                </h2>
                <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#instructorModal">
                    <i class="bi bi-plus-circle"></i> Add New Instructor
                </button>
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

    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card border-success border-2">
                <div class="card-body text-center">
                    <i class="bi bi-person-check fs-1 text-success"></i>
                    <h3 class="mt-2 mb-1"><?php echo $stats['active']; ?></h3>
                    <small class="text-muted">Active Instructors</small>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card border-secondary border-2">
                <div class="card-body text-center">
                    <i class="bi bi-person-x fs-1 text-secondary"></i>
                    <h3 class="mt-2 mb-1"><?php echo $stats['inactive']; ?></h3>
                    <small class="text-muted">Inactive Instructors</small>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card border-primary border-2">
                <div class="card-body text-center">
                    <i class="bi bi-book fs-1 text-primary"></i>
                    <h3 class="mt-2 mb-1"><?php echo $stats['assigned_courses']; ?></h3>
                    <small class="text-muted">Courses Assigned</small>
                </div>
            </div>
        </div>
    </div>

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

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0">Instructors List (<?php echo count($instructors); ?> instructors)</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($instructors)): ?>
            <div class="text-center py-5">
                <i class="bi bi-person-x fs-1 text-muted"></i>
                <p class="text-muted mt-3">No instructors found</p>
                <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#instructorModal">
                    <i class="bi bi-plus-circle"></i> Add First Instructor
                </button>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Instructor</th>
                            <th>Contact</th>
                            <th>Branch</th>
                            <th>Courses</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($instructors as $instructor): ?>
                        <tr>
                            <td><strong>#<?php echo $instructor['user_id']; ?></strong></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-info bg-opacity-10 d-flex align-items-center justify-content-center me-2" 
                                         style="width: 40px; height: 40px;">
                                        <strong class="text-info"><?php echo strtoupper(substr($instructor['full_name'], 0, 1)); ?></strong>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($instructor['full_name']); ?></strong><br>
                                        <small class="text-muted">@<?php echo htmlspecialchars($instructor['username']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <small class="d-block">
                                    <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($instructor['email']); ?>
                                </small>
                                <?php if ($instructor['phone']): ?>
                                <small class="d-block">
                                    <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($instructor['phone']); ?>
                                </small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($instructor['branch_name'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="badge bg-primary">
                                    <?php echo $instructor['course_count']; ?> Courses
                                </span>
                                <?php if ($instructor['course_count'] > 0): ?>
                                <a href="manage_courses.php?instructor=<?php echo $instructor['user_id']; ?>" 
                                   class="btn btn-sm btn-link p-0">View</a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $instructor['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($instructor['status']); ?>
                                </span>
                            </td>
                            <td><?php echo format_date($instructor['created_at']); ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" 
                                        data-bs-target="#editModal<?php echo $instructor['user_id']; ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <a href="?action=delete&id=<?php echo $instructor['user_id']; ?>" 
                                   class="btn btn-sm btn-outline-danger"
                                   onclick="return confirm('Are you sure you want to delete this instructor?')">
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

<?php if (!empty($instructors)): ?>
    <?php foreach ($instructors as $instructor): ?>
    <div class="modal fade" id="editModal<?php echo $instructor['user_id']; ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Edit Instructor: <?php echo htmlspecialchars($instructor['full_name']); ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="instructor_id" value="<?php echo $instructor['user_id']; ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username *</label>
                                <input type="text" name="username" class="form-control" required 
                                       value="<?php echo htmlspecialchars($instructor['username']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" name="email" class="form-control" required 
                                       value="<?php echo htmlspecialchars($instructor['email']); ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" name="full_name" class="form-control" required 
                                       value="<?php echo htmlspecialchars($instructor['full_name']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" name="phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($instructor['phone'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Branch *</label>
                                <select name="branch_id" class="form-select" required>
                                    <?php foreach ($branches as $branch): ?>
                                    <option value="<?php echo $branch['branch_id']; ?>" 
                                            <?php echo $instructor['branch_id'] == $branch['branch_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($branch['branch_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status *</label>
                                <select name="status" class="form-select" required>
                                    <option value="active" <?php echo $instructor['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $instructor['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" name="password" class="form-control">
                            <small class="text-muted">Leave blank to keep current password</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="save_instructor" class="btn btn-primary">
                            <i class="bi bi-save"></i> Update Instructor
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="modal fade" id="instructorModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Add New Instructor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username *</label>
                            <input type="text" name="username" class="form-control" required 
                                   placeholder="Enter username">
                            <small class="text-muted">Minimum 4 characters</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" required 
                                   placeholder="instructor@email.com">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="full_name" class="form-control" required 
                                   placeholder="Enter full name">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-control" 
                                   placeholder="07XXXXXXXX">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Branch *</label>
                            <select name="branch_id" class="form-select" required>
                                <option value="">Select Branch</option>
                                <?php foreach ($branches as $branch): ?>
                                <option value="<?php echo $branch['branch_id']; ?>">
                                    <?php echo htmlspecialchars($branch['branch_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status *</label>
                            <select name="status" class="form-select" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control" required>
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_instructor" class="btn btn-info text-white">
                        <i class="bi bi-plus-circle"></i> Create Instructor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>