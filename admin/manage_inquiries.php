<?php
require_once '../config.php';
require_role('admin');

$db = Database::getInstance()->getConnection();
$success = '';
$errors = [];

// Handle Respond to Inquiry
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['respond_inquiry'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Invalid request';
    } else {
        $inquiry_id = (int)$_POST['inquiry_id'];
        $response = sanitize_input($_POST['response']);
        $status = $_POST['status'];
        
        if (strlen($response) < 10) {
            $errors[] = 'Response must be at least 10 characters';
        }
        
        if (empty($errors)) {
            $stmt = $db->prepare("UPDATE inquiries SET response = ?, status = ?, responded_by = ?, responded_at = NOW() 
                                  WHERE inquiry_id = ?");
            if ($stmt->execute([$response, $status, get_user_id(), $inquiry_id])) {
                log_activity(get_user_id(), 'INQUIRY_RESPOND', "Responded to inquiry ID: $inquiry_id");
                $success = 'Response submitted successfully!';
                
                // In a real application, you would send an email to the inquirer here
                // send_email($inquiry['email'], 'Response to Your Inquiry', $response);
            } else {
                $errors[] = 'Failed to submit response';
            }
        }
    }
}

// Handle Update Status
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Invalid request';
    } else {
        $inquiry_id = (int)$_POST['inquiry_id'];
        $status = $_POST['status'];
        
        $stmt = $db->prepare("UPDATE inquiries SET status = ? WHERE inquiry_id = ?");
        if ($stmt->execute([$status, $inquiry_id])) {
            log_activity(get_user_id(), 'INQUIRY_STATUS_UPDATE', "Updated inquiry ID: $inquiry_id status to: $status");
            $success = 'Status updated successfully!';
        } else {
            $errors[] = 'Failed to update status';
        }
    }
}

