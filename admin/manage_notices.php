<?php
require_once '../config.php';
require_role('admin');

$db = Database::getInstance()->getConnection();
$success = '';
$errors = [];

// Handle Add/Edit Notice
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_notice'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Invalid request';
    } else {
        $notice_id = isset($_POST['notice_id']) ? (int)$_POST['notice_id'] : 0;
        $title = sanitize_input($_POST['title']);
        $content = sanitize_input($_POST['content']);
        $category = $_POST['category'];
        $priority = $_POST['priority'];
        $branch_id = !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : NULL;
        $publish_date = $_POST['publish_date'];
        $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : NULL;
        $status = $_POST['status'];
        
        // Validation
        if (strlen($title) < 5) {
            $errors[] = 'Title must be at least 5 characters';
        }
        
        if (strlen($content) < 20) {
            $errors[] = 'Content must be at least 20 characters';
        }
        
        if (empty($publish_date)) {
            $errors[] = 'Publish date is required';
        }
        
        if (empty($errors)) {
            if ($notice_id > 0) {
                // Update existing notice
                $stmt = $db->prepare("UPDATE notices SET title = ?, content = ?, category = ?, priority = ?, 
                                      branch_id = ?, publish_date = ?, expiry_date = ?, status = ? 
                                      WHERE notice_id = ?");
                if ($stmt->execute([$title, $content, $category, $priority, $branch_id, 
                                    $publish_date, $expiry_date, $status, $notice_id])) {
                    log_activity(get_user_id(), 'NOTICE_UPDATE', "Updated notice: $title");
                    $success = 'Notice updated successfully!';
                } else {
                    $errors[] = 'Failed to update notice';
                }
            } else {
                // Insert new notice
                $stmt = $db->prepare("INSERT INTO notices (title, content, category, priority, branch_id, 
                                      published_by, publish_date, expiry_date, status) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$title, $content, $category, $priority, $branch_id, 
                                    get_user_id(), $publish_date, $expiry_date, $status])) {
                    log_activity(get_user_id(), 'NOTICE_CREATE', "Created notice: $title");
                    $success = 'Notice created successfully!';
                } else {
                    $errors[] = 'Failed to create notice';
                }
            }
        }
    }
}

// Handle Delete
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $notice_id = (int)$_GET['id'];
    $stmt = $db->prepare("DELETE FROM notices WHERE notice_id = ?");
    if ($stmt->execute([$notice_id])) {
        log_activity(get_user_id(), 'NOTICE_DELETE', "Deleted notice ID: $notice_id");
        $success = 'Notice deleted successfully!';
    } else {
        $errors[] = 'Failed to delete notice';
    }
}

// Handle Archive
if (isset($_GET['action']) && $_GET['action'] == 'archive' && isset($_GET['id'])) {
    $notice_id = (int)$_GET['id'];
    $stmt = $db->prepare("UPDATE notices SET status = 'archived' WHERE notice_id = ?");
    if ($stmt->execute([$notice_id])) {
        log_activity(get_user_id(), 'NOTICE_ARCHIVE', "Archived notice ID: $notice_id");
        $success = 'Notice archived successfully!';
    } else {
        $errors[] = 'Failed to archive notice';
    }
}

// Get filter parameters
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query
$query = "SELECT n.*, b.branch_name, u.full_name as published_by_name
          FROM notices n
          LEFT JOIN branches b ON n.branch_id = b.branch_id
          LEFT JOIN users u ON n.published_by = u.user_id
          WHERE 1=1";

$params = [];

if ($category_filter) {
    $query .= " AND n.category = ?";
    $params[] = $category_filter;
}

