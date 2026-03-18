<?php
require_once 'config.php';

if(isset($_GET['action']) && $_GET['action'] == 'get_candidates'){
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');

    // Suppress output buffering and errors
    ini_set('display_errors', 0);
    error_reporting(0);
    ob_start();

    try {
        $job_id = intval($_GET['job_id'] ?? 0);

        if($job_id <= 0){
            ob_clean();
            echo json_encode(["success" => false, "error" => "Invalid job ID"]);
            exit;
        }

        // STEP 1: FETCH JOB DATA
        $job_result = supabaseGET('jobs', '?job_id=eq.' . $job_id);
        if (!$job_result['success'] || empty($job_result['data'])) {
            ob_clean();
            echo json_encode(["success" => false, "error" => "Job not found"]);
            exit;
        }
        $job = $job_result['data'][0];

        // STEP 2: PREPARE JOB SKILLS
        $job_skills_raw = $job['required_skills'] ?? '';
        $job_skills = array_map('trim', explode(',', strtolower($job_skills_raw)));
        $job_skills = array_filter($job_skills, fn($s) => !empty($s)); // Remove empty strings

        // STEP 3: FETCH ALL APPLICANTS
        $result = supabaseGET('applicants', '?select=*');

        $candidates = [];

        // Process applicants and calculate compatibility scores
        if($result['success'] && !empty($result['data'])){
            foreach($result['data'] as $row){
                $applicant_id = intval($row['applicant_id'] ?? 0);
                $applicant_name = $row['NAME'] ?? 'Unknown';
                $candidate_skills_raw = $row['skills'] ?? '';
                
                // Skip invalid applicants
                if($applicant_id <= 0) continue;
                
                // STEP 4: CALCULATE SKILL MATCH
                $candidate_skills = array_map('trim', explode(',', strtolower($candidate_skills_raw)));
                $candidate_skills = array_filter($candidate_skills, fn($s) => !empty($s)); // Remove empty strings
                
                // Find matched and missing skills
                $matched_skills = array_intersect($candidate_skills, $job_skills);
                $missing_skills = array_diff($job_skills, $candidate_skills);
                
                // STEP 5: CALCULATE COMPATIBILITY SCORE
                $total_required = count($job_skills);
                if ($total_required > 0) {
                    $compatibility_score = (count($matched_skills) / $total_required) * 100;
                } else {
                    $compatibility_score = 0;
                }
                
                // Round to nearest integer
                $compatibility_score = round($compatibility_score);
                
                // STEP 6: BUILD CANDIDATE OBJECT
                $candidates[] = [
                    "id" => $applicant_id,
                    "applicant_id" => $applicant_id,
                    "candidate_name" => sanitize($applicant_name),
                    "position_match" => "Skills Match",
                    "compatibility_score" => $compatibility_score,
                    "matched_skills" => array_values($matched_skills),
                    "missing_skills" => array_values($missing_skills)
                ];
            }
        } else {
            // Log debug info if no results
            error_log("No applicants found in database");
        }

        // STEP 7: SORT BY SCORE (HIGHEST FIRST)
        usort($candidates, function($a, $b) {
            return $b['compatibility_score'] <=> $a['compatibility_score'];
        });

        // Clean output buffer and send JSON response
        ob_clean();
        echo json_encode([
            "success" => true,
            "data" => $candidates,
            "count" => count($candidates)
        ]);

    } catch (Exception $e) {
        ob_clean();
        echo json_encode([
            "success" => false,
            "error" => "Server error: " . $e->getMessage()
        ]);
    }

    exit;
}

// ดึง job จาก Supabase
$result = supabaseGET('jobs', '?select=*&order=created_at.desc');

$jobPositions = [];
$jobLoadError = '';

