<?php
// instructor/materials.php
require_once '../config.php';
require_role('instructor');

$instructor_id = get_user_id();
$db = Database::getInstance()->getConnection();
$message = '';
$error = '';

// Handle File Deletion
if (isset($_GET['delete'])) {
    $material_id = (int)$_GET['delete'];
    
    // Check ownership
    $check = $db->prepare("
        SELECT m.* FROM course_materials m 
        JOIN courses c ON m.course_id = c.course_id 
        WHERE m.material_id = ? AND c.instructor_id = ?
    ");
    $check->execute([$material_id, $instructor_id]);
    $file = $check->fetch();

    if ($file) {
        // Delete physical file
        $file_path = "../uploads/materials/" . $file['file_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        // Delete DB record
        $del = $db->prepare("DELETE FROM course_materials WHERE material_id = ?");
        $del->execute([$material_id]);
        $message = "Material deleted successfully.";
    } else {
        $error = "Error: Permission denied or file not found.";
    }
}

// Handle Upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = "Invalid Security Token";
    } else {
        $course_id = $_POST['course_id'];
        $title = sanitize_input($_POST['title']);
        $description = sanitize_input($_POST['description']);
        
        // File handling
        $target_dir = "../uploads/materials/";
        if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }
        
        $file_name = time() . '_' . basename($_FILES["file"]["name"]);
        $target_file = $target_dir . $file_name;
        $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        
        // Allow certain file formats
        $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip', 'jpg', 'png'];
        
        if (in_array($file_type, $allowed)) {
            if (move_uploaded_file($_FILES["file"]["tmp_name"], $target_file)) {
                // FIXED: Added file_type to query
                $stmt = $db->prepare("INSERT INTO course_materials (course_id, title, description, file_path, file_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$course_id, $title, $description, $file_name, $file_type, $instructor_id])) {
                    $message = "File uploaded successfully!";
                } else {
                    $error = "Database error.";
                }
            } else {
                $error = "Sorry, there was an error uploading your file.";
            }
        } else {
            $error = "Invalid file type. Only PDF, Docs, PPT, Images & ZIP allowed.";
        }
    }
}

// Get Instructor's Courses
$stmt = $db->prepare("SELECT course_id, course_name, course_code FROM courses WHERE instructor_id = ?");
$stmt->execute([$instructor_id]);
$my_courses = $stmt->fetchAll();

// Get Existing Materials
$selected_course = $_GET['course_id'] ?? ($my_courses[0]['course_id'] ?? 0);
$materials = [];

if ($selected_course) {
    // FIXED: Changed 'created_at' to 'uploaded_at'
    $stmt = $db->prepare("SELECT * FROM course_materials WHERE course_id = ? ORDER BY uploaded_at DESC");
    $stmt->execute([$selected_course]);
    $materials = $stmt->fetchAll();
}

$page_title = 'Course Materials';
include '../includes/header.php';
?>

<div class="container mt-4 mb-5">
    <h2 class="mb-4"><i class="bi bi-folder2-open text-warning"></i> Course Materials</h2>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle"></i> <?= $message ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle"></i> <?= $error ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">Upload New Material</div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Select Course</label>
                            <select name="course_id" class="form-select" required onchange="window.location.href='?course_id='+this.value">
                                <?php if (empty($my_courses)): ?>
                                    <option value="">No courses assigned</option>
                                <?php else: ?>
                                    <?php foreach ($my_courses as $course): ?>
                                        <option value="<?= $course['course_id'] ?>" <?= $selected_course == $course['course_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Material Title</label>
                            <input type="text" name="title" class="form-control" required placeholder="Ex: Week 1 Lecture Slides">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description (Optional)</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">File</label>
                            <input type="file" name="file" class="form-control" required>
                            <small class="text-muted">Max 10MB. PDF, DOCX, PPTX, ZIP</small>
                        </div>

                        <button type="submit" class="btn btn-primary w-100" <?= empty($my_courses) ? 'disabled' : '' ?>>
                            <i class="bi bi-cloud-upload"></i> Upload
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Uploaded Materials</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($materials)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-folder-x fs-1"></i>
                            <p>No materials uploaded for this course yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($materials as $file): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">
                                            <i class="bi bi-file-earmark-text text-secondary me-2"></i>
                                            <?= htmlspecialchars($file['title']) ?>
                                            <span class="badge bg-light text-dark border ms-2"><?= strtoupper($file['file_type'] ?? 'FILE') ?></span>
                                        </h6>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($file['description'] ?? '') ?> • 
                                            Uploaded: <?= date('M d, Y', strtotime($file['uploaded_at'])) ?>
                                        </small>
                                    </div>
                                    <div class="btn-group">
                                        <a href="../uploads/materials/<?= $file['file_path'] ?>" class="btn btn-sm btn-outline-primary" download>
                                            <i class="bi bi-download"></i>
                                        </a>
                                        <a href="?course_id=<?= $course_id ?>&delete=<?= $file['material_id'] ?>" 
                                           class="btn btn-sm btn-outline-danger" 
                                           onclick="return confirm('Delete this file?');">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>