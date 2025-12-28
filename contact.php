<?php
require_once 'config.php';

$success = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate CSRF token
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $name = sanitize_input($_POST['name']);
        $email = sanitize_input($_POST['email']);
        $phone = sanitize_input($_POST['phone']);
        $subject = sanitize_input($_POST['subject']);
        $message = sanitize_input($_POST['message']);
        
        // Validation
        if (empty($name) || strlen($name) < 3) {
            $errors[] = 'Name must be at least 3 characters long';
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address';
        }
        
        if (empty($phone) || !preg_match('/^[0-9]{10}$/', str_replace(' ', '', $phone))) {
            $errors[] = 'Invalid phone number (must be 10 digits)';
        }
        
        if (empty($subject) || strlen($subject) < 5) {
            $errors[] = 'Subject must be at least 5 characters long';
        }
        
        if (empty($message) || strlen($message) < 20) {
            $errors[] = 'Message must be at least 20 characters long';
        }
        
        // If no errors, save to database
        if (empty($errors)) {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("INSERT INTO inquiries (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$name, $email, $phone, $subject, $message])) {
                $success = 'Thank you for contacting us! We will respond to your inquiry within 24-48 hours.';
                
                // Clear form
                $_POST = [];
            } else {
                $errors[] = 'Failed to submit inquiry. Please try again.';
            }
        }
    }
}

$branches = get_branches();
$page_title = 'Contact Us - SkillPro Institute';
include 'includes/header.php';
?>

