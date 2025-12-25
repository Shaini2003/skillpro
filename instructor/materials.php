<?php
// instructor/materials.php
require_once '../config.php';
require_role('instructor');

$instructor_id = $_SESSION['user_id'];
$db = Database::getInstance()->getConnection();
$message = '';
$message_type = '';

// Handle File Deletion
if (isset($_GET['delete'])) {
    $material_id = (int)$_GET['delete'];
    
    // Verify ownership before deleting
    $check = $db->prepare("
        SELECT m.*, c.instructor_id 
        FROM course_materials m 
        JOIN courses c ON m.course_id = c.course_id 
        WHERE m.material_id = ? AND c.instructor_id = ?
    ");
    $check->execute([$material_id, $instructor_id]);
    $file = $check->fetch();

    if ($file) {
        // Delete physical file
        $file_path = '../' . $file['file_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }

        // Delete DB record
        $del = $db->prepare("DELETE FROM course_materials WHERE material_id = ?");
        $del->execute([$material_id]);
        
        $message = "Material deleted successfully.";
        $message_type = "success";
    } else {
        $message = "Error: Permission denied or file not found.";
        $message_type = "danger";
    }
}

// Handle File Upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_material'])) {
    $course_id = (int)$_POST['course_id'];
    $title = sanitize_input($_POST['title']);
    $description = sanitize_input($_POST['description']);

    // Verify course belongs to this instructor
    $verify_course = $db->prepare("SELECT course_id FROM courses WHERE course_id = ? AND instructor_id = ?");
    $verify_course->execute([$course_id, $instructor_id]);
    
    if ($verify_course->rowCount() > 0) {
        if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
            // Use the helper function from includes/functions.php
            $upload_result = upload_file($_FILES['file'], 'materials');
            
            if ($upload_result['success']) {
                $file_path = $upload_result['path'];
                $file_type = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));

                $stmt = $db->prepare("INSERT INTO course_materials (course_id, title, description, file_path, file_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
                
                if ($stmt->execute([$course_id, $title, $description, $file_path, $file_type, $instructor_id])) {
                    $message = "File uploaded successfully!";
                    $message_type = "success";
                } else {
                    $message = "Database error: Could not save file info.";
                    $message_type = "danger";
                }
            } else {
                $message = $upload_result['message'];
                $message_type = "danger";
            }
        } else {
            $message = "Please select a valid file.";
            $message_type = "warning";
        }
    } else {
        $message = "Invalid course selected.";
        $message_type = "danger";
    }
}

// Fetch Instructor's Courses (for dropdown)
$courses_stmt = $db->prepare("SELECT course_id, course_name, course_code FROM courses WHERE instructor_id = ? AND status = 'active'");
$courses_stmt->execute([$instructor_id]);
$my_courses = $courses_stmt->fetchAll();

// Fetch Uploaded Materials (for list)
$materials_stmt = $db->prepare("
    SELECT m.*, c.course_name, c.course_code 
    FROM course_materials m 
    JOIN courses c ON m.course_id = c.course_id 
    WHERE c.instructor_id = ? 
    ORDER BY m.uploaded_at DESC
");
$materials_stmt->execute([$instructor_id]);
$materials = $materials_stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Course Materials</h2>
        <a href="index.php" class="btn btn-outline-secondary">Back to Dashboard</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-upload me-2"></i>Upload New Material</h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Select Course *</label>
                            <select name="course_id" class="form-select" required>
                                <option value="">-- Choose Course --</option>
                                <?php foreach ($my_courses as $course): ?>
                                    <option value="<?= $course['course_id'] ?>">
                                        <?= htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Title *</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Week 1 Lecture Slides" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Brief description..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">File *</label>
                            <input type="file" name="file" class="form-control" required>
                            <div class="form-text">Allowed: PDF, DOCX, JPG, PNG (Max 5MB)</div>
                        </div>

                        <button type="submit" name="upload_material" class="btn btn-primary w-100">Upload File</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Uploaded Materials</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Title</th>
                                    <th>Course</th>
                                    <th>Type</th>
                                    <th>Uploaded</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($materials)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">
                                            No materials uploaded yet.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($materials as $mat): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($mat['title']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($mat['description']) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-info text-dark"><?= htmlspecialchars($mat['course_code']) ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary text-uppercase"><?= $mat['file_type'] ?></span>
                                            </td>
                                            <td><small><?= format_date($mat['uploaded_at']) ?></small></td>
                                            <td class="text-end">
                                                <a href="<?= SITE_URL . '/' . $mat['file_path'] ?>" class="btn btn-sm btn-outline-primary" target="_blank" title="View/Download">
                                                    <i class="bi bi-download"></i>
                                                </a>
                                                <a href="materials.php?delete=<?= $mat['material_id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this file? This cannot be undone.');" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>