// Validate response
if (!isset($result['success'])) {
    $jobLoadError = "Invalid response format from database";
} elseif (!$result['success']) {
    $jobLoadError = "Failed to fetch jobs from database";
} elseif (empty($result['data'])) {
    $jobLoadError = "No jobs available in database";
} else {
    // Successfully fetched jobs
    $jobPositions = $result['data'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Job Candidate Matching - Ascenda</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <style>
        /* ============================================
           Ascenda Design System - CSS Variables
           Brand Color: PMS 2583 (#8B4789)
        ============================================ */
        :root {
            --primary: #8C4799;
            --primary-light: #A85CA6;
            --primary-dark: #6D3368;
            --primary-bg: #F8F4F8;
            --white: #FFFFFF;
            --gray-50: #FAFAFA;
            --gray-100: #F5F5F5;
            --gray-200: #E5E5E5;
            --gray-300: #D4D4D4;
            --gray-400: #A3A3A3;
            --gray-500: #737373;
            --gray-600: #525252;
            --gray-700: #404040;
            --gray-800: #262626;
            --gray-900: #171717;
            --success: #10B981;
            --success-light: #D1FAE5;
            --warning: #F59E0B;
            --warning-light: #FEF3C7;
            --danger: #EF4444;
            --danger-light: #FEE2E2;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
            --radius-sm: 6px;
            --radius: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            --transition: all 0.2s ease;
        }

        /* ============================================
           Reset & Base Styles
        ============================================ */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            font-size: 16px;
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--gray-50);
            color: var(--gray-800);
            line-height: 1.6;
            min-height: 100vh;
        }  

        /* ============================================
           Main Container
        ============================================ */
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* ============================================
           Page Header
        ============================================ */
        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
            letter-spacing: -0.02em;
        }

        .page-description {
            color: var(--gray-500);
            font-size: 1rem;
        }

        /* ============================================
           Cards
        ============================================ */
        .card {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            transition: var(--transition);
        }

        .card:hover {
            box-shadow: var(--shadow-md);
        }

        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray-100);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-header-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-bg);
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
        }

        .card-header-icon svg {
            width: 20px;
            height: 20px;
        }

        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
        }

        .card-body {
            padding: 1.5rem;
        }

        /* ============================================
           Form Elements
        ============================================ */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }

        .form-select {
            width: 100%;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            font-family: inherit;
            color: var(--gray-800);
            background-color: var(--white);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius);
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23737373'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 20px;
            cursor: pointer;
            transition: var(--transition);
        }

        .form-select:hover {
            border-color: var(--gray-400);
        }

        .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(139, 71, 137, 0.15);
        }

        /* ============================================
           Buttons
        ============================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0.75rem 1.5rem;
            font-size: 0.9375rem;
            font-weight: 500;
            font-family: inherit;
            border-radius: var(--radius);
            border: none;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn svg {
            width: 18px;
            height: 18px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: var(--white);
            box-shadow: 0 2px 4px rgba(139, 71, 137, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%);
            box-shadow: 0 4px 8px rgba(139, 71, 137, 0.4);
            transform: translateY(-1px);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-primary:disabled {
            background: var(--gray-300);
            box-shadow: none;
            cursor: not-allowed;
            transform: none;
        }

        .btn-secondary {
            background: var(--white);
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }

        .btn-secondary:hover {
            background: var(--gray-50);
            border-color: var(--gray-400);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8125rem;
        }

        /* ============================================
           Alerts / Messages
        ============================================ */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 1rem 1.25rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
        }

        .alert-icon {
            flex-shrink: 0;
            width: 20px;
            height: 20px;
        }

        .alert-content {
            flex: 1;
        }

        .alert-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .alert-info {
            background: var(--primary-bg);
            border: 1px solid rgba(139, 71, 137, 0.2);
            color: var(--primary-dark);
        }

        .alert-warning {
            background: var(--warning-light);
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: #92400E;
        }

        .alert-danger {
            background: var(--danger-light);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #991B1B;
        }

        /* ============================================
           Results Section
        ============================================ */
        .results-section {
            margin-top: 2rem;
            display: none;
        }

        .results-section.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .results-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .results-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-900);
        }

        .results-count {
            background: var(--primary-bg);
            color: var(--primary);
            padding: 0.375rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.8125rem;
            font-weight: 500;
        }

        /* ============================================
           Table Styles
        ============================================ */
        .table-container {
            overflow-x: auto;
            border-radius: var(--radius-lg);
            border: 1px solid var(--gray-200);
            background: var(--white);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9375rem;
        }

        .table thead {
            background: var(--gray-50);
        }

        .table th {
            padding: 1rem 1.25rem;
            text-align: left;
            font-weight: 600;
            color: var(--gray-600);
            font-size: 0.8125rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--gray-200);
        }

        .table td {
            padding: 1rem 1.25rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--gray-100);
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .table tbody tr {
            transition: var(--transition);
        }

        .table tbody tr:hover {
            background: var(--gray-50);
        }

        .candidate-name {
            font-weight: 500;
            color: var(--gray-900);
        }

        .position-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 0.375rem 0.75rem;
            background: var(--primary-bg);
            color: var(--primary);
            border-radius: 9999px;
            font-size: 0.8125rem;
            font-weight: 500;
        }

        .position-badge svg {
            width: 14px;
            height: 14px;
        }

        
        /* ============================================
           Empty State
        ============================================ */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
        }

        .empty-state-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: var(--gray-100);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-400);
        }

        .empty-state-icon svg {
            width: 40px;
            height: 40px;
        }

        .empty-state-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }

        .empty-state-text {
            color: var(--gray-500);
            font-size: 0.9375rem;
        }

        /* ============================================
           Loading Spinner
        ============================================ */
        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid var(--white);
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
        }

        .loading-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .loading-content {
            text-align: center;
        }

        .loading-spinner {
            width: 48px;
            height: 48px;
            border: 3px solid var(--gray-200);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 1rem;
        }

        .loading-text {
            color: var(--gray-600);
            font-weight: 500;
        }

        /* ============================================
           Responsive Design
        ============================================ */
        @media (max-width: 1024px) {
            .navbar-menu {
                display: none;
                position: absolute;
                top: 70px;
                left: 0;
                right: 0;
                background: var(--primary);
                flex-direction: column;
                padding: 1rem;
                gap: 0.25rem;
            }

            .navbar-menu.active {
                display: flex;
            }

            .navbar-menu a {
                width: 100%;
                padding: 0.875rem 1rem;
            }

            .mobile-toggle {
                display: block;
            }
        }

        @media (max-width: 768px) {
            .main-container {
                padding: 1rem;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .card-body {
                padding: 1rem;
            }

            .table th, .table td {
                padding: 0.75rem 1rem;
            }

            .score-bar {
                max-width: 80px;
            }
        }

        @media (max-width: 640px) {
            .results-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .table {
                font-size: 0.875rem;
            }

            .btn {
                width: 100%;
            }
        }
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
        <li><a href="add_job.php" class="nav-link">Post Job</a></li>
        <li><a href="candidate_list.php" class="nav-link">Candidates</a></li>
        <li><a href="job_analysis.php" class="nav-link active">Job Analysis</a></li>
        </ul>

        <div class="mobile-toggle" id="mobileToggle">
        <span></span>
        <span></span>
        <span></span>
        </div>

        </div>
    </nav>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <div class="loading-text">Analyzing candidates...</div>
        </div>
    </div>

    <!-- Main Content -->
    <main class="main-container">
        <!-- Page Header -->
        <header class="page-header">
            <h1 class="page-title">AI Job Candidate Matching</h1>
            <p class="page-description">Select a job position to view AI-powered candidate matches and compatibility scores.</p>
        </header>

        <!-- System Messages -->
        <div id="systemMessages"></div>

        <!-- Job Selection Card -->
        <div class="card">
            <div class="card-header">
                <div class="card-header-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                        <path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/>
                    </svg>
                </div>
                <h2 class="card-title">Select Job Position</h2>
            </div>
            <div class="card-body">
                <?php if (!empty($jobLoadError)): ?>
                    <div class="alert alert-warning" style="background: #fff3cd; border: 1px solid #ffc107; color: #856404; padding: 12px; border-radius: 6px; margin-bottom: 15px;">
                        <strong>⚠️ Notice:</strong> <?php echo htmlspecialchars($jobLoadError); ?>
                    </div>
                <?php endif; ?>
                <div class="form-group">
                    <label class="form-label" for="jobSelect">Job Position</label>
                    <select class="form-select" id="jobSelect" <?php echo empty($jobPositions) ? 'disabled' : ''; ?>>
                        <option value="">-- Select a job position --</option>
                        <?php if (empty($jobPositions)): ?>
                            <option value="" disabled>No positions available</option>
                        <?php else: ?>
                            <?php foreach ($jobPositions as $job): ?>
                                <option value="<?php echo htmlspecialchars($job['job_id'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($job['position_name'] ?? 'Untitled Position'); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <button class="btn btn-primary" id="viewMatchesBtn" onclick="viewMatches()" <?php echo empty($jobPositions) ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''; ?>>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    View Matches
                </button>
            </div>
        </div>

        <!-- Results Section -->
        <section class="results-section" id="resultsSection">
            <div class="results-header">
                <h2 class="results-title">Candidate Matching Results</h2>
                <span class="results-count" id="resultsCount">0 candidates</span>
            </div>

            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Candidate Name</th>
                            <th>Position Match</th>
                            <th>Compatibility Score (%)</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="candidatesTableBody">
                        <!-- Candidates will be loaded here -->
                    </tbody>
                </table>
            </div>

            <!-- Empty State -->
            <div class="empty-state" id="emptyState" style="display: none;">
                <div class="empty-state-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <line x1="17" y1="11" x2="23" y2="11"/>
                    </svg>
                </div>
                <h3 class="empty-state-title">No Applicants Found</h3>
                <p class="empty-state-text">No applicants found for this position.</p>
            </div>
        </section>
    </main>

    <script>
        /**
         * Ascenda - AI Job Candidate Matching
         * JavaScript Module
         */
        console.log("✓ JS Loaded - job_analysis.php");

        // Toggle mobile menu
        function toggleMobileMenu() {
            const menu = document.getElementById('navMenu');
            if(menu) menu.classList.toggle('active');
        }

        // Show loading overlay
        function showLoading(show = true) {
            const overlay = document.getElementById('loadingOverlay');
            if (show) {
                overlay.classList.add('active');
            } else {
                overlay.classList.remove('active');
            }
        }

        // Show system message
        function showMessage(type, message) {
            const container = document.getElementById('systemMessages');
            const alertClass = type === 'error' ? 'alert-danger' :
                             type === 'warning' ? 'alert-warning' : 'alert-info';

            const iconSvg = type === 'error' ?
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>' :
                type === 'warning' ?
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>' :
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';

            container.innerHTML = `
                <div class="alert ${alertClass}">
                    <div class="alert-icon">${iconSvg}</div>
                    <div class="alert-content">${message}</div>
                </div>
            `;

            // Auto-hide after 5 seconds
            setTimeout(() => {
                container.innerHTML = '';
            }, 5000);
        }

        // Clear messages
        function clearMessages() {
            document.getElementById('systemMessages').innerHTML = '';
        }

        // View matches for selected job
        async function viewMatches() {
            console.log("✓ viewMatches() called");
            const jobSelect = document.getElementById('jobSelect');
            const jobId = jobSelect.value;

            console.log("Selected Job ID:", jobId);

            clearMessages();

            if (!jobId) {
                showMessage('warning', 'Please select a job position first.');
                return;
            }

            showLoading(true);

            try {
                console.log("Fetching candidates for job:", jobId);
                const response = await fetch(`?action=get_candidates&job_id=${jobId}`);
                const text = await response.text();
                console.log("RAW RESPONSE:", text);

                let data;
                try {
                    data = JSON.parse(text);
                } catch (parseError) {
                    console.error("JSON Parse Error:", parseError);
                    showMessage('error', 'Invalid response from server. Check console for details.');
                    showLoading(false);
                    return;
                }

                console.log("Parsed data:", data);

                if (!data.success) {
                    showMessage('error', data.error || data.message || 'An error occurred while fetching candidates.');
                    document.getElementById('resultsSection').classList.remove('active');
                    showLoading(false);
                    return;
                }

                const candidates = data.data || [];
                console.log("Candidates:", candidates);
                const tableBody = document.getElementById('candidatesTableBody');
                const resultsSection = document.getElementById('resultsSection');
                const emptyState = document.getElementById('emptyState');
                const tableContainer = document.querySelector('.table-container');
                const resultsCount = document.getElementById('resultsCount');

                // Update results count
                resultsCount.textContent = `${candidates.length} candidate${candidates.length !== 1 ? 's' : ''}`;

                if (candidates.length === 0) {
                    tableContainer.style.display = 'none';
                    emptyState.style.display = 'block';
                } else {
                    tableContainer.style.display = 'block';
                    emptyState.style.display = 'none';

                    tableBody.innerHTML = candidates.map(candidate => {
                        const score = Math.round(candidate.compatibility_score) || 0;
                        
                        // Build skills display
                        let skillsHtml = '';
                        
                        // Show matched skills in green
                        if (candidate.matched_skills && candidate.matched_skills.length > 0) {
                            skillsHtml += candidate.matched_skills.map(skill => 
                                `<span style="color: #10B981; font-weight: 500; margin-right: 4px;">✓ ${escapeHtml(skill)}</span>`
                            ).join('');
                        }
                        
                        // Show missing skills in red
                        if (candidate.missing_skills && candidate.missing_skills.length > 0) {
                            skillsHtml += ' ' + candidate.missing_skills.map(skill => 
                                `<span style="color: #EF4444; margin-right: 4px;">✗ ${escapeHtml(skill)}</span>`
                            ).join('');
                        }

                        return `
                            <tr>
                                <td class="candidate-name">${escapeHtml(candidate.candidate_name)}</td>
                                <td>${escapeHtml(candidate.position_match)}</td>
                                <td title="Based on skills matching: ${score}% of required skills matched">
                                    <strong>${score}%</strong>
                                </td>
                                <td>
                                    <a href="ascenda-result_ai/match_results.php?job_id=${jobId}&applicant_id=${candidate.id}" class="btn btn-secondary btn-sm">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                            <circle cx="12" cy="12" r="10"/>
                                            <line x1="12" y1="16" x2="12" y2="12"/>
                                            <line x1="12" y1="8" x2="12.01" y2="8"/>
                                        </svg>
                                        Details
                                    </a>
                                </td>
                            </tr>
                        `;
                    }).join('');
                }

                resultsSection.classList.add('active');

            } catch (error) {
                console.error('Error:', error);
                showMessage('error', 'Failed to connect to the server. Please try again.');
            } finally {
                showLoading(false);
            }
        }

        // Escape HTML to prevent XSS
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            console.log("✓ DOMContentLoaded - Initializing page");
            const jobSelect = document.getElementById('jobSelect');

            // Check if no jobs available
            if (jobSelect.options.length <= 1) {
                const options = jobSelect.querySelectorAll('option:not([value=""])');
                if (options.length === 0 || (options.length === 1 && options[0].disabled)) {
                    showMessage('info', 'No job positions available.');
                }
            }

            // Enable/disable button based on selection
            jobSelect.addEventListener('change', function() {
                const btn = document.getElementById('viewMatchesBtn');
                btn.disabled = !this.value;
                console.log("Job selected:", this.value);
            });

            // wire mobile toggle button
            const mobileToggleEl = document.getElementById('mobileToggle');
            if (mobileToggleEl) {
                mobileToggleEl.addEventListener('click', toggleMobileMenu);
            }
            
            console.log("✓ Page initialization complete");
        });

        // Navbar scroll effect
        const navbarEl = document.getElementById('navbar');
        if (navbarEl) {
            window.addEventListener('scroll', () => {
                if (window.scrollY > 50) navbarEl.classList.add('scrolled');
                else navbarEl.classList.remove('scrolled');
            });
        }
    </script>