<div class="container mt-5 mb-5">
    <div class="row">
        <div class="col-12 text-center mb-5">
            <h2 class="fw-bold">Contact Us</h2>
            <p class="text-muted">Get in touch with us for any inquiries or assistance</p>
        </div>
    </div>

    <div class="row">
        <!-- Contact Form -->
        <div class="col-lg-7 mb-4">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h4 class="mb-4">
                        <i class="bi bi-envelope-fill text-primary"></i> Send us a Message
                    </h4>
                    
                    <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill"></i> <?= $success ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                            <li><?= $error ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="contactForm">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" name="name" class="form-control" required 
                                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                                       placeholder="Enter your full name">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address *</label>
                                <input type="email" name="email" class="form-control" required 
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                       placeholder="your.email@example.com">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number *</label>
                                <input type="tel" name="phone" class="form-control" required 
                                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                                       placeholder="07XXXXXXXX">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Subject *</label>
                                <select name="subject" class="form-select" required>
                                    <option value="">Select Subject</option>
                                    <option value="Course Inquiry" <?= ($_POST['subject'] ?? '') == 'Course Inquiry' ? 'selected' : '' ?>>Course Inquiry</option>
                                    <option value="Enrollment Process" <?= ($_POST['subject'] ?? '') == 'Enrollment Process' ? 'selected' : '' ?>>Enrollment Process</option>
                                    <option value="Fee Payment" <?= ($_POST['subject'] ?? '') == 'Fee Payment' ? 'selected' : '' ?>>Fee Payment</option>
                                    <option value="Certification" <?= ($_POST['subject'] ?? '') == 'Certification' ? 'selected' : '' ?>>Certification</option>
                                    <option value="Technical Support" <?= ($_POST['subject'] ?? '') == 'Technical Support' ? 'selected' : '' ?>>Technical Support</option>
                                    <option value="Job Opportunities" <?= ($_POST['subject'] ?? '') == 'Job Opportunities' ? 'selected' : '' ?>>Job Opportunities</option>
                                    <option value="Other" <?= ($_POST['subject'] ?? '') == 'Other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Message *</label>
                            <textarea name="message" class="form-control" rows="6" required 
                                      placeholder="Please describe your inquiry in detail..."><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                            <small class="text-muted">Minimum 20 characters</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg px-5">
                            <i class="bi bi-send-fill"></i> Submit Inquiry
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Contact Information -->
        <div class="col-lg-5">
            <!-- Quick Contact -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="mb-4">
                        <i class="bi bi-info-circle-fill text-primary"></i> Quick Contact
                    </h5>
                    
                    <div class="mb-3">
                        <h6 class="text-muted mb-2">
                            <i class="bi bi-telephone-fill text-success"></i> Phone
                        </h6>
                        <p class="mb-0">General Inquiries: <strong>0112 345 678</strong></p>
                        <p class="mb-0">Admissions: <strong>0112 345 679</strong></p>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="text-muted mb-2">
                            <i class="bi bi-envelope-fill text-danger"></i> Email
                        </h6>
                        <p class="mb-0">info@skillpro.lk</p>
                        <p class="mb-0">admissions@skillpro.lk</p>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="text-muted mb-2">
                            <i class="bi bi-clock-fill text-warning"></i> Office Hours
                        </h6>
                        <p class="mb-0">Monday - Friday: 8:00 AM - 5:00 PM</p>
                        <p class="mb-0">Saturday: 9:00 AM - 1:00 PM</p>
                        <p class="mb-0 text-danger">Sunday: Closed</p>
                    </div>
                    
                    <div>
                        <h6 class="text-muted mb-2">
                            <i class="bi bi-share-fill text-info"></i> Social Media
                        </h6>
                        <div class="d-flex gap-2">
                            <a href="#" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-facebook"></i>
                            </a>
                            <a href="#" class="btn btn-outline-info btn-sm">
                                <i class="bi bi-twitter"></i>
                            </a>
                            <a href="#" class="btn btn-outline-danger btn-sm">
                                <i class="bi bi-youtube"></i>
                            </a>
                            <a href="#" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-linkedin"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Branch Locations -->
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="mb-4">
                        <i class="bi bi-geo-alt-fill text-danger"></i> Our Branches
                    </h5>
                    
                    <?php foreach ($branches as $branch): ?>
                    <div class="mb-3 pb-3 <?= $branch !== end($branches) ? 'border-bottom' : '' ?>">
                        <h6 class="text-primary mb-2">
                            <i class="bi bi-building"></i> <?= htmlspecialchars($branch['branch_name']) ?>
                        </h6>
                        <p class="text-muted small mb-1">
                            <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($branch['address']) ?>
                        </p>
                        <p class="text-muted small mb-1">
                            <i class="bi bi-telephone"></i> <?= htmlspecialchars($branch['phone']) ?>
                        </p>
                        <p class="text-muted small mb-0">
                            <i class="bi bi-envelope"></i> <?= htmlspecialchars($branch['email']) ?>
                        </p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- FAQ Section -->
    <div class="row mt-5">
        <div class="col-12">
            <h3 class="text-center mb-4">Frequently Asked Questions</h3>
        </div>
        <div class="col-lg-6 mb-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-primary mb-2">
                        <i class="bi bi-question-circle-fill"></i> How do I enroll in a course?
                    </h6>
                    <p class="text-muted mb-0">
                        First, register for a student account on our website. Once registered, you can browse available courses and click "Enroll Now" on your desired course. Our admissions team will review your application within 24 hours.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-primary mb-2">
                        <i class="bi bi-question-circle-fill"></i> What payment methods are accepted?
                    </h6>
                    <p class="text-muted mb-0">
                        We accept cash payments at our branch offices, bank transfers, and online payments. Installment payment plans are available for selected courses. Contact our admissions office for more details.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-primary mb-2">
                        <i class="bi bi-question-circle-fill"></i> Are the certificates recognized?
                    </h6>
                    <p class="text-muted mb-0">
                        Yes! All our courses are registered under the Tertiary and Vocational Education Commission (TVEC) of Sri Lanka. Upon successful completion, you will receive a nationally recognized TVEC-approved certificate.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-primary mb-2">
                        <i class="bi bi-question-circle-fill"></i> Can I transfer between branches?
                    </h6>
                    <p class="text-muted mb-0">
                        Branch transfers may be possible depending on course availability and seat capacity. Please submit a request through your student portal or contact the admissions office at your current branch.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Map Section (Optional) -->
   <div class="row mt-5">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="p-3 border-bottom bg-light rounded-top">
                        <h5 class="mb-0">
                            <i class="bi bi-geo-alt-fill text-danger"></i> Visit Our Main Campus
                        </h5>
                        <small class="text-muted">123 Galle Road, Colombo 03, Sri Lanka</small>
                    </div>

                    <div class="ratio ratio-21x9">
                        <iframe 
                            src="https://www.google.com/maps?q=123+Galle+Road,+Colombo+03,+Sri+Lanka&output=embed" 
                            width="100%" 
                            height="450" 
                            style="border:0;" 
                            allowfullscreen="" 
                            loading="lazy" 
                            referrerpolicy="no-referrer-when-downgrade">
                        </iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Form Validation Script -->
<script>
document.getElementById('contactForm').addEventListener('submit', function(e) {
    const message = document.querySelector('textarea[name="message"]').value;
    
    if (message.length < 20) {
        e.preventDefault();
        alert('Message must be at least 20 characters long');
    }
});
</script>

<?php include 'includes/footer.php'; ?>