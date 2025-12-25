<?php
require_once '../config.php';
require_role('admin');

$db = Database::getInstance()->getConnection();
$message = '';

// Handle Add Course
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_course'])) {
    $code = sanitize_input($_POST['code']);
    $name = sanitize_input($_POST['name']);
    $category = $_POST['category'];
    $fee = $_POST['fee'];
    $duration = $_POST['duration'];
    $mode = $_POST['mode'];
    $branch = $_POST['branch'];
    
    $stmt = $db->prepare("INSERT INTO courses (course_code, course_name, category_id, fee, duration, mode, branch_id) VALUES (?,?,?,?,?,?,?)");
    if($stmt->execute([$code, $name, $category, $fee, $duration, $mode, $branch])) {
        $message = '<div class="alert alert-success">Course Added Successfully</div>';
    } else {
        $message = '<div class="alert alert-danger">Error adding course</div>';
    }
}

// Fetch Data for Dropdowns and List
$courses = $db->query("SELECT c.*, cat.category_name, b.branch_name FROM courses c LEFT JOIN categories cat ON c.category_id = cat.category_id LEFT JOIN branches b ON c.branch_id = b.branch_id ORDER BY c.course_id DESC")->fetchAll();
$categories = $db->query("SELECT * FROM categories")->fetchAll();
$branches = $db->query("SELECT * FROM branches")->fetchAll();

include '../includes/header.php';
?>

<div class="container mt-4">
    <h2>Manage Courses</h2>
    <?= $message ?>
    
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-light">Add New Course</div>
        <div class="card-body">
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-3">
                        <input type="text" name="code" class="form-control" placeholder="Course Code (e.g. ICT101)" required>
                    </div>
                    <div class="col-md-5">
                        <input type="text" name="name" class="form-control" placeholder="Course Name" required>
                    </div>
                    <div class="col-md-4">
                        <select name="category" class="form-select" required>
                            <option value="">Select Category</option>
                            <?php foreach($categories as $c): ?><option value="<?=$c['category_id']?>"><?=$c['category_name']?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="number" name="fee" class="form-control" placeholder="Fee (LKR)" required>
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="duration" class="form-control" placeholder="Duration (e.g. 6 Months)" required>
                    </div>
                    <div class="col-md-3">
                        <select name="mode" class="form-select">
                            <option value="onsite">On-site</option>
                            <option value="online">Online</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="branch" class="form-select" required>
                            <option value="">Select Branch</option>
                            <?php foreach($branches as $b): ?><option value="<?=$b['branch_id']?>"><?=$b['branch_name']?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="add_course" class="btn btn-primary">Create Course</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Code</th>
                <th>Name</th>
                <th>Category</th>
                <th>Branch</th>
                <th>Mode</th>
                <th>Fee</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($courses as $course): ?>
            <tr>
                <td><?= htmlspecialchars($course['course_code']) ?></td>
                <td><?= htmlspecialchars($course['course_name']) ?></td>
                <td><?= htmlspecialchars($course['category_name']) ?></td>
                <td><?= htmlspecialchars($course['branch_name']) ?></td>
                <td><span class="badge bg-secondary"><?= ucfirst($course['mode']) ?></span></td>
                <td>Rs. <?= number_format($course['fee'], 2) ?></td>
                <td>
                    <a href="#" class="btn btn-sm btn-warning">Edit</a>
                    <a href="#" class="btn btn-sm btn-danger">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>