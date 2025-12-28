<?php
require_once '../config.php';
require_role('student');

$student_id = get_user_id();
$db = Database::getInstance()->getConnection();
$success = '';
$errors = [];

// Handle profile update (Info + Photo)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Invalid request';
    } else {
        $full_name = sanitize_input($_POST['full_name']);
        $email = sanitize_input($_POST['email']);
        $phone = sanitize_input($_POST['phone']);
        $branch_id = (int)$_POST['branch_id'];
        
        // Validation
        if (strlen($full_name) < 3) $errors[] = 'Full name must be at least 3 characters';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address';
        
        // Check email uniqueness
        $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $stmt->execute([$email, $student_id]);
        if ($stmt->fetch()) $errors[] = 'Email already in use';
        
        // Handle Photo Upload
        $profile_image = null;
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png'];
            $filename = $_FILES['profile_image']['name'];
            $filetype = pathinfo($filename, PATHINFO_EXTENSION);
            $filesize = $_FILES['profile_image']['size'];

            if (!in_array(strtolower($filetype), $allowed)) {
                $errors[] = "Only JPG, JPEG, and PNG files are allowed.";
            } elseif ($filesize > 2 * 1024 * 1024) { // 2MB Limit
                $errors[] = "File size must be less than 2MB.";
            } else {
                // Create upload directory if not exists
                $target_dir = "../uploads/profiles/";
                if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);

                $new_filename = "user_" . $student_id . "_" . time() . "." . $filetype;
                $target_file = $target_dir . $new_filename;

                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
                    $profile_image = "uploads/profiles/" . $new_filename;
                } else {
                    $errors[] = "Failed to upload image.";
                }
            }
        }

        if (empty($errors)) {
            // Build Update Query
            $sql = "UPDATE users SET full_name = ?, email = ?, phone = ?, branch_id = ?";
            $params = [$full_name, $email, $phone, $branch_id];

            // If new image uploaded, add to query
            if ($profile_image) {
                $sql .= ", profile_image = ?";
                $params[] = $profile_image;
            }

            $sql .= " WHERE user_id = ?";
            $params[] = $student_id;

            $stmt = $db->prepare($sql);
            if ($stmt->execute($params)) {
                $_SESSION['full_name'] = $full_name;
                // Update session image if changed
                if ($profile_image) $_SESSION['profile_image'] = $profile_image;
                
                log_activity($student_id, 'PROFILE_UPDATE', 'Profile information updated');
                $success = 'Profile updated successfully!';
            } else {
                $errors[] = 'Failed to update profile';
            }
        }
    }
}

// Handle password change (Kept same as before)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Invalid request';
    } else {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $stmt->execute([$student_id]);
        $user = $stmt->fetch();
        
        if (!password_verify($current_password, $user['password_hash'])) {
            $errors[] = 'Current password is incorrect';
        } elseif (strlen($new_password) < 6) {
            $errors[] = 'New password must be at least 6 characters';
        } elseif ($new_password !== $confirm_password) {
            $errors[] = 'New passwords do not match';
        } else {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            if ($stmt->execute([$new_hash, $student_id])) {
                log_activity($student_id, 'PASSWORD_CHANGE', 'Password changed');
                $success = 'Password changed successfully!';
            } else {
                $errors[] = 'Failed to change password';
            }
        }
    }
}

