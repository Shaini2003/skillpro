<?php
require_once '../config.php';
require_role('admin');

$db = Database::getInstance()->getConnection();
$success = '';
$errors = [];

// Handle Add/Edit Event
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_event'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Invalid request';
    } else {
        $event_id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
        $event_title = sanitize_input($_POST['event_title']);
        $event_type = $_POST['event_type'];
        $description = sanitize_input($_POST['description']);
        $event_date = $_POST['event_date'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $location = sanitize_input($_POST['location']);
        $branch_id = !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : NULL;
        
        // Validation
        if (strlen($event_title) < 5) {
            $errors[] = 'Event title must be at least 5 characters';
        }
        
        if (empty($event_date)) {
            $errors[] = 'Event date is required';
        }
        
        if (empty($errors)) {
            if ($event_id > 0) {
                // Update existing event
                $stmt = $db->prepare("UPDATE events SET event_title = ?, event_type = ?, description = ?, 
                                      event_date = ?, start_time = ?, end_time = ?, location = ?, branch_id = ? 
                                      WHERE event_id = ?");
                if ($stmt->execute([$event_title, $event_type, $description, $event_date, $start_time, 
                                    $end_time, $location, $branch_id, $event_id])) {
                    log_activity(get_user_id(), 'EVENT_UPDATE', "Updated event: $event_title");
                    $success = 'Event updated successfully!';
                } else {
                    $errors[] = 'Failed to update event';
                }
            } else {
                // Insert new event
                $stmt = $db->prepare("INSERT INTO events (event_title, event_type, description, event_date, 
                                      start_time, end_time, location, branch_id, created_by) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$event_title, $event_type, $description, $event_date, $start_time, 
                                    $end_time, $location, $branch_id, get_user_id()])) {
                    log_activity(get_user_id(), 'EVENT_CREATE', "Created event: $event_title");
                    $success = 'Event created successfully!';
                } else {
                    $errors[] = 'Failed to create event';
                }
            }
        }
    }
}

// Handle Delete
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $event_id = (int)$_GET['id'];
    $stmt = $db->prepare("DELETE FROM events WHERE event_id = ?");
    if ($stmt->execute([$event_id])) {
        log_activity(get_user_id(), 'EVENT_DELETE', "Deleted event ID: $event_id");
        $success = 'Event deleted successfully!';
    } else {
        $errors[] = 'Failed to delete event';
    }
}

// Get filter parameters
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$month_filter = isset($_GET['month']) ? $_GET['month'] : '';

// Build query
$query = "SELECT e.*, b.branch_name, u.full_name as created_by_name
          FROM events e
          LEFT JOIN branches b ON e.branch_id = b.branch_id
          LEFT JOIN users u ON e.created_by = u.user_id
          WHERE 1=1";

$params = [];

if ($type_filter) {
    $query .= " AND e.event_type = ?";
    $params[] = $type_filter;
}

if ($month_filter) {
    $query .= " AND DATE_FORMAT(e.event_date, '%Y-%m') = ?";
    $params[] = $month_filter;
}

$query .= " ORDER BY e.event_date DESC, e.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$events = $stmt->fetchAll();

// Get branches for dropdown
$branches = get_branches();

// Check if we're editing an event
$edit_event = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $event_id = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT * FROM events WHERE event_id = ?");
    $stmt->execute([$event_id]);
    $edit_event = $stmt->fetch();
}

$page_title = 'Manage Events - Admin Panel';
include '../includes/header.php';
?>

