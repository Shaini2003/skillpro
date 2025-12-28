<?php
// instructor/schedule.php
require_once '../config.php';
require_role('instructor');

$instructor_id = get_user_id();
$db = Database::getInstance()->getConnection();
$message = '';
$error = '';

// Add Schedule
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate CSRF token
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = "Invalid request.";
    } else {
        $course_id = $_POST['course_id'];
        $day = $_POST['day_of_week'];
        $start = $_POST['start_time'];
        $end = $_POST['end_time'];
        $room = sanitize_input($_POST['room_number']);

        // Insert using 'day_of_week' and 'room_number'
        $stmt = $db->prepare("INSERT INTO schedules (course_id, day_of_week, start_time, end_time, room_number) VALUES (?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$course_id, $day, $start, $end, $room])) {
            $message = "Class scheduled successfully!";
        } else {
            $error = "Failed to schedule class.";
        }
    }
}

// Get Instructor's Courses (Fixed: Removed strict 'active' check to ensure courses show up)
$stmt = $db->prepare("SELECT course_id, course_name FROM courses WHERE instructor_id = ?");
$stmt->execute([$instructor_id]);
$courses = $stmt->fetchAll();

// Get Existing Schedules
// Sorted by Day of Week (Monday -> Sunday) and then Time
$stmt = $db->prepare("
    SELECT s.*, c.course_name 
    FROM schedules s 
    JOIN courses c ON s.course_id = c.course_id 
    WHERE c.instructor_id = ? 
    ORDER BY FIELD(s.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), s.start_time ASC
");
$stmt->execute([$instructor_id]);
$schedules = $stmt->fetchAll();

$page_title = 'Weekly Schedule';
include '../includes/header.php';
?>

<div class="container mt-4 mb-5">
    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Add Weekly Class</h5>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-success p-2 small"><i class="bi bi-check-circle"></i> <?= $message ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger p-2 small"><i class="bi bi-exclamation-triangle"></i> <?= $error ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Course</label>
                            <select name="course_id" class="form-select" required>
                                <?php if (empty($courses)): ?>
                                    <option value="">No courses assigned</option>
                                <?php else: ?>
                                    <?php foreach ($courses as $c): ?>
                                        <option value="<?= $c['course_id'] ?>"><?= htmlspecialchars($c['course_name']) ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <?php if (empty($courses)): ?>
                                <div class="form-text text-danger">You have no courses assigned. Contact Admin.</div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Day of Week</label>
                            <select name="day_of_week" class="form-select" required>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                                <option value="Sunday">Sunday</option>
                            </select>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col">
                                <label class="form-label">Start Time</label>
                                <input type="time" name="start_time" class="form-control" required>
                            </div>
                            <div class="col">
                                <label class="form-label">End Time</label>
                                <input type="time" name="end_time" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Room Number / Lab</label>
                            <input type="text" name="room_number" class="form-control" placeholder="Ex: Lab A-1" required>
                        </div>
                        
                        <button type="submit" class="btn btn-success w-100" <?= empty($courses) ? 'disabled' : '' ?>>Add to Schedule</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-calendar-week"></i> Current Weekly Schedule</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Day</th>
                                <th>Course</th>
                                <th>Time</th>
                                <th>Room</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($schedules)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No classes scheduled yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($schedules as $s): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <?= htmlspecialchars($s['day_of_week']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($s['course_name']) ?></div>
                                        </td>
                                        <td>
                                            <small><?= date('g:i A', strtotime($s['start_time'])) ?> - <?= date('g:i A', strtotime($s['end_time'])) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-info bg-opacity-10 text-info">
                                                <i class="bi bi-geo-alt-fill"></i> <?= htmlspecialchars($s['room_number']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-danger" onclick="alert('Delete feature coming soon!')">
                                                <i class="bi bi-trash"></i>
                                            </button>
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

<?php include '../includes/footer.php'; ?>