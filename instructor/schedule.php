<?php
// instructor/schedule.php
require_once '../config.php';
require_role('instructor');

$instructor_id = $_SESSION['user_id'];
$db = Database::getInstance()->getConnection();

// Check if filtering by specific course
$course_filter = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$filter_title = "All Courses";

// Base Query
$sql = "
    SELECT s.*, c.course_name, c.course_code, b.branch_name 
    FROM schedules s 
    JOIN courses c ON s.course_id = c.course_id 
    JOIN branches b ON c.branch_id = b.branch_id 
    WHERE c.instructor_id = ? 
";
$params = [$instructor_id];

// Apply Filter
if ($course_filter > 0) {
    $sql .= " AND c.course_id = ?";
    $params[] = $course_filter;
    
    // Get course name for display title
    $stmt_name = $db->prepare("SELECT course_name FROM courses WHERE course_id = ?");
    $stmt_name->execute([$course_filter]);
    $c_name = $stmt_name->fetchColumn();
    if($c_name) $filter_title = $c_name;
}

// Order by Day (Monday first) and Time
$sql .= " ORDER BY FIELD(s.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), s.start_time";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Group results by Day of Week for better display
$schedule_by_day = [];
$days_order = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

foreach ($days_order as $day) {
    $schedule_by_day[$day] = [];
}

foreach ($rows as $row) {
    $schedule_by_day[$row['day_of_week']][] = $row;
}

include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>Weekly Schedule</h2>
            <h5 class="text-muted"><?= htmlspecialchars($filter_title) ?></h5>
        </div>
        <div>
            <?php if ($course_filter > 0): ?>
                <a href="schedule.php" class="btn btn-outline-primary me-2">Show All</a>
            <?php endif; ?>
            <a href="index.php" class="btn btn-outline-secondary">Dashboard</a>
        </div>
    </div>

    <?php if (empty($rows)): ?>
        <div class="alert alert-warning text-center p-5">
            <i class="bi bi-calendar-x fs-1 d-block mb-3"></i>
            <h5>No classes scheduled.</h5>
            <p>Your timetable is currently empty for this selection.</p>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($schedule_by_day as $day => $classes): ?>
                <?php if (!empty($classes)): ?>
                <div class="col-12 mb-4">
                    <div class="card shadow-sm border-start border-4 border-primary">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold text-primary"><?= $day ?></h5>
                            <span class="badge bg-secondary"><?= count($classes) ?> Classes</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 20%;">Time</th>
                                            <th style="width: 40%;">Course</th>
                                            <th style="width: 25%;">Branch</th>
                                            <th style="width: 15%;">Room</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($classes as $cls): ?>
                                            <tr>
                                                <td class="fw-bold text-dark">
                                                    <?= date('g:i A', strtotime($cls['start_time'])) ?> - 
                                                    <?= date('g:i A', strtotime($cls['end_time'])) ?>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($cls['course_code']) ?></strong><br>
                                                    <?= htmlspecialchars($cls['course_name']) ?>
                                                </td>
                                                <td>
                                                    <i class="bi bi-geo-alt-fill text-danger"></i> 
                                                    <?= htmlspecialchars($cls['branch_name']) ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info text-dark">
                                                        <?= htmlspecialchars($cls['room_number']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>