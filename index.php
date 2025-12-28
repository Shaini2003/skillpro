<?php
require_once 'config.php';

// Fetch featured courses
$db = Database::getInstance()->getConnection();
$stmt = $db->query("SELECT c.*, cat.category_name, b.branch_name, u.full_name as instructor_name 
                    FROM courses c 
                    LEFT JOIN categories cat ON c.category_id = cat.category_id
                    LEFT JOIN branches b ON c.branch_id = b.branch_id
                    LEFT JOIN users u ON c.instructor_id = u.user_id
                    WHERE c.status = 'active' 
                    ORDER BY c.created_at DESC LIMIT 6");
$featured_courses = $stmt->fetchAll();

// Fetch recent notices
$stmt = $db->query("SELECT * FROM notices 
                    WHERE status = 'published' 
                    AND (expiry_date IS NULL OR expiry_date >= CURDATE())
                    ORDER BY priority DESC, publish_date DESC LIMIT 5");
$notices = $stmt->fetchAll();

// Fetch upcoming events
$stmt = $db->query("SELECT * FROM events 
                    WHERE event_date >= CURDATE()
                    ORDER BY event_date ASC LIMIT 5");
$events = $stmt->fetchAll();

include 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section bg-primary text-white py-5" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 500px;">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-4">Empower Your Future with SkillPro Institute</h1>
                <p class="lead mb-4">Join Sri Lanka's premier vocational training institute. Gain industry-relevant skills in IT, Engineering, Hospitality, and more.</p>
                <div class="d-flex gap-3">
                    <a href="courses.php" class="btn btn-light btn-lg px-4">Browse Courses</a>
                    <a href="register.php" class="btn btn-outline-light btn-lg px-4">Register Now</a>
                </div>
                <div class="mt-4">
                    <div class="d-flex gap-4 text-white">
                        <div>
                            <h3 class="mb-0">1000+</h3>
                            <small>Students Enrolled</small>
                        </div>
                        <div>
                            <h3 class="mb-0">50+</h3>
                            <small>Expert Instructors</small>
                        </div>
                        <div>
                            <h3 class="mb-0">30+</h3>
                            <small>Course Programs</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 d-none d-lg-block">
                <img src="assets/images/Educational-Institutions.jpg" alt="Education" class="img-fluid">
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="row text-center">
            <div class="col-md-3 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="mb-3 text-primary">
                            <svg width="48" height="48" fill="currentColor" class="bi bi-laptop" viewBox="0 0 16 16">
                                <path d="M13.5 3a.5.5 0 0 1 .5.5V11H2V3.5a.5.5 0 0 1 .5-.5h11zm-11-1A1.5 1.5 0 0 0 1 3.5V12h14V3.5A1.5 1.5 0 0 0 13.5 2h-11zM0 12.5h16a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 0 12.5z"/>
                            </svg>
                        </div>
                        <h5>Online & On-site Learning</h5>
                        <p class="text-muted mb-0">Flexible learning modes to suit your schedule</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="mb-3 text-success">
                            <svg width="48" height="48" fill="currentColor" class="bi bi-award" viewBox="0 0 16 16">
                                <path d="M9.669.864 8 0 6.331.864l-1.858.282-.842 1.68-1.337 1.32L2.6 6l-.306 1.854 1.337 1.32.842 1.68 1.858.282L8 12l1.669-.864 1.858-.282.842-1.68 1.337-1.32L13.4 6l.306-1.854-1.337-1.32-.842-1.68L9.669.864zm1.196 1.193.684 1.365 1.086 1.072L12.387 6l.248 1.506-1.086 1.072-.684 1.365-1.51.229L8 10.874l-1.355-.702-1.51-.229-.684-1.365-1.086-1.072L3.614 6l-.25-1.506 1.087-1.072.684-1.365 1.51-.229L8 1.126l1.356.702 1.509.229z"/>
                                <path d="M4 11.794V16l4-1 4 1v-4.206l-2.018.306L8 13.126 6.018 12.1 4 11.794z"/>
                            </svg>
                        </div>
                        <h5>Certified Programs</h5>
                        <p class="text-muted mb-0">TVEC approved certification upon completion</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="mb-3 text-warning">
                            <svg width="48" height="48" fill="currentColor" class="bi bi-people" viewBox="0 0 16 16">
                                <path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1h8Zm-7.978-1A.261.261 0 0 1 7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002a.274.274 0 0 1-.014.002H7.022ZM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0ZM6.936 9.28a5.88 5.88 0 0 0-1.23-.247A7.35 7.35 0 0 0 5 9c-4 0-5 3-5 4 0 .667.333 1 1 1h4.216A2.238 2.238 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816ZM4.92 10A5.493 5.493 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275ZM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0Zm3-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z"/>
                            </svg>
                        </div>
                        <h5>Expert Instructors</h5>
                        <p class="text-muted mb-0">Learn from industry professionals</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="mb-3 text-danger">
                            <svg width="48" height="48" fill="currentColor" class="bi bi-briefcase" viewBox="0 0 16 16">
                                <path d="M6.5 1A1.5 1.5 0 0 0 5 2.5V3H1.5A1.5 1.5 0 0 0 0 4.5v8A1.5 1.5 0 0 0 1.5 14h13a1.5 1.5 0 0 0 1.5-1.5v-8A1.5 1.5 0 0 0 14.5 3H11v-.5A1.5 1.5 0 0 0 9.5 1h-3zm0 1h3a.5.5 0 0 1 .5.5V3H6v-.5a.5.5 0 0 1 .5-.5zm1.886 6.914L15 7.151V12.5a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5V7.15l6.614 1.764a1.5 1.5 0 0 0 .772 0zM1.5 4h13a.5.5 0 0 1 .5.5v1.616L8.129 7.948a.5.5 0 0 1-.258 0L1 6.116V4.5a.5.5 0 0 1 .5-.5z"/>
                            </svg>
                        </div>
                        <h5>Career Support</h5>
                        <p class="text-muted mb-0">Job placement assistance and career guidance</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Featured Courses -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Featured Courses</h2>
            <p class="text-muted">Explore our most popular training programs</p>
        </div>
        <div class="row">
            <?php foreach ($featured_courses as $course): ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <span class="badge bg-primary"><?= htmlspecialchars($course['category_name'] ?? 'General') ?></span>
                            <span class="badge bg-info"><?= ucfirst(htmlspecialchars($course['mode'])) ?></span>
                        </div>
                        <h5 class="card-title"><?= htmlspecialchars($course['course_name']) ?></h5>
                        <p class="text-muted small mb-2">
                            <svg width="16" height="16" fill="currentColor" class="bi bi-geo-alt-fill" viewBox="0 0 16 16">
                                <path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10zm0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"/>
                            </svg>
                            <?= htmlspecialchars($course['branch_name']) ?>
                        </p>
                        <p class="card-text"><?= substr(htmlspecialchars($course['description']), 0, 100) ?>...</p>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div>
                                <small class="text-muted">Duration:</small>
                                <strong class="d-block"><?= htmlspecialchars($course['duration']) ?></strong>
                            </div>
                            <div class="text-end">
                                <small class="text-muted">Fee:</small>
                                <strong class="d-block text-success">Rs. <?= number_format($course['fee'], 2) ?></strong>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-top-0">
                        <a href="course_details.php?id=<?= $course['course_id'] ?>" class="btn btn-outline-primary w-100">View Details</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-4">
            <a href="courses.php" class="btn btn-primary btn-lg">View All Courses</a>
        </div>
    </div>
</section>

<!-- Notices & Events -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="row">
            <!-- Notices -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <svg width="20" height="20" fill="currentColor" class="bi bi-megaphone" viewBox="0 0 16 16">
                                <path d="M13 2.5a1.5 1.5 0 0 1 3 0v11a1.5 1.5 0 0 1-3 0v-.214c-2.162-1.241-4.49-1.843-6.912-2.083l.405 2.712A1 1 0 0 1 5.51 15.1h-.098a1 1 0 0 1-.992-.883L3.8 10.4c-2.353.356-3.8 1.364-3.8 1.992v.5a.5.5 0 0 1-1 0v-.5c0-1.157 1.888-2.544 4.777-2.98-.384-2.55-.676-4.816-.676-5.912 0-1.146.776-1.992 1.672-1.992C6.64 1.508 8.883 2.293 11 3.106V2.5z"/>
                            </svg>
                            Latest Notices
                        </h5>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <?php foreach ($notices as $notice): ?>
                        <div class="notice-item mb-3 pb-3 border-bottom">
                            <div class="d-flex justify-content-between">
                                <h6 class="mb-1"><?= htmlspecialchars($notice['title']) ?></h6>
                                <span class="badge bg-<?= $notice['priority'] == 'high' ? 'danger' : ($notice['priority'] == 'medium' ? 'warning' : 'secondary') ?>"><?= ucfirst($notice['priority']) ?></span>
                            </div>
                            <small class="text-muted"><?= format_date($notice['publish_date']) ?></small>
                            <p class="mb-0 mt-2"><?= substr(htmlspecialchars($notice['content']), 0, 150) ?>...</p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="card-footer bg-white text-center">
                        <a href="notices.php" class="btn btn-sm btn-outline-primary">View All Notices</a>
                    </div>
                </div>
            </div>
            
            <!-- Events -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <svg width="20" height="20" fill="currentColor" class="bi bi-calendar-event" viewBox="0 0 16 16">
                                <path d="M11 6.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1z"/>
                                <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/>
                            </svg>
                            Upcoming Events
                        </h5>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <?php foreach ($events as $event): ?>
                        <div class="event-item mb-3 pb-3 border-bottom">
                            <div class="d-flex">
                                <div class="text-center me-3" style="min-width: 60px;">
                                    <div class="bg-success text-white rounded p-2">
                                        <div class="fw-bold"><?= date('d', strtotime($event['event_date'])) ?></div>
                                        <small><?= date('M', strtotime($event['event_date'])) ?></small>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?= htmlspecialchars($event['event_title']) ?></h6>
                                    <small class="text-muted">
                                        <svg width="14" height="14" fill="currentColor" class="bi bi-clock" viewBox="0 0 16 16">
                                            <path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z"/>
                                            <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z"/>
                                        </svg>
                                        <?= date('g:i A', strtotime($event['start_time'])) ?>
                                    </small>
                                    <p class="mb-0 mt-1"><?= htmlspecialchars($event['description'] ?? '') ?></p>
                                    <span class="badge bg-light text-dark mt-1"><?= ucfirst(str_replace('_', ' ', $event['event_type'])) ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="card-footer bg-white text-center">
                        <a href="events.php" class="btn btn-sm btn-outline-success">View Calendar</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="py-5 bg-primary text-white">
    <div class="container text-center">
        <h2 class="fw-bold mb-3">Ready to Start Your Journey?</h2>
        <p class="lead mb-4">Join thousands of students building successful careers with SkillPro Institute</p>
        <div class="d-flex gap-3 justify-content-center">
            <a href="register.php" class="btn btn-light btn-lg px-5">Enroll Now</a>
            <a href="contact.php" class="btn btn-outline-light btn-lg px-5">Contact Us</a>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>