<div class="container-fluid mt-4 mb-5">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Manage Events</li>
                </ol>
            </nav>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">
                    <i class="bi bi-calendar-event-fill text-success"></i> Manage Events
                </h2>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#eventModal">
                    <i class="bi bi-plus-circle"></i> Add New Event
                </button>
            </div>

            <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Event Type</label>
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="course_start" <?php echo $type_filter == 'course_start' ? 'selected' : ''; ?>>Course Start</option>
                        <option value="exam" <?php echo $type_filter == 'exam' ? 'selected' : ''; ?>>Exam</option>
                        <option value="workshop" <?php echo $type_filter == 'workshop' ? 'selected' : ''; ?>>Workshop</option>
                        <option value="seminar" <?php echo $type_filter == 'seminar' ? 'selected' : ''; ?>>Seminar</option>
                        <option value="holiday" <?php echo $type_filter == 'holiday' ? 'selected' : ''; ?>>Holiday</option>
                        <option value="job_fair" <?php echo $type_filter == 'job_fair' ? 'selected' : ''; ?>>Job Fair</option>
                        <option value="other" <?php echo $type_filter == 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Month</label>
                    <input type="month" name="month" class="form-control" value="<?php echo htmlspecialchars($month_filter); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-filter"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Events Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0">Events List (<?php echo count($events); ?> events)</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($events)): ?>
            <div class="text-center py-5">
                <i class="bi bi-calendar-x fs-1 text-muted"></i>
                <p class="text-muted mt-3">No events found</p>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#eventModal">
                    <i class="bi bi-plus-circle"></i> Create First Event
                </button>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Event Title</th>
                            <th>Type</th>
                            <th>Date & Time</th>
                            <th>Location</th>
                            <th>Branch</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $event_type_colors = [
                            'course_start' => 'primary',
                            'exam' => 'danger',
                            'workshop' => 'info',
                            'seminar' => 'success',
                            'holiday' => 'warning',
                            'job_fair' => 'warning',
                            'other' => 'secondary'
                        ];
                        
                        foreach ($events as $event): 
                            $color = isset($event_type_colors[$event['event_type']]) ? $event_type_colors[$event['event_type']] : 'secondary';
                            $is_past = strtotime($event['event_date']) < strtotime('today');
                        ?>
                        <tr class="<?php echo $is_past ? 'table-secondary' : ''; ?>">
                            <td><strong>#<?php echo $event['event_id']; ?></strong></td>
                            <td>
                                <strong><?php echo htmlspecialchars($event['event_title']); ?></strong>
                                <?php if ($is_past): ?>
                                <span class="badge bg-secondary">Past</span>
                                <?php endif; ?>
                                <?php if ($event['description']): ?>
                                <br><small class="text-muted"><?php echo substr(htmlspecialchars($event['description']), 0, 50); ?>...</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $color; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $event['event_type'])); ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo format_date($event['event_date']); ?></strong><br>
                                <?php if ($event['start_time']): ?>
                                <small class="text-muted">
                                    <i class="bi bi-clock"></i> 
                                    <?php echo date('g:i A', strtotime($event['start_time'])); ?>
                                    <?php if ($event['end_time']): ?>
                                    - <?php echo date('g:i A', strtotime($event['end_time'])); ?>
                                    <?php endif; ?>
                                </small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($event['location']); ?></td>
                            <td>
                                <?php if ($event['branch_name']): ?>
                                    <?php echo htmlspecialchars($event['branch_name']); ?>
                                <?php else: ?>
                                    <span class="text-muted">All Branches</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($event['created_by_name']); ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" 
                                        data-bs-target="#editModal<?php echo $event['event_id']; ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <a href="?action=delete&id=<?php echo $event['event_id']; ?>" 
                                   class="btn btn-sm btn-outline-danger"
                                   onclick="return confirm('Are you sure you want to delete this event?')">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>

                        <!-- Edit Modal for each event -->
                        <div class="modal fade" id="editModal<?php echo $event['event_id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header bg-primary text-white">
                                        <h5 class="modal-title">Edit Event: <?php echo htmlspecialchars($event['event_title']); ?></h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                                            
                                            <div class="row">
                                                <div class="col-md-8 mb-3">
                                                    <label class="form-label">Event Title *</label>
                                                    <input type="text" name="event_title" class="form-control" required 
                                                           value="<?php echo htmlspecialchars($event['event_title']); ?>">
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">Event Type *</label>
                                                    <select name="event_type" class="form-select" required>
                                                        <option value="course_start" <?php echo $event['event_type'] == 'course_start' ? 'selected' : ''; ?>>Course Start</option>
                                                        <option value="exam" <?php echo $event['event_type'] == 'exam' ? 'selected' : ''; ?>>Exam</option>
                                                        <option value="workshop" <?php echo $event['event_type'] == 'workshop' ? 'selected' : ''; ?>>Workshop</option>
                                                        <option value="seminar" <?php echo $event['event_type'] == 'seminar' ? 'selected' : ''; ?>>Seminar</option>
                                                        <option value="holiday" <?php echo $event['event_type'] == 'holiday' ? 'selected' : ''; ?>>Holiday</option>
                                                        <option value="job_fair" <?php echo $event['event_type'] == 'job_fair' ? 'selected' : ''; ?>>Job Fair</option>
                                                        <option value="other" <?php echo $event['event_type'] == 'other' ? 'selected' : ''; ?>>Other</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Description</label>
                                                <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($event['description']); ?></textarea>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">Event Date *</label>
                                                    <input type="date" name="event_date" class="form-control" required 
                                                           value="<?php echo $event['event_date']; ?>">
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">Start Time</label>
                                                    <input type="time" name="start_time" class="form-control" 
                                                           value="<?php echo $event['start_time']; ?>">
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">End Time</label>
                                                    <input type="time" name="end_time" class="form-control" 
                                                           value="<?php echo $event['end_time']; ?>">
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Location</label>
                                                    <input type="text" name="location" class="form-control" 
                                                           value="<?php echo htmlspecialchars($event['location']); ?>">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Branch</label>
                                                    <select name="branch_id" class="form-select">
                                                        <option value="">All Branches</option>
                                                        <?php foreach ($branches as $branch): ?>
                                                        <option value="<?php echo $branch['branch_id']; ?>" 
                                                                <?php echo $event['branch_id'] == $branch['branch_id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($branch['branch_name']); ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="save_event" class="btn btn-primary">
                                                <i class="bi bi-save"></i> Update Event
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Event Modal -->
<div class="modal fade" id="eventModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Add New Event</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Event Title *</label>
                            <input type="text" name="event_title" class="form-control" required 
                                   placeholder="Enter event title">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Event Type *</label>
                            <select name="event_type" class="form-select" required>
                                <option value="">Select Type</option>
                                <option value="course_start">Course Start</option>
                                <option value="exam">Exam</option>
                                <option value="workshop">Workshop</option>
                                <option value="seminar">Seminar</option>
                                <option value="holiday">Holiday</option>
                                <option value="job_fair">Job Fair</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" 
                                  placeholder="Enter event description"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Event Date *</label>
                            <input type="date" name="event_date" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Start Time</label>
                            <input type="time" name="start_time" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">End Time</label>
                            <input type="time" name="end_time" class="form-control">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-control" 
                                   placeholder="e.g., Main Hall, Lab A1">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Branch</label>
                            <select name="branch_id" class="form-select">
                                <option value="">All Branches</option>
                                <?php foreach ($branches as $branch): ?>
                                <option value="<?php echo $branch['branch_id']; ?>">
                                    <?php echo htmlspecialchars($branch['branch_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Leave blank for all branches</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_event" class="btn btn-success">
                        <i class="bi bi-plus-circle"></i> Create Event
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>