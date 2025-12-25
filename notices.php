<?php
require_once 'config.php';

$db = Database::getInstance()->getConnection();

// Get filter parameters
$category_filter = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT n.*, u.full_name as published_by_name, b.branch_name 
          FROM notices n
          LEFT JOIN users u ON n.published_by = u.user_id
          LEFT JOIN branches b ON n.branch_id = b.branch_id
          WHERE n.status = 'published' 
          AND (n.expiry_date IS NULL OR n.expiry_date >= CURDATE())";

$params = [];

if ($category_filter) {
    $query .= " AND n.category = ?";
    $params[] = $category_filter;
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

$page_title = 'Notice Board - SkillPro Institute';
include 'includes/header.php';
?>

<div class="container mt-5 mb-5">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-0">
                        <i class="bi bi-megaphone-fill text-primary"></i> Notice Board
                    </h2>
                    <p class="text-muted mb-0">Stay updated with latest announcements</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search notices..." 
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-4">
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <option value="general" <?= $category_filter == 'general' ? 'selected' : '' ?>>General</option>
                        <option value="holiday" <?= $category_filter == 'holiday' ? 'selected' : '' ?>>Holiday</option>
                        <option value="exam" <?= $category_filter == 'exam' ? 'selected' : '' ?>>Exam</option>
                        <option value="seminar" <?= $category_filter == 'seminar' ? 'selected' : '' ?>>Seminar</option>
                        <option value="job_fair" <?= $category_filter == 'job_fair' ? 'selected' : '' ?>>Job Fair</option>
                        <option value="urgent" <?= $category_filter == 'urgent' ? 'selected' : '' ?>>Urgent</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Search
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Notices List -->
    <div class="row">
        <?php if (empty($notices)): ?>
        <div class="col-12">
            <div class="alert alert-info text-center">
                <i class="bi bi-info-circle fs-3 d-block mb-2"></i>
                <h5>No notices found</h5>
                <p class="mb-0">There are currently no notices matching your criteria.</p>
            </div>
        </div>
        <?php else: ?>
        <?php foreach ($notices as $notice): ?>
        <div class="col-lg-6 mb-4">
            <div class="card h-100 shadow-sm border-0">
                <!-- Priority Badge -->
                <?php
                $priority_colors = [
                    'high' => 'danger',
                    'medium' => 'warning',
                    'low' => 'secondary'
                ];
                $category_colors = [
                    'general' => 'primary',
                    'holiday' => 'info',
                    'exam' => 'danger',
                    'seminar' => 'success',
                    'job_fair' => 'warning',
                    'urgent' => 'danger'
                ];
                ?>
                
                <div class="card-header bg-white border-0 pt-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="badge bg-<?= $category_colors[$notice['category']] ?? 'primary' ?>">
                                <?= ucfirst(str_replace('_', ' ', $notice['category'])) ?>
                            </span>
                        </div>
                        <span class="badge bg-<?= $priority_colors[$notice['priority']] ?>">
                            <?= ucfirst($notice['priority']) ?> Priority
                        </span>
                    </div>
                </div>
                
                <div class="card-body">
                    <h5 class="card-title mb-3">
                        <?php if ($notice['priority'] == 'high'): ?>
                        <i class="bi bi-exclamation-triangle-fill text-danger"></i>
                        <?php endif; ?>
                        <?= htmlspecialchars($notice['title']) ?>
                    </h5>
                    
                    <p class="card-text text-muted">
                        <?= nl2br(htmlspecialchars($notice['content'])) ?>
                    </p>
                </div>
                
                <div class="card-footer bg-light border-0">
                    <div class="row g-0">
                        <div class="col-6">
                            <small class="text-muted">
                                <i class="bi bi-calendar3"></i> 
                                <?= format_date($notice['publish_date']) ?>
                            </small>
                        </div>
                        <div class="col-6 text-end">
                            <?php if ($notice['branch_name']): ?>
                            <small class="text-muted">
                                <i class="bi bi-geo-alt-fill"></i> 
                                <?= htmlspecialchars($notice['branch_name']) ?>
                            </small>
                            <?php else: ?>
                            <small class="text-muted">
                                <i class="bi bi-geo-alt-fill"></i> All Branches
                            </small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($notice['expiry_date']): ?>
                    <div class="mt-2">
                        <small class="text-danger">
                            <i class="bi bi-clock-fill"></i> 
                            Valid until <?= format_date($notice['expiry_date']) ?>
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Important Notice Info -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="alert alert-light border">
                <h6 class="alert-heading">
                    <i class="bi bi-info-circle-fill text-primary"></i> 
                    Notice Information
                </h6>
                <p class="mb-0">
                    <strong>Stay Informed:</strong> Check this notice board regularly for important updates about courses, exams, holidays, and events. 
                    For urgent matters, notices will also be sent via email to registered students.
                </p>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>