<?php
$page_title = "Post a Job";
require_once 'includes/db.php';
require_once 'includes/session.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $company_name = trim($_POST['company_name']);
    $job_type = $_POST['job_type'];
    $location = trim($_POST['location']);
    $salary_range = trim($_POST['salary_range']);
    $description = trim($_POST['description']);
    $apply_link = trim($_POST['apply_link']);
    
    if (empty($title) || empty($job_type) || empty($description)) {
        $error = "Title, Job Type, and Description are required.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO jobs (user_id, title, company_name, job_type, location, description, salary_range, apply_link) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$user_id, $title, $company_name, $job_type, $location, $description, $salary_range, $apply_link])) {
            setFlashMessage('success', 'Job posted successfully!');
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Database error. Please try again.";
        }
    }
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card card-custom p-4 border-top border-4 border-primary shadow-sm">
                <h3 class="fw-bold mb-4 text-primary"><i class="fa-solid fa-briefcase me-2"></i>Post a Job</h3>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label>Job Title *</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label>Company/Organization Name</label>
                            <input type="text" name="company_name" class="form-control">
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label>Job Type *</label>
                            <select name="job_type" class="form-select" required>
                                <option value="">Select</option>
                                <option value="Private">Private</option>
                                <option value="Government">Government</option>
                                <option value="Apprenticeship">Apprenticeship</option>
                                <option value="Skill Development">Skill Development</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>Location</label>
                            <input type="text" name="location" class="form-control" placeholder="e.g. Pune, Remote">
                        </div>
                        <div class="col-md-4">
                            <label>Salary/Stipend Range</label>
                            <input type="text" name="salary_range" class="form-control" placeholder="e.g. ₹20k - ₹30k">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label>Job Description *</label>
                        <textarea name="description" class="form-control" rows="5" required></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label>Apply Link or Email</label>
                        <input type="text" name="apply_link" class="form-control" placeholder="URL or Email address for candidates">
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">Post Job</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