if ($status_filter) {
    $query .= " AND n.status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $query .= " AND (n.title LIKE ? OR n.content LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY n.priority DESC, n.publish_date DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$notices = $stmt->fetchAll();

// Get branches for dropdown
$branches = get_branches();

// Get statistics
$stats = [];
$stmt = $db->query("SELECT COUNT(*) as count FROM notices WHERE status = 'published'");
$stats['published'] = $stmt->fetch()['count'];
$stmt = $db->query("SELECT COUNT(*) as count FROM notices WHERE status = 'draft'");
$stats['draft'] = $stmt->fetch()['count'];
$stmt = $db->query("SELECT COUNT(*) as count FROM notices WHERE status = 'archived'");
$stats['archived'] = $stmt->fetch()['count'];
$stmt = $db->query("SELECT COUNT(*) as count FROM notices WHERE status = 'published' AND expiry_date < CURDATE()");
$stats['expired'] = $stmt->fetch()['count'];

$page_title = 'Manage Notices - Admin Panel';
include '../includes/header.php';
?>

<div class="container-fluid mt-4 mb-5">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Manage Notices</li>
                </ol>
            </nav>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">
                    <i class="bi bi-megaphone-fill text-warning"></i> Manage Notices
                </h2>
                <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#noticeModal">
                    <i class="bi bi-plus-circle"></i> Add New Notice
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

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-success border-2">
                <div class="card-body text-center">
                    <i class="bi bi-check-circle fs-1 text-success"></i>
                    <h3 class="mt-2 mb-1"><?php echo $stats['published']; ?></h3>
                    <small class="text-muted">Published</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-secondary border-2">
                <div class="card-body text-center">
                    <i class="bi bi-file-earmark fs-1 text-secondary"></i>
                    <h3 class="mt-2 mb-1"><?php echo $stats['draft']; ?></h3>
                    <small class="text-muted">Draft</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-warning border-2">
                <div class="card-body text-center">
                    <i class="bi bi-archive fs-1 text-warning"></i>
                    <h3 class="mt-2 mb-1"><?php echo $stats['archived']; ?></h3>
                    <small class="text-muted">Archived</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-danger border-2">
                <div class="card-body text-center">
                    <i class="bi bi-clock-history fs-1 text-danger"></i>
                    <h3 class="mt-2 mb-1"><?php echo $stats['expired']; ?></h3>
                    <small class="text-muted">Expired</small>
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
                    <input type="text" name="search" class="form-control" placeholder="Search title or content..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <option value="general" <?php echo $category_filter == 'general' ? 'selected' : ''; ?>>General</option>
                        <option value="holiday" <?php echo $category_filter == 'holiday' ? 'selected' : ''; ?>>Holiday</option>
                        <option value="exam" <?php echo $category_filter == 'exam' ? 'selected' : ''; ?>>Exam</option>
                        <option value="seminar" <?php echo $category_filter == 'seminar' ? 'selected' : ''; ?>>Seminar</option>
                        <option value="job_fair" <?php echo $category_filter == 'job_fair' ? 'selected' : ''; ?>>Job Fair</option>
                        <option value="urgent" <?php echo $category_filter == 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="draft" <?php echo $status_filter == 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="published" <?php echo $status_filter == 'published' ? 'selected' : ''; ?>>Published</option>
                        <option value="archived" <?php echo $status_filter == 'archived' ? 'selected' : ''; ?>>Archived</option>
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

    <!-- Notices List -->
    <?php if (empty($notices)): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-megaphone fs-1 text-muted"></i>
            <p class="text-muted mt-3">No notices found</p>
            <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#noticeModal">
                <i class="bi bi-plus-circle"></i> Create First Notice
            </button>
        </div>
    </div>
    <?php else: ?>
    <div class="row">
        <?php 
        $priority_colors = ['high' => 'danger', 'medium' => 'warning', 'low' => 'secondary'];
        $status_colors = ['draft' => 'secondary', 'published' => 'success', 'archived' => 'warning'];
        $category_colors = [
            'general' => 'primary',
            'holiday' => 'info',
            'exam' => 'danger',
            'seminar' => 'success',
            'job_fair' => 'warning',
            'urgent' => 'danger'
        ];
        
        foreach ($notices as $notice): 
            $is_expired = $notice['expiry_date'] && strtotime($notice['expiry_date']) < time();
        ?>
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm h-100 <?php echo $is_expired ? 'border-danger' : ''; ?>">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <h6 class="mb-2"><?php echo htmlspecialchars($notice['title']); ?></h6>
                            <div class="d-flex gap-2 flex-wrap">
                                <span class="badge bg-<?php echo $category_colors[$notice['category']]; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $notice['category'])); ?>
                                </span>
                                <span class="badge bg-<?php echo $priority_colors[$notice['priority']]; ?>">
                                    <?php echo ucfirst($notice['priority']); ?> Priority
                                </span>
                                <span class="badge bg-<?php echo $status_colors[$notice['status']]; ?>">
                                    <?php echo ucfirst($notice['status']); ?>
                                </span>
                                <?php if ($is_expired): ?>
                                <span class="badge bg-danger">Expired</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <p class="card-text"><?php echo nl2br(htmlspecialchars($notice['content'])); ?></p>
                    
                    <hr>
                    
                    <div class="row g-2 small text-muted">
                        <div class="col-6">
                            <i class="bi bi-calendar"></i> Publish: <?php echo format_date($notice['publish_date']); ?>
                        </div>
                        <?php if ($notice['expiry_date']): ?>
                        <div class="col-6">
                            <i class="bi bi-calendar-x"></i> Expires: <?php echo format_date($notice['expiry_date']); ?>
                        </div>
                        <?php endif; ?>
                        <div class="col-6">
                            <i class="bi bi-person"></i> By: <?php echo htmlspecialchars($notice['published_by_name']); ?>
                        </div>
                        <div class="col-6">
                            <i class="bi bi-building"></i> 
                            <?php echo $notice['branch_name'] ? htmlspecialchars($notice['branch_name']) : 'All Branches'; ?>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" 
                                data-bs-target="#editModal<?php echo $notice['notice_id']; ?>">
                            <i class="bi bi-pencil"></i> Edit
                        </button>
                        <?php if ($notice['status'] == 'published'): ?>
                        <a href="?action=archive&id=<?php echo $notice['notice_id']; ?>" 
                           class="btn btn-sm btn-outline-warning">
                            <i class="bi bi-archive"></i> Archive
                        </a>
                        <?php endif; ?>
                        <a href="?action=delete&id=<?php echo $notice['notice_id']; ?>" 
                           class="btn btn-sm btn-outline-danger ms-auto"
                           onclick="return confirm('Are you sure you want to delete this notice?')">
                            <i class="bi bi-trash"></i> Delete
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <div class="modal fade" id="editModal<?php echo $notice['notice_id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Edit Notice</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="notice_id" value="<?php echo $notice['notice_id']; ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Title *</label>
                                <input type="text" name="title" class="form-control" required 
                                       value="<?php echo htmlspecialchars($notice['title']); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Content *</label>
                                <textarea name="content" class="form-control" rows="5" required><?php echo htmlspecialchars($notice['content']); ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Category *</label>
                                    <select name="category" class="form-select" required>
                                        <option value="general" <?php echo $notice['category'] == 'general' ? 'selected' : ''; ?>>General</option>
                                        <option value="holiday" <?php echo $notice['category'] == 'holiday' ? 'selected' : ''; ?>>Holiday</option>
                                        <option value="exam" <?php echo $notice['category'] == 'exam' ? 'selected' : ''; ?>>Exam</option>
                                        <option value="seminar" <?php echo $notice['category'] == 'seminar' ? 'selected' : ''; ?>>Seminar</option>
                                        <option value="job_fair" <?php echo $notice['category'] == 'job_fair' ? 'selected' : ''; ?>>Job Fair</option>
                                        <option value="urgent" <?php echo $notice['category'] == 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Priority *</label>
                                    <select name="priority" class="form-select" required>
                                        <option value="low" <?php echo $notice['priority'] == 'low' ? 'selected' : ''; ?>>Low</option>
                                        <option value="medium" <?php echo $notice['priority'] == 'medium' ? 'selected' : ''; ?>>Medium</option>
                                        <option value="high" <?php echo $notice['priority'] == 'high' ? 'selected' : ''; ?>>High</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Status *</label>
                                    <select name="status" class="form-select" required>
                                        <option value="draft" <?php echo $notice['status'] == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                        <option value="published" <?php echo $notice['status'] == 'published' ? 'selected' : ''; ?>>Published</option>
                                        <option value="archived" <?php echo $notice['status'] == 'archived' ? 'selected' : ''; ?>>Archived</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Publish Date *</label>
                                    <input type="date" name="publish_date" class="form-control" required 
                                           value="<?php echo $notice['publish_date']; ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Expiry Date</label>
                                    <input type="date" name="expiry_date" class="form-control" 
                                           value="<?php echo $notice['expiry_date']; ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Branch</label>
                                    <select name="branch_id" class="form-select">
                                        <option value="">All Branches</option>
                                        <?php foreach ($branches as $branch): ?>
                                        <option value="<?php echo $branch['branch_id']; ?>" 
                                                <?php echo $notice['branch_id'] == $branch['branch_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($branch['branch_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="save_notice" class="btn btn-primary">
                                <i class="bi bi-save"></i> Update Notice
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Add Notice Modal -->
<div class="modal fade" id="noticeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">Add New Notice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-control" required 
                               placeholder="Enter notice title">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Content *</label>
                        <textarea name="content" class="form-control" rows="5" required 
                                  placeholder="Enter notice content"></textarea>
                        <small class="text-muted">Minimum 20 characters</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Category *</label>
                            <select name="category" class="form-select" required>
                                <option value="general">General</option>
                                <option value="holiday">Holiday</option>
                                <option value="exam">Exam</option>
                                <option value="seminar">Seminar</option>
                                <option value="job_fair">Job Fair</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Priority *</label>
                            <select name="priority" class="form-select" required>
                                <option value="medium">Medium</option>
                                <option value="low">Low</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status *</label>
                            <select name="status" class="form-select" required>
                                <option value="published">Published</option>
                                <option value="draft">Draft</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Publish Date *</label>
                            <input type="date" name="publish_date" class="form-control" required 
                                   value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" name="expiry_date" class="form-control">
                            <small class="text-muted">Optional</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Branch</label>
                            <select name="branch_id" class="form-select">
                                <option value="">All Branches</option>
                                <?php foreach ($branches as $branch): ?>
                                <option value="<?php echo $branch['branch_id']; ?>">
                                    <?php echo htmlspecialchars($branch['branch_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_notice" class="btn btn-warning">
                        <i class="bi bi-plus-circle"></i> Create Notice
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>