// Get student data
$stmt = $db->prepare("SELECT u.*, b.branch_name FROM users u 
                      LEFT JOIN branches b ON u.branch_id = b.branch_id 
                      WHERE u.user_id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

// Get enrollment statistics
$stmt = $db->prepare("SELECT 
                      COUNT(*) as total_enrollments,
                      SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as active_courses,
                      SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_courses
                      FROM enrollments WHERE student_id = ?");
$stmt->execute([$student_id]);
$stats = $stmt->fetch();

$branches = get_branches();
$page_title = 'My Profile - SkillPro Institute';
include '../includes/header.php';
?>

<div class="container mt-5 mb-5">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">My Profile</li>
                </ol>
            </nav>

            <h2 class="mb-4">
                <i class="bi bi-person-circle text-primary"></i> My Profile
            </h2>

            <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill"></i> <?= $success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                    <li><?= $error ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    
                    <div class="position-relative d-inline-block mb-3">
                        <?php if (!empty($student['profile_image'])): ?>
                            <img src="../<?= htmlspecialchars($student['profile_image']) ?>" 
                                 alt="Profile" 
                                 class="rounded-circle border" 
                                 style="width: 120px; height: 120px; object-fit: cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" 
                                 style="width: 120px; height: 120px; font-size: 48px;">
                                <?= strtoupper(substr($student['full_name'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        
                        <label for="profile_upload" class="position-absolute bottom-0 end-0 bg-white border rounded-circle p-2 shadow-sm" style="cursor: pointer;">
                            <i class="bi bi-camera-fill text-primary"></i>
                        </label>
                    </div>

                    <h4><?= htmlspecialchars($student['full_name']) ?></h4>
                    <p class="text-muted mb-1">@<?= htmlspecialchars($student['username']) ?></p>
                    <p class="text-muted">
                        <i class="bi bi-building"></i> <?= htmlspecialchars($student['branch_name'] ?? 'Main Branch') ?>
                    </p>
                    
                    <div class="border-top pt-3 mt-3">
                        <div class="row text-center">
                            <div class="col-4">
                                <h5 class="text-primary mb-0"><?= $stats['total_enrollments'] ?></h5>
                                <small class="text-muted">Total</small>
                            </div>
                            <div class="col-4">
                                <h5 class="text-success mb-0"><?= $stats['active_courses'] ?></h5>
                                <small class="text-muted">Active</small>
                            </div>
                            <div class="col-4">
                                <h5 class="text-info mb-0"><?= $stats['completed_courses'] ?></h5>
                                <small class="text-muted">Completed</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mt-4">
                <div class="card-body">
                    <h6 class="mb-3">Account Information</h6>
                    <p class="mb-2">
                        <i class="bi bi-shield-check text-success"></i>
                        <strong>Status:</strong> 
                        <span class="badge bg-<?= $student['status'] == 'active' ? 'success' : 'warning' ?>">
                            <?= ucfirst($student['status']) ?>
                        </span>
                    </p>
                    <p class="mb-2">
                        <i class="bi bi-calendar-check text-primary"></i>
                        <strong>Member Since:</strong> <?= format_date($student['created_at']) ?>
                    </p>
                    <?php if ($student['last_login']): ?>
                    <p class="mb-0">
                        <i class="bi bi-clock text-info"></i>
                        <strong>Last Login:</strong> <?= format_datetime($student['last_login']) ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-pencil-square"></i> Update Profile Information
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <input type="file" name="profile_image" id="profile_upload" class="d-none" accept="image/png, image/jpeg">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($student['username']) ?>" disabled>
                                <small class="text-muted">Username cannot be changed</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Full Name *</label>
                                <input type="text" name="full_name" class="form-control" required 
                                       value="<?= htmlspecialchars($student['full_name']) ?>">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Email Address *</label>
                                <input type="email" name="email" class="form-control" required 
                                       value="<?= htmlspecialchars($student['email']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number *</label>
                                <input type="tel" name="phone" class="form-control" required 
                                       value="<?= htmlspecialchars($student['phone']) ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Preferred Branch *</label>
                            <select name="branch_id" class="form-select" required>
                                <?php foreach ($branches as $branch): ?>
                                <option value="<?= $branch['branch_id'] ?>" 
                                        <?= $student['branch_id'] == $branch['branch_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($branch['branch_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Change Profile Photo</label>
                            <input type="file" name="profile_image" class="form-control" accept="image/*">
                            <small class="text-muted">Allowed: JPG, PNG. Max size: 2MB.</small>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="bi bi-save"></i> Update Profile
                        </button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-key"></i> Change Password
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Current Password *</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">New Password *</label>
                            <input type="password" name="new_password" class="form-control" required minlength="6">
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password *</label>
                            <input type="password" name="confirm_password" class="form-control" required minlength="6">
                        </div>
                        
                        <button type="submit" name="change_password" class="btn btn-danger">
                            <i class="bi bi-shield-lock"></i> Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>