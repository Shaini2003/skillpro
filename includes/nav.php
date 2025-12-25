<?php
// includes/nav.php

// Ensure config is loaded if this file is accessed directly or included from a subdirectory
if (!defined('SITE_URL')) {
    $config_path = file_exists('config.php') ? 'config.php' : (file_exists('../config.php') ? '../config.php' : '../../config.php');
    if (file_exists($config_path)) {
        require_once $config_path;
    }
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="<?= defined('SITE_URL') ? SITE_URL : '/skillpro' ?>/index.php">
            <i class="bi bi-mortarboard-fill fs-4"></i> 
            <span>SkillPro</span>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>" href="<?= SITE_URL ?>/index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'courses.php' ? 'active' : '' ?>" href="<?= SITE_URL ?>/courses.php">Courses</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'notices.php' ? 'active' : '' ?>" href="<?= SITE_URL ?>/notices.php">Notices</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'events.php' ? 'active' : '' ?>" href="<?= SITE_URL ?>/events.php">Events</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active' : '' ?>" href="<?= SITE_URL ?>/contact.php">Contact</a>
                </li>
            </ul>

            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">
                <?php if (function_exists('is_logged_in') && is_logged_in()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active d-flex align-items-center gap-2" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="bg-white text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                <i class="bi bi-person-fill"></i>
                            </div>
                            <span class="d-none d-lg-inline fw-semibold"><?= htmlspecialchars(get_user_name()) ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 animate slideIn" aria-labelledby="userDropdown">
                            <li><h6 class="dropdown-header text-uppercase small text-muted">Signed in as <br><strong class="text-dark"><?= ucfirst(get_user_role()) ?></strong></h6></li>
                            <li><hr class="dropdown-divider"></li>
                            
                            <li>
                                <a class="dropdown-item" href="<?= SITE_URL ?>/<?= get_user_role() ?>/index.php">
                                    <i class="bi bi-speedometer2 me-2 text-primary"></i> Dashboard
                                </a>
                            </li>
                            
                            <?php if (get_user_role() == 'student'): ?>
                            <li>
                                <a class="dropdown-item" href="<?= SITE_URL ?>/student/profile.php">
                                    <i class="bi bi-person-gear me-2 text-info"></i> My Profile
                                </a>
                            </li>
                            <?php endif; ?>

                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?= SITE_URL ?>/logout.php">
                                    <i class="bi bi-box-arrow-right me-2"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item me-2">
                        <a class="nav-link" href="<?= SITE_URL ?>/login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-light text-primary fw-bold px-4 rounded-pill shadow-sm hover-lift" href="<?= SITE_URL ?>/register.php">Register</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>