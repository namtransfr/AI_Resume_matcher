<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $position_name = sanitize($_POST['position_name'] ?? '');
    $job_description = sanitize($_POST['job_description'] ?? '');
    $required_skills = sanitize($_POST['required_skills'] ?? '');

    if (!empty($position_name) && !empty($required_skills)) {
        $job_data = [
            'position_name' => $position_name,
            'job_description' => $job_description,
            'required_skills' => $required_skills
        ];

        $result = supabasePOST('jobs', $job_data);

        if ($result['success']) {
            if (isset($result['data']['code']) && $result['data']['code'] === '42501') {
                header("Location: add_job.php?message=Database policy blocked job insert&type=error");
                exit();
            }
            header("Location: add_job.php?message=Job posted successfully!&type=success");
            exit();
        } else {
            header("Location: add_job.php?message=Error posting job. Please try again.&type=error");
            exit();
        }
    } else {
        header("Location: add_job.php?message=Position name and required skills are required.&type=error");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Job - Ascenda</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <style>
        /* page-specific overrides (kept for compatibility) */
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .alert { padding: 1rem; border-radius: var(--radius); margin-bottom: 1rem; }
        .alert-success { background: var(--success-light); color: var(--success); }
        .alert-error { background: var(--danger-light); color: var(--danger); }
        .alert-info { background: var(--gray-100); color: var(--gray-700); }

        .table-container { overflow-x: auto; margin-top: 2rem; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 0.75rem 1rem; border: 1px solid var(--gray-200); }
        .table th { background: var(--gray-100); }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar" id="navbar">
        <div class="nav-container">
            <a href="index.php" class="nav-logo">Ascenda</a>
            <ul class="nav-menu" id="navMenu">
                <li><a href="index.php" class="nav-link">Home</a></li>
                <li><a href="submit-resume.php" class="nav-link">Submit Resume</a></li>
                <li><a href="add_job.php" class="nav-link active">Post Job</a></li>
                <li><a href="candidate_list.php" class="nav-link">Candidates</a></li>
                <li><a href="job_analysis.php" class="nav-link">Job Analysis</a></li>
            </ul>
            <div class="mobile-toggle" id="mobileToggle">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <header class="page-header">
        <h1 class="page-title">Post a New Job</h1>
        <p class="page-subtitle">Add job posting details below</p>
    </header>

    <main class="main-container">
        <section class="form-section">
            <div class="form-container">
                <?php

                // Display success or error messages
                if (isset($_GET['message'])) {
                    $message = htmlspecialchars($_GET['message']);
                    $type = isset($_GET['type']) ? $_GET['type'] : 'success';
                    echo '<div class="alert alert-' . $type . '">' . $message . '</div>';
                }
                ?>

                <div class="form-header">
                    <h2>Post a New Job</h2>
                    <p>Fill in the information below to create a job listing.</p>
                </div>

                <form action="add_job.php" method="POST">
                    <div class="form-group">
                        <label class="form-label" for="position_name">
                            Position Name <span class="required">*</span>
                        </label>
                        <input type="text" id="position_name" name="position_name" class="form-input" required placeholder="e.g., Junior PHP Developer">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="job_description">
                            Job Description
                        </label>
                        <textarea id="job_description" name="job_description" class="form-textarea" placeholder="Describe the job responsibilities and requirements"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="required_skills">
                            Required Skills <span class="required">*</span>
                        </label>
                        <input type="text" id="required_skills" name="required_skills" class="form-input" required placeholder="e.g., PHP, MySQL, HTML, CSS, JavaScript">
                        <div class="form-help">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>Separate skills with commas</span>
                        </div>
                    </div>

                    <div class="form-submit" style="margin-top:1.5rem;">
                        <button type="submit" class="btn-primary">Post Job</button>
                        <a href="candidate_list.php" class="btn-secondary" style="margin-left:1rem;">View Candidates</a>
                    </div>
                </form>

                <?php
                // Display existing jobs
                require_once 'config.php';

                // Fetch jobs from Supabase
                $jobs_result = supabaseGET('jobs', '?order=created_at.desc');

                if ($jobs_result['success'] && !empty($jobs_result['data'])) {
                    echo '<div class="card" style="margin-top:2rem;">';
                    echo '<h2>Existing Jobs</h2>';
                    echo '<div class="table-container"><table class="table">';
                    echo '<tr style="background: #f8f9fa;">';
                    echo '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Position</th>';
                    echo '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Required Skills</th>';
                    echo '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Posted</th>';
                    echo '</tr>';

                    foreach ($jobs_result['data'] as $job) {
                        $created_date = isset($job['created_at']) ? date('Y-m-d', strtotime($job['created_at'])) : 'N/A';
                        echo '<tr>';
                        echo '<td style="padding: 10px; border: 1px solid #ddd;">' . htmlspecialchars($job['position_name'] ?? 'N/A') . '</td>';
                        echo '<td style="padding: 10px; border: 1px solid #ddd;">' . htmlspecialchars($job['required_skills'] ?? 'N/A') . '</td>';
                        echo '<td style="padding: 10px; border: 1px solid #ddd;">' . $created_date . '</td>';
                        echo '</tr>';
                    }

                    echo '</table></div>';
                    echo '</div>';
                } else {
                    echo '<div class="card" style="margin-top:2rem;"><p>No jobs posted yet.</p></div>';
                }
                ?>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 AI Resume Matching System - University Mini Project</p>
        </div>
    </footer>

    <script>
        const mobileToggle = document.getElementById('mobileToggle');
        const navMenu = document.getElementById('navMenu');
        if (mobileToggle && navMenu) {
            mobileToggle.addEventListener('click', () => {
                navMenu.classList.toggle('active');
            });
        }

        const navbar = document.getElementById('navbar');
        window.addEventListener('scroll', () => {
            if (navbar) {
                if (window.scrollY > 50) navbar.classList.add('scrolled');
                else navbar.classList.remove('scrolled');
            }
        });
    </script>
</body>
</html>
