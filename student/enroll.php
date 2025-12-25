<?php
// student/enroll.php
require_once '../config.php';
require_role('student');

$student_id = $_SESSION['user_id'];
$db = Database::getInstance()->getConnection();

// Get Search/Filter Parameters
$search = $_GET['search'] ?? '';
$category_id = $_GET['category'] ?? '';
$branch_id = $_GET['branch'] ?? '';

// Build Query: Select active courses that the student has NOT enrolled in yet
$sql = "
    SELECT c.*, b.branch_name, cat.category_name, u.full_name as instructor_name,
           (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.course_id AND e.status = 'approved') as current_students
    FROM courses c
    LEFT JOIN branches b ON c.branch_id = b.branch_id
    LEFT JOIN categories cat ON c.category_id = cat.category_id
    LEFT JOIN users u ON c.instructor_id = u.user_id
    WHERE c.status = 'active'
    AND c.course_id NOT IN (SELECT course_id FROM enrollments WHERE student_id = ?)
";

$params = [$student_id];

if ($search) {
    $sql .= " AND (c.course_name LIKE ? OR c.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_id) {
    $sql .= " AND c.category_id = ?";
    $params[] = $category_id;
}

if ($branch_id) {
    $sql .= " AND c.branch_id = ?";
    $params[] = $branch_id;
}

$sql .= " ORDER BY c.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$available_courses = $stmt->fetchAll();

// Fetch filter options
$categories = get_categories();
$branches = get_branches();

include '../includes/header.php';
?>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Enroll in New Courses</h2>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Dashboard
        </a>
    </div>

    <div class="card mb-4 bg-light shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Search by name or description..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>" <?= $category_id == $cat['category_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="branch" class="form-select">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $br): ?>
                            <option value="<?= $br['branch_id'] ?>" <?= $branch_id == $br['branch_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($br['branch_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($available_courses)): ?>
        <div class="alert alert-info text-center py-5">
            <i class="bi bi-emoji-smile fs-1 d-block mb-3"></i>
            <h4>All Caught Up!</h4>
            <p>You have enrolled in all available courses matching your search.</p>
            <a href="my_courses.php" class="btn btn-primary mt-2">View My Courses</a>
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php foreach ($available_courses as $course): ?>
                <?php 
                    $seats_left = $course['max_students'] - $course['current_students'];
                    $progress = ($course['current_students'] / $course['max_students']) * 100;
                    $is_full = $seats_left <= 0;
                ?>
                <div class="col">
                    <div class="card h-100 shadow-sm border-0 hover-lift">
                        <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                            <div class="d-flex justify-content-between align-items-start">
                                <span class="badge bg-primary bg-opacity-10 text-primary">
                                    <?= htmlspecialchars($course['category_name']) ?>
                                </span>
                                <span class="badge bg-secondary"><?= ucfirst($course['mode']) ?></span>
                            </div>
                        </div>

                        <div class="card-body">
                            <h5 class="card-title fw-bold mb-2">
                                <a href="../course_details.php?id=<?= $course['course_id'] ?>" class="text-decoration-none text-dark">
                                    <?= htmlspecialchars($course['course_name']) ?>
                                </a>
                            </h5>
                            <p class="text-muted small mb-3">
                                <i class="bi bi-person-badge"></i> <?= htmlspecialchars($course['instructor_name']) ?>
                            </p>
                            
                            <p class="card-text text-secondary small">
                                <?= substr(htmlspecialchars($course['description']), 0, 90) ?>...
                            </p>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span>Capacity</span>
                                    <span class="<?= $is_full ? 'text-danger' : 'text-success' ?>">
                                        <?= $is_full ? 'Full' : $seats_left . ' seats left' ?>
                                    </span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar <?= $is_full ? 'bg-danger' : 'bg-success' ?>" role="progressbar" style="width: <?= $progress ?>%"></div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center bg-light p-2 rounded">
                                <div class="small">
                                    <div class="text-muted">Course Fee</div>
                                    <div class="fw-bold text-dark">Rs. <?= number_format($course['fee'], 2) ?></div>
                                </div>
                                <div class="small text-end">
                                    <div class="text-muted">Duration</div>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($course['duration']) ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="card-footer bg-white border-top-0 pb-3 pt-0">
                            <div class="d-grid gap-2">
                                <?php if ($is_full): ?>
                                    <button class="btn btn-secondary" disabled>Class Full</button>
                                <?php else: ?>
                                    <button onclick="confirmEnrollment(<?= $course['course_id'] ?>)" class="btn btn-primary">
                                        Enroll Now
                                    </button>
                                <?php endif; ?>
                                <a href="../course_details.php?id=<?= $course['course_id'] ?>" class="btn btn-outline-secondary btn-sm">View Details</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>