// Handle Delete
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $inquiry_id = (int)$_GET['id'];
    $stmt = $db->prepare("DELETE FROM inquiries WHERE inquiry_id = ?");
    if ($stmt->execute([$inquiry_id])) {
        log_activity(get_user_id(), 'INQUIRY_DELETE', "Deleted inquiry ID: $inquiry_id");
        $success = 'Inquiry deleted successfully!';
    } else {
        $errors[] = 'Failed to delete inquiry';
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query
$query = "SELECT i.*, u.full_name as responded_by_name
          FROM inquiries i
          LEFT JOIN users u ON i.responded_by = u.user_id
          WHERE 1=1";

$params = [];

if ($status_filter) {
    $query .= " AND i.status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $query .= " AND (i.name LIKE ? OR i.email LIKE ? OR i.subject LIKE ? OR i.message LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY i.submitted_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$inquiries = $stmt->fetchAll();

// Get statistics
$stats = [];
$stmt = $db->query("SELECT COUNT(*) as count FROM inquiries WHERE status = 'new'");
$stats['new'] = $stmt->fetch()['count'];
$stmt = $db->query("SELECT COUNT(*) as count FROM inquiries WHERE status = 'in_progress'");
$stats['in_progress'] = $stmt->fetch()['count'];
$stmt = $db->query("SELECT COUNT(*) as count FROM inquiries WHERE status = 'resolved'");
$stats['resolved'] = $stmt->fetch()['count'];

$page_title = 'Manage Inquiries - Admin Panel';
include '../includes/header.php';
?>

<div class="container-fluid mt-4 mb-5">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Manage Inquiries</li>
                </ol>
            </nav>

            <h2 class="mb-4">
                <i class="bi bi-chat-dots-fill text-danger"></i> Manage Inquiries
            </h2>

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
        <div class="col-md-4 mb-3">
            <div class="card border-danger border-2">
                <div class="card-body text-center">
                    <i class="bi bi-exclamation-circle fs-1 text-danger"></i>
                    <h3 class="mt-2 mb-1"><?php echo $stats['new']; ?></h3>
                    <small class="text-muted">New Inquiries</small>
                    <?php if ($stats['new'] > 0): ?>
                    <div class="mt-2">
                        <a href="?status=new" class="btn btn-sm btn-danger">Review Now</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card border-warning border-2">
                <div class="card-body text-center">
                    <i class="bi bi-hourglass-split fs-1 text-warning"></i>
                    <h3 class="mt-2 mb-1"><?php echo $stats['in_progress']; ?></h3>
                    <small class="text-muted">In Progress</small>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card border-success border-2">
                <div class="card-body text-center">
                    <i class="bi bi-check-circle fs-1 text-success"></i>
                    <h3 class="mt-2 mb-1"><?php echo $stats['resolved']; ?></h3>
                    <small class="text-muted">Resolved</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search by name, email, subject, or message..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="new" <?php echo $status_filter == 'new' ? 'selected' : ''; ?>>New</option>
                        <option value="in_progress" <?php echo $status_filter == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="resolved" <?php echo $status_filter == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
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

    <!-- Inquiries List -->
    <?php if (empty($inquiries)): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-inbox fs-1 text-muted"></i>
            <p class="text-muted mt-3">No inquiries found</p>
        </div>
    </div>
    <?php else: ?>
    <div class="row">
        <?php 
        $status_colors = [
            'new' => 'danger',
            'in_progress' => 'warning',
            'resolved' => 'success'
        ];
        
        foreach ($inquiries as $inquiry): 
            $color = $status_colors[$inquiry['status']];
        ?>
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm h-100 border-<?php echo $color; ?> border-start border-5">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-1">
                                <i class="bi bi-person-circle"></i> 
                                <?php echo htmlspecialchars($inquiry['name']); ?>
                            </h6>
                            <small class="text-muted">
                                <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($inquiry['email']); ?>
                            </small>
                            <?php if ($inquiry['phone']): ?>
                            <br><small class="text-muted">
                                <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($inquiry['phone']); ?>
                            </small>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span class="badge bg-<?php echo $color; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $inquiry['status'])); ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <h6 class="text-primary mb-2">
                        <i class="bi bi-chat-square-quote"></i> 
                        <?php echo htmlspecialchars($inquiry['subject']); ?>
                    </h6>
                    
                    <div class="alert alert-light border mb-3">
                        <strong>Message:</strong><br>
                        <?php echo nl2br(htmlspecialchars($inquiry['message'])); ?>
                    </div>
                    
                    <small class="text-muted">
                        <i class="bi bi-clock"></i> 
                        Submitted: <?php echo format_datetime($inquiry['submitted_at']); ?>
                    </small>
                    
                    <?php if ($inquiry['response']): ?>
                    <hr>
                    <div class="alert alert-success mb-0">
                        <strong><i class="bi bi-reply-fill"></i> Response:</strong><br>
                        <?php echo nl2br(htmlspecialchars($inquiry['response'])); ?>
                        <hr class="my-2">
                        <small class="text-muted">
                            <i class="bi bi-person"></i> By: <?php echo htmlspecialchars($inquiry['responded_by_name']); ?> | 
                            <i class="bi bi-clock"></i> <?php echo format_datetime($inquiry['responded_at']); ?>
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-white">
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" 
                                data-bs-target="#respondModal<?php echo $inquiry['inquiry_id']; ?>">
                            <i class="bi bi-reply-fill"></i> 
                            <?php echo $inquiry['response'] ? 'Update Response' : 'Respond'; ?>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" 
                                data-bs-target="#statusModal<?php echo $inquiry['inquiry_id']; ?>">
                            <i class="bi bi-gear"></i> Change Status
                        </button>
                        <a href="?action=delete&id=<?php echo $inquiry['inquiry_id']; ?>" 
                           class="btn btn-sm btn-outline-danger ms-auto"
                           onclick="return confirm('Are you sure you want to delete this inquiry?')">
                            <i class="bi bi-trash"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Respond Modal -->
        <div class="modal fade" id="respondModal<?php echo $inquiry['inquiry_id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Respond to Inquiry</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="inquiry_id" value="<?php echo $inquiry['inquiry_id']; ?>">
                            
                            <div class="alert alert-light border">
                                <h6>Inquiry Details:</h6>
                                <p class="mb-1"><strong>From:</strong> <?php echo htmlspecialchars($inquiry['name']); ?> (<?php echo htmlspecialchars($inquiry['email']); ?>)</p>
                                <p class="mb-1"><strong>Subject:</strong> <?php echo htmlspecialchars($inquiry['subject']); ?></p>
                                <p class="mb-0"><strong>Message:</strong> <?php echo nl2br(htmlspecialchars($inquiry['message'])); ?></p>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Your Response *</label>
                                <textarea name="response" class="form-control" rows="6" required><?php echo htmlspecialchars($inquiry['response']); ?></textarea>
                                <small class="text-muted">This response will be sent to the inquirer via email</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Update Status *</label>
                                <select name="status" class="form-select" required>
                                    <option value="in_progress" <?php echo $inquiry['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="resolved" <?php echo $inquiry['status'] == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="respond_inquiry" class="btn btn-primary">
                                <i class="bi bi-send"></i> Send Response
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Status Modal -->
        <div class="modal fade" id="statusModal<?php echo $inquiry['inquiry_id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-secondary text-white">
                        <h5 class="modal-title">Change Status</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="inquiry_id" value="<?php echo $inquiry['inquiry_id']; ?>">
                            
                            <p>Change status for inquiry from <strong><?php echo htmlspecialchars($inquiry['name']); ?></strong></p>
                            
                            <div class="mb-3">
                                <label class="form-label">New Status *</label>
                                <select name="status" class="form-select" required>
                                    <option value="new" <?php echo $inquiry['status'] == 'new' ? 'selected' : ''; ?>>New</option>
                                    <option value="in_progress" <?php echo $inquiry['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="resolved" <?php echo $inquiry['status'] == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
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
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>