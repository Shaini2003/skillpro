<?php
require_once '../config.php';
require_role('admin');

$db = Database::getInstance()->getConnection();
$success = '';
$errors = [];

// --- 1. HANDLE FORM SUBMISSION (ADD/EDIT COURSE) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_course'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Invalid security token';
    } else {
        $course_id = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
        $course_name = sanitize_input($_POST['course_name']);
        $course_code = sanitize_input($_POST['course_code']);
        $category_id = (int)$_POST['category_id'];
        $branch_id = (int)$_POST['branch_id'];
        $instructor_id = !empty($_POST['instructor_id']) ? (int)$_POST['instructor_id'] : null; // Handle Instructor
        $fee = (float)$_POST['fee'];
        $duration = sanitize_input($_POST['duration']);
        $mode = sanitize_input($_POST['mode']);
        $status = sanitize_input($_POST['status']);
        $description = sanitize_input($_POST['description']);

        // Basic Validation
        if (strlen($course_name) < 3) $errors[] = 'Course name is too short';
        if (empty($course_code)) $errors[] = 'Course code is required';

        if (empty($errors)) {
            if ($course_id > 0) {
                // Update Existing Course
                $stmt = $db->prepare("UPDATE courses SET course_name=?, course_code=?, category_id=?, branch_id=?, instructor_id=?, fee=?, duration=?, mode=?, status=?, description=? WHERE course_id=?");
                if ($stmt->execute([$course_name, $course_code, $category_id, $branch_id, $instructor_id, $fee, $duration, $mode, $status, $description, $course_id])) {
                    $success = 'Course updated successfully!';
                    log_activity(get_user_id(), 'COURSE_UPDATE', "Updated course: $course_name");
                } else {
                    $errors[] = 'Database update failed.';
                }
            } else {
                // Insert New Course
                $stmt = $db->prepare("INSERT INTO courses (course_name, course_code, category_id, branch_id, instructor_id, fee, duration, mode, status, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$course_name, $course_code, $category_id, $branch_id, $instructor_id, $fee, $duration, $mode, $status, $description])) {
                    $success = 'Course added successfully!';
                    log_activity(get_user_id(), 'COURSE_CREATE', "Created course: $course_name");
                } else {
                    $errors[] = 'Database insert failed.';
                }
            }
        }
    }
}

// Handle Delete
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    // Check for enrollments first
    $check = $db->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = ?");
    $check->execute([$id]);
    if ($check->fetchColumn() > 0) {
        $errors[] = "Cannot delete course. Students are already enrolled.";
    } else {
        $stmt = $db->prepare("DELETE FROM courses WHERE course_id = ?");
        if ($stmt->execute([$id])) {
            $success = "Course deleted successfully.";
        }
    }
}

// --- 2. PREPARE DATA ---

// Fetch Filters
$search = $_GET['search'] ?? '';
$filter_instructor = $_GET['instructor'] ?? '';

// Fetch Dropdown Data
$branches = $db->query("SELECT * FROM branches")->fetchAll();
$categories = $db->query("SELECT * FROM categories")->fetchAll();
// Fetch Active Instructors for Dropdown
$instructors = $db->query("SELECT user_id, full_name FROM users WHERE role = 'instructor' AND status = 'active' ORDER BY full_name")->fetchAll();

// Build Main Query
$query = "SELECT c.*, b.branch_name, cat.category_name, u.full_name as instructor_name,
          (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.course_id AND e.status = 'approved') as active_students
          FROM courses c
          LEFT JOIN branches b ON c.branch_id = b.branch_id
          LEFT JOIN categories cat ON c.category_id = cat.category_id
          LEFT JOIN users u ON c.instructor_id = u.user_id
          WHERE 1=1";

$params = [];

if ($search) {
    $query .= " AND (c.course_name LIKE ? OR c.course_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filter_instructor) {
    $query .= " AND c.instructor_id = ?";
    $params[] = $filter_instructor;
}

$query .= " ORDER BY c.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$courses = $stmt->fetchAll();

$page_title = 'Manage Courses';
include '../includes/header.php';
?>

<style>body.modal-open { overflow-y: scroll !important; padding-right: 0 !important; }</style>

