<?php
// student/schedule.php
require_once '../config.php';
require_role('student');

$student_id = $_SESSION['user_id'];
$db = Database::getInstance()->getConnection();

// Fetch schedules ONLY for courses the student is currently enrolled in (and approved)
$sql = "
    SELECT s.*, c.course_name, c.course_code, c.mode, 
           b.branch_name, u.full_name as instructor_name
    FROM schedules s
    JOIN courses c ON s.course_id = c.course_id
    JOIN enrollments e ON c.course_id = e.course_id
    LEFT JOIN branches b ON c.branch_id = b.branch_id
    LEFT JOIN users u ON c.instructor_id = u.user_id
    WHERE e.student_id = ? 
    AND e.status = 'approved'
    ORDER BY FIELD(s.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), 
             s.start_time ASC
";

$stmt = $db->prepare($sql);
$stmt->execute([$student_id]);
$all_classes = $stmt->fetchAll();

// Organize data by Day
$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$weekly_schedule = array_fill_keys($days_of_week, []);

foreach ($all_classes as $class) {
    $weekly_schedule[$class['day_of_week']][] = $class;
}

include '../includes/header.php';
?>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4 print-hide">
        <div>
            <h2>My Class Schedule</h2>
            <p class="text-muted">Timetable for your active courses.</p>
        </div>
        <div>
            <button onclick="window.print()" class="btn btn-outline-secondary">
                <i class="bi bi-printer me-1"></i> Print Schedule
            </button>
            <a href="index.php" class="btn btn-primary ms-2">Dashboard</a>
        </div>
    </div>

    <?php if (empty($all_classes)): ?>
        <div class="alert alert-warning text-center p-5">
            <i class="bi bi-calendar-x fs-1 d-block mb-3"></i>
            <h4>No Classes Found</h4>
            <p>You don't have any classes scheduled yet. <br>This might be because you haven't enrolled in any courses or your enrollments are still pending approval.</p>
            <a href="enroll.php" class="btn btn-primary mt-3">Browse Courses</a>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($days_of_week as $day): ?>
                <?php $classes = $weekly_schedule[$day]; ?>
                <div class="col-12 mb-4">
                    <div class="card shadow-sm h-100 <?= empty($classes) ? 'opacity-50 bg-light' : 'border-start border-4 border-primary' ?>">
                        <div class="card-header d-flex justify-content-between align-items-center <?= empty($classes) ? 'bg-transparent border-bottom-0' : 'bg-light' ?>">
                            <h5 class="mb-0 fw-bold <?= empty($classes) ? 'text-muted' : 'text-primary' ?>"><?= $day ?></h5>
                            <?php if (!empty($classes)): ?>
                                <span class="badge bg-white text-primary border"><?= count($classes) ?> Classes</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($classes)): ?>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 20%">Time</th>
                                            <th style="width: 35%">Course</th>
                                            <th style="width: 25%">Location</th>
                                            <th style="width: 20%">Instructor</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($classes as $cls): ?>
                                            <tr>
                                                <td class="align-middle">
                                                    <div class="fw-bold text-dark">
                                                        <?= date('g:i A', strtotime($cls['start_time'])) ?>
                                                    </div>
                                                    <small class="text-muted">
                                                        to <?= date('g:i A', strtotime($cls['end_time'])) ?>
                                                    </small>
                                                </td>
                                                <td class="align-middle">
                                                    <span class="badge bg-primary bg-opacity-10 text-primary mb-1">
                                                        <?= htmlspecialchars($cls['course_code']) ?>
                                                    </span>
                                                    <div class="fw-semibold"><?= htmlspecialchars($cls['course_name']) ?></div>
                                                </td>
                                                <td class="align-middle">
                                                    <?php if($cls['mode'] == 'online'): ?>
                                                        <span class="text-success"><i class="bi bi-laptop me-1"></i> Online Class</span>
                                                        <div class="small text-muted">Check email for link</div>
                                                    <?php else: ?>
                                                        <div><i class="bi bi-geo-alt me-1"></i> <?= htmlspecialchars($cls['branch_name']) ?></div>
                                                        <span class="badge bg-secondary text-light mt-1">
                                                            Room: <?= htmlspecialchars($cls['room_number']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="align-middle text-muted">
                                                    <i class="bi bi-person me-1"></i> <?= htmlspecialchars($cls['instructor_name']) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php else: ?>
                            <div class="card-body py-2">
                                <small class="text-muted fst-italic">No classes scheduled.</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
@media print {
    .print-hide, .navbar, .footer {
        display: none !important;
    }
    .card {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
        break-inside: avoid;
    }
    .badge {
        border: 1px solid #000;
        color: #000 !important;
    }
}
</style>

<?php include '../includes/footer.php'; ?>