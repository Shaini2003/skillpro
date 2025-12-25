// Form Validation
document.addEventListener('DOMContentLoaded', function() {
    // Add validation to all forms
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
    
    // Password strength indicator
    const passwordInputs = document.querySelectorAll('input[type="password"]');
    passwordInputs.forEach(input => {
        input.addEventListener('input', function() {
            checkPasswordStrength(this);
        });
    });
    
    // Confirm before delete
    const deleteButtons = document.querySelectorAll('.btn-delete');
    deleteButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this item?')) {
                e.preventDefault();
            }
        });
    });
    
    // Auto-hide alerts
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
});

// Password Strength Checker
function checkPasswordStrength(input) {
    const password = input.value;
    let strength = 0;
    
    if (password.length >= 6) strength++;
    if (password.length >= 10) strength++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
    if (/\d/.test(password)) strength++;
    if (/[^a-zA-Z\d]/.test(password)) strength++;
    
    // Display strength (implement UI indicator as needed)
    const strengthText = ['Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
    console.log('Password strength:', strengthText[strength - 1] || 'Too short');
}

// AJAX Course Search
function searchCourses(query) {
    fetch(`api/search_courses.php?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            displaySearchResults(data);
        })
        .catch(error => console.error('Search error:', error));
}

// Display Search Results
function displaySearchResults(courses) {
    const container = document.getElementById('searchResults');
    if (!container) return;
    
    container.innerHTML = '';
    
    courses.forEach(course => {
        const card = createCourseCard(course);
        container.appendChild(card);
    });
}

// Create Course Card
function createCourseCard(course) {
    const col = document.createElement('div');
    col.className = 'col-md-4 mb-4';
    
    col.innerHTML = `
        <div class="card h-100 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <span class="badge bg-primary">${course.category_name}</span>
                    <span class="badge bg-info">${course.mode}</span>
                </div>
                <h5 class="card-title">${course.course_name}</h5>
                <p class="text-muted small">
                    <i class="bi bi-geo-alt-fill"></i> ${course.branch_name}
                </p>
                <p class="card-text">${course.description.substring(0, 100)}...</p>
                <div class="d-flex justify-content-between mt-3">
                    <div>
                        <small class="text-muted">Duration:</small>
                        <strong class="d-block">${course.duration}</strong>
                    </div>
                    <div class="text-end">
                        <small class="text-muted">Fee:</small>
                        <strong class="d-block text-success">Rs. ${parseFloat(course.fee).toLocaleString()}</strong>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white">
                <a href="course_details.php?id=${course.course_id}" class="btn btn-outline-primary w-100">View Details</a>
            </div>
        </div>
    `;
    
    return col;
}

// Real-time Form Validation
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function validatePhone(phone) {
    const re = /^[0-9]{10}$/;
    return re.test(phone.replace(/\s/g, ''));
}

// Enrollment Confirmation
function confirmEnrollment(courseId) {
    if (confirm('Are you sure you want to enroll in this course?')) {
        fetch('api/enroll_course.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ course_id: courseId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Enrollment successful!');
                location.reload();
            } else {
                alert('Enrollment failed: ' + data.message);
            }
        })
        .catch(error => console.error('Enrollment error:', error));
    }
}

// File Upload Preview
function previewFile(input) {
    const file = input.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('filePreview');
            if (preview) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
        };
        reader.readAsDataURL(file);
    }
}

// Chart.js Integration (if needed for dashboard)
function createEnrollmentChart(data) {
    const ctx = document.getElementById('enrollmentChart');
    if (!ctx) return;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Enrollments',
                data: data.values,
                borderColor: 'rgb(102, 126, 234)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                }
            }
        }
    });
}

// Initialize tooltips (Bootstrap)
const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});

// Print functionality
function printPage() {
    window.print();
}

// Export to PDF (basic implementation)
function exportToPDF() {
    window.print();
}