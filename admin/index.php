<?php
require_once '../config.php';
require_role('admin');

$db = Database::getInstance()->getConnection();

// Get Counts
$students_count = $db->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$courses_count = $db->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$enrollments_pending = $db->query("SELECT COUNT(*) FROM enrollments WHERE status='pending'")->fetchColumn();
$inquiries_new = $db->query("SELECT COUNT(*) FROM inquiries WHERE status='new'")->fetchColumn();

include '../includes/header.php';
?>

<div class="container mt-4">
    <h2 class="mb-4">Admin Dashboard</h2>
    
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <h1><?= $students_count ?></h1>
                    <p class="mb-0">Total Students</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <h1><?= $courses_count ?></h1>
                    <p class="mb-0">Active Courses</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark h-100">
                <div class="card-body">
                    <h1><?= $enrollments_pending ?></h1>
                    <p class="mb-0">Pending Enrollments</p>
                    <a href="manage_enrollments.php" class="btn btn-sm btn-light mt-2">Manage</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body">
                    <h1><?= $inquiries_new ?></h1>
                    <p class="mb-0">New Inquiries</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">Management Tools</div>
                <div class="card-body">
                    <div class="d-grid gap-2 d-md-block">
                        <a href="manage_courses.php" class="btn btn-outline-primary"><i class="bi bi-book"></i> Courses</a>
                        <a href="manage_students.php" class="btn btn-outline-secondary"><i class="bi bi-people"></i> Students</a>
                        <a href="manage_notices.php" class="btn btn-outline-success"><i class="bi bi-megaphone"></i> Notices</a>
                        <a href="manage_events.php" class="btn btn-outline-warning"><i class="bi bi-calendar"></i> Events</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>