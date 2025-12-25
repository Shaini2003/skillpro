<?php
// course_details.php
require_once 'config.php';

if (!isset($_GET['id'])) {
    redirect('courses.php');
}

$course_id = (int)$_GET['id'];
$db = Database::getInstance()->getConnection();

// Fetch course details
$stmt = $db->prepare("SELECT c.*, cat.category_name, b.branch_name, b.location, u.full_name as instructor_name 
                      FROM courses c 
                      LEFT JOIN categories cat ON c.category_id = cat.category_id
                      LEFT JOIN branches b ON c.branch_id = b.branch_id
                      LEFT JOIN users u ON c.instructor_id = u.user_id
                      WHERE c.course_id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) {
    redirect('courses.php');
}

// Fetch schedule
$stmt = $db->prepare("SELECT * FROM schedules WHERE course_id = ? ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')");
$stmt->execute([$course_id]);
$schedules = $stmt->fetchAll();

// Check enrollment status if logged in as student
$is_enrolled = false;
if (is_logged_in() && get_user_role() == 'student') {
    $stmt = $db->prepare("SELECT * FROM enrollments WHERE student_id = ? AND course_id = ?");
    $stmt->execute([$_SESSION['user_id'], $course_id]);
    $is_enrolled = $stmt->fetch();
}

include 'includes/header.php';
?>

<div class="container mt-5 mb-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="courses.php">Courses</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($course['course_name']) ?></li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-lg-8">
            <h1 class="mb-3"><?= htmlspecialchars($course['course_name']) ?></h1>
            <div class="mb-4">
                <span class="badge bg-primary me-2"><?= htmlspecialchars($course['category_name']) ?></span>
                <span class="badge bg-info me-2"><?= ucfirst($course['mode']) ?></span>
                <span class="text-muted"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($course['branch_name']) ?></span>
            </div>

            <img src="assets/images/course-placeholder.jpg" class="img-fluid rounded mb-4 w-100" alt="Course Image" style="height: 300px; object-fit: cover; background-color: #eee;">

            <h3>About this Course</h3>
            <p class="lead"><?= nl2br(htmlspecialchars($course['description'])) ?></p>

            <h4 class="mt-4">Class Schedule</h4>
            <?php if ($schedules): ?>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Day</th>
                            <th>Time</th>
                            <th>Location/Room</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedules as $sch): ?>
                        <tr>
                            <td><?= $sch['day_of_week'] ?></td>
                            <td><?= date('g:i A', strtotime($sch['start_time'])) ?> - <?= date('g:i A', strtotime($sch['end_time'])) ?></td>
                            <td><?= htmlspecialchars($sch['room_number']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-muted">Schedule to be announced.</p>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="card shadow border-0 sticky-top" style="top: 100px; z-index: 1;">
                <div class="card-body">
                    <h3 class="text-primary fw-bold mb-3">Rs. <?= number_format($course['fee'], 2) ?></h3>
                    
                    <ul class="list-unstyled mb-4">
                        <li class="mb-2"><i class="bi bi-clock me-2 text-muted"></i> <strong>Duration:</strong> <?= htmlspecialchars($course['duration']) ?></li>
                        <li class="mb-2"><i class="bi bi-person me-2 text-muted"></i> <strong>Instructor:</strong> <?= htmlspecialchars($course['instructor_name']) ?></li>
                        <li class="mb-2"><i class="bi bi-calendar-event me-2 text-muted"></i> <strong>Starts:</strong> <?= format_date($course['start_date']) ?></li>
                        <li class="mb-2"><i class="bi bi-people me-2 text-muted"></i> <strong>Max Seats:</strong> <?= $course['max_students'] ?></li>
                    </ul>

                    <?php if ($is_enrolled): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle-fill"></i> You have enrolled in this course.
                            <br>Status: <strong><?= ucfirst($is_enrolled['status']) ?></strong>
                        </div>
                        <a href="student/my_courses.php" class="btn btn-primary w-100">Go to My Courses</a>
                    <?php elseif (is_logged_in() && get_user_role() == 'student'): ?>
                        <button onclick="confirmEnrollment(<?= $course_id ?>)" class="btn btn-success btn-lg w-100 mb-2">Enroll Now</button>
                        <small class="text-muted d-block text-center">Clicking Enroll will send a request to the admin.</small>
                    <?php elseif (is_logged_in() && get_user_role() != 'student'): ?>
                        <div class="alert alert-warning">Instructors/Admins cannot enroll.</div>
                    <?php else: ?>
                        <a href="login.php?redirect=course_details.php?id=<?= $course_id ?>" class="btn btn-primary btn-lg w-100">Login to Enroll</a>
                        <div class="text-center mt-2">
                            <small>New here? <a href="register.php">Register Now</a></small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmEnrollment(courseId) {
    if(confirm('Do you want to enroll in this course?')) {
        // You would typically use fetch/AJAX here. 
        // For simplicity, we redirect to a handler script
        window.location.href = 'student/enroll_handler.php?course_id=' + courseId;
    }
}
</script>

<?php include 'includes/footer.php'; ?>