<?php
require_once 'config.php';

$db = Database::getInstance()->getConnection();

// Get filter parameters
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$branch_filter = $_GET['branch'] ?? '';
$mode_filter = $_GET['mode'] ?? '';

// Build query
$query = "SELECT c.*, cat.category_name, b.branch_name, u.full_name as instructor_name 
          FROM courses c 
          LEFT JOIN categories cat ON c.category_id = cat.category_id
          LEFT JOIN branches b ON c.branch_id = b.branch_id
          LEFT JOIN users u ON c.instructor_id = u.user_id
          WHERE c.status = 'active'";

$params = [];

if ($search) {
    $query .= " AND (c.course_name LIKE ? OR c.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_filter) {
    $query .= " AND c.category_id = ?";
    $params[] = $category_filter;
}

if ($branch_filter) {
    $query .= " AND c.branch_id = ?";
    $params[] = $branch_filter;
}

if ($mode_filter) {
    $query .= " AND c.mode = ?";
    $params[] = $mode_filter;
}

$query .= " ORDER BY c.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$courses = $stmt->fetchAll();

$categories = get_categories();
$branches = get_branches();

include 'includes/header.php';
?>

<div class="container mt-5 mb-5">
    <h2 class="mb-4">Browse Courses</h2>
    
    <!-- Search and Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control" placeholder="Search courses..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['category_id'] ?>" <?= $category_filter == $cat['category_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['category_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="branch" class="form-select">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $branch): ?>
                        <option value="<?= $branch['branch_id'] ?>" <?= $branch_filter == $branch['branch_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($branch['branch_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="mode" class="form-select">
                        <option value="">All Modes</option>
                        <option value="online" <?= $mode_filter == 'online' ? 'selected' : '' ?>>Online</option>
                        <option value="onsite" <?= $mode_filter == 'onsite' ? 'selected' : '' ?>>On-site</option>
                        <option value="hybrid" <?= $mode_filter == 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary me-2">Search</button>
                    <a href="courses.php" class="btn btn-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Course Results -->
    <div class="row">
        <?php if (empty($courses)): ?>
        <div class="col-12">
            <div class="alert alert-info">No courses found matching your criteria.</div>
        </div>
        <?php else: ?>
        <?php foreach ($courses as $course): ?>
        <div class="col-md-4 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-3">
                        <span class="badge bg-primary"><?= htmlspecialchars($course['category_name'] ?? 'General') ?></span>
                        <span class="badge bg-info"><?= ucfirst(htmlspecialchars($course['mode'])) ?></span>
                    </div>
                    <h5 class="card-title"><?= htmlspecialchars($course['course_name']) ?></h5>
                    <p class="text-muted small">
                        <i class="bi bi-geo-alt-fill"></i> <?= htmlspecialchars($course['branch_name']) ?>
                    </p>
                    <p class="card-text"><?= substr(htmlspecialchars($course['description']), 0, 100) ?>...</p>
                    <div class="d-flex justify-content-between mt-3">
                        <div>
                            <small class="text-muted">Duration:</small>
                            <strong class="d-block"><?= htmlspecialchars($course['duration']) ?></strong>
                        </div>
                        <div class="text-end">
                            <small class="text-muted">Fee:</small>
                            <strong class="d-block text-success">Rs. <?= number_format($course['fee'], 2) ?></strong>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <a href="course_details.php?id=<?= $course['course_id'] ?>" class="btn btn-outline-primary w-100">View Details</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>