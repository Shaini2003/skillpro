<?php
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password'];
    
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['branch_id'] = $user['branch_id'];
            
            // Update last login
            $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            $stmt->execute([$user['user_id']]);
            
            log_activity($user['user_id'], 'LOGIN', 'User logged in');
            
            // Redirect based on role
            switch ($user['role']) {
                case 'admin':
                    redirect('admin/index.php');
                    break;
                case 'instructor':
                    redirect('instructor/index.php');
                    break;
                case 'student':
                    redirect('student/index.php');
                    break;
            }
        } else {
            $error = 'Invalid username or password';
        }
    }
}

include 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow">
                <div class="card-body p-5">
                    <h3 class="text-center mb-4">Login to SkillPro</h3>
                    
                    <?php if ($error): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" id="loginForm">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember">
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 mb-3">Login</button>
                        
                        <div class="text-center">
                            <p class="mb-0">Don't have an account? <a href="register.php">Register here</a></p>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="mt-3 text-center text-muted">
                <small>
                    <strong>Demo Credentials:</strong><br>
                    Admin: admin / admin123<br>
                    Instructor: instructor1 / 123456<br>
                    Student: Register new account
                </small>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>