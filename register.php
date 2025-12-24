<?php
require_once 'config.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = sanitize_input($_POST['full_name']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $branch_id = (int)$_POST['branch_id'];
    
    // Validation
    if (strlen($username) < 4) {
        $errors[] = 'Username must be at least 4 characters';
    }
    
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    }
    
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match';
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address';
    }
    
    if (empty($errors)) {
        $db = Database::getInstance()->getConnection();
        
        // Check if username exists
        $stmt = $db->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->fetch()) {
            $errors[] = 'Username or email already exists';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("INSERT INTO users (username, password_hash, email, full_name, phone, role, branch_id, status) VALUES (?, ?, ?, ?, ?, 'student', ?, 'active')");
            
            if ($stmt->execute([$username, $password_hash, $email, $full_name, $phone, $branch_id])) {
                $success = 'Registration successful! You can now login.';
                log_activity($db->lastInsertId(), 'REGISTER', 'New student registered');
            } else {
                $errors[] = 'Registration failed. Please try again.';
            }
        }
    }
}

$branches = get_branches();
include 'includes/header.php';
?>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-body p-5">
                    <h3 class="text-center mb-4">Student Registration</h3>
                    
                    <?php if ($success): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                    <?php endif; ?>
                    
                    <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                            <li><?= $error ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="registerForm">
                        <div class="mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="full_name" class="form-control" required value="<?= $_POST['full_name'] ?? '' ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" required value="<?= $_POST['email'] ?? '' ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Phone *</label>
                            <input type="tel" name="phone" class="form-control" required value="<?= $_POST['phone'] ?? '' ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Preferred Branch *</label>
                            <select name="branch_id" class="form-select" required>
                                <option value="">Select Branch</option>
                                <?php foreach ($branches as $branch): ?>
                                <option value="<?= $branch['branch_id'] ?>"><?= htmlspecialchars($branch['branch_name']) ?> - <?= htmlspecialchars($branch['location']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Username *</label>
                            <input type="text" name="username" class="form-control" required value="<?= $_POST['username'] ?? '' ?>">
                            <small class="text-muted">Minimum 4 characters</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Password *</label>
                            <input type="password" name="password" class="form-control" required>
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Confirm Password *</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 mb-3">Register</button>
                        
                        <div class="text-center">
                            <p class="mb-0">Already have an account? <a href="login.php">Login here</a></p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>