<div class="container-fluid mt-4 mb-5">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0"><i class="bi bi-book-half text-primary"></i> Manage Courses</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                    <i class="bi bi-plus-lg"></i> Add New Course
                </button>
            </div>

            <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
            <?php if (!empty($errors)): ?><div class="alert alert-danger"><?= implode('<br>', $errors) ?></div><?php endif; ?>

            <div class="card mb-4 shadow-sm">
                <div class="card-body">
                    <form class="row g-2">
                        <div class="col-md-4">
                            <input type="text" name="search" class="form-control" placeholder="Search course..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-3">
                            <select name="instructor" class="form-select">
                                <option value="">Filter by Instructor</option>
                                <?php foreach ($instructors as $inst): ?>
                                    <option value="<?= $inst['user_id'] ?>" <?= $filter_instructor == $inst['user_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($inst['full_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-secondary w-100">Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Code</th>
                                    <th>Course Name</th>
                                    <th>Category</th>
                                    <th>Instructor</th> <th>Branch</th>
                                    <th>Fee</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($courses as $course): ?>
                                <tr>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($course['course_code']) ?></span></td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($course['course_name']) ?></div>
                                        <small class="text-muted"><?= $course['active_students'] ?> Students</small>
                                    </td>
                                    <td><?= htmlspecialchars($course['category_name']) ?></td>
                                    
                                    <td>
                                        <?php if ($course['instructor_name']): ?>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary bg-opacity-10 text-primary rounded-circle me-2 d-flex justify-content-center align-items-center" style="width:30px;height:30px;font-size:12px;">
                                                    <?= substr($course['instructor_name'], 0, 1) ?>
                                                </div>
                                                <?= htmlspecialchars($course['instructor_name']) ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic">Unassigned</span>
                                        <?php endif; ?>
                                    </td>

                                    <td><?= htmlspecialchars($course['branch_name']) ?></td>
                                    <td>Rs. <?= number_format($course['fee']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $course['status'] == 'active' ? 'success' : 'secondary' ?>">
                                            <?= ucfirst($course['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $course['course_id'] ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <a href="?action=delete&id=<?= $course['course_id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this course?');">
                                            <i class="bi bi-trash"></i>
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
    </div>
</div>

<div class="modal fade" id="addCourseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Add New Course</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Course Name *</label>
                        <input type="text" name="course_name" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Course Code *</label>
                        <input type="text" name="course_code" class="form-control" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-select" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Assign Instructor</label>
                        <select name="instructor_id" class="form-select">
                            <option value="">-- No Instructor --</option>
                            <?php foreach ($instructors as $inst): ?>
                                <option value="<?= $inst['user_id'] ?>"><?= htmlspecialchars($inst['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Branch</label>
                        <select name="branch_id" class="form-select" required>
                            <?php foreach ($branches as $br): ?>
                                <option value="<?= $br['branch_id'] ?>"><?= htmlspecialchars($br['branch_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Fee (Rs)</label>
                        <input type="number" name="fee" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Mode</label>
                        <select name="mode" class="form-select">
                            <option value="online">Online</option>
                            <option value="onsite">Onsite</option>
                            <option value="hybrid">Hybrid</option>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Duration</label>
                        <input type="text" name="duration" class="form-control" placeholder="e.g. 3 Months">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" name="save_course" class="btn btn-primary">Save Course</button>
            </div>
        </form>
    </div>
</div>

<?php foreach ($courses as $c): ?>
<div class="modal fade" id="editModal<?= $c['course_id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Edit Course</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="course_id" value="<?= $c['course_id'] ?>">
                
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Course Name</label>
                        <input type="text" name="course_name" class="form-control" value="<?= htmlspecialchars($c['course_name']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Code</label>
                        <input type="text" name="course_code" class="form-control" value="<?= htmlspecialchars($c['course_code']) ?>" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-select" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['category_id'] ?>" <?= $c['category_id'] == $cat['category_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Assign Instructor</label>
                        <select name="instructor_id" class="form-select">
                            <option value="">-- No Instructor --</option>
                            <?php foreach ($instructors as $inst): ?>
                                <option value="<?= $inst['user_id'] ?>" <?= $c['instructor_id'] == $inst['user_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($inst['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Branch</label>
                        <select name="branch_id" class="form-select" required>
                            <?php foreach ($branches as $br): ?>
                                <option value="<?= $br['branch_id'] ?>" <?= $c['branch_id'] == $br['branch_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($br['branch_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Fee</label>
                        <input type="number" name="fee" class="form-control" value="<?= $c['fee'] ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Mode</label>
                        <select name="mode" class="form-select">
                            <option value="online" <?= $c['mode'] == 'online' ? 'selected' : '' ?>>Online</option>
                            <option value="onsite" <?= $c['mode'] == 'onsite' ? 'selected' : '' ?>>Onsite</option>
                            <option value="hybrid" <?= $c['mode'] == 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Duration</label>
                        <input type="text" name="duration" class="form-control" value="<?= htmlspecialchars($c['duration']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?= $c['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $c['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($c['description']) ?></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" name="save_course" class="btn btn-info text-white">Update Course</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<?php include '../includes/footer.php'; ?>