<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analyze Candidate - AI Resume Matching System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background-color: #f4f6f9;
            line-height: 1.6;
            color: #333;
        }

        header {
            background-color: #6f42c1;
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 20px;
        }

        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        nav h1 {
            font-size: 24px;
        }

        nav ul {
            list-style: none;
            display: flex;
            gap: 20px;
        }

        nav a {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 5px;
            transition: background 0.3s;
        }

        nav a:hover {
            background-color: #0056b3;
        }

        main {
            padding: 40px 0;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .card h2 {
            color: #6f42c1;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #6f42c1;
        }

        .candidate-info {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .candidate-info p {
            margin-bottom: 10px;
        }

        .candidate-info strong {
            color: #6f42c1;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }

        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        select:focus {
            outline: none;
            border-color: #6f42c1;
        }

        .btn {
            display: inline-block;
            padding: 12px 30px;
            background-color: #6f42c1;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            transition: background 0.3s;
        }

        .btn:hover {
            background-color: #0056b3;
        }

        .btn-secondary {
            background-color: #6c757d;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .empty-state a {
            color: #6f42c1;
            text-decoration: none;
        }

        footer {
            text-align: center;
            padding: 20px 0;
            color: #666;
            margin-top: 40px;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <nav>
                <h1>AI Resume Matcher</h1>
                <ul>
                    <li><a href="index.php">Resume</a></li>
                    <li><a href="add_job.php">Post Job</a></li>
                    <li><a href="candidate_list.php">Candidates</a></li>
                    <li><a href="job_analysis.php">Analyze Job</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">
            <?php
            require_once 'config.php';

            // Check if applicant_id is provided
            if (!isset($_GET['applicant_id']) || empty($_GET['applicant_id'])) {
                echo '<div class="card">';
                echo '<div class="empty-state">';
                echo '<p>No candidate selected.</p>';
                echo '<p><a href="candidate_list.php">Go back to candidate list</a></p>';
                echo '</div>';
                echo '</div>';
                exit();
            }

            $applicant_id = (int)$_GET['applicant_id'];

            // Fetch applicant details from Supabase
            $applicant_result = supabaseGET('applicants', '?applicant_id=eq.' . $applicant_id);

            if (!$applicant_result['success'] || empty($applicant_result['data'])) {
                echo '<div class="card">';
                echo '<div class="empty-state">';
                echo '<p>Candidate not found.</p>';
                echo '<p><a href="candidate_list.php">Go back to candidate list</a></p>';
                echo '</div>';
                echo '</div>';
                exit();
            }

            $applicant = $applicant_result['data'][0];

            // Fetch all jobs from Supabase
            $jobs_result = supabaseGET('jobs', '?order=created_at.desc');
            $jobs = $jobs_result['success'] ? $jobs_result['data'] : [];
            ?>

            <div class="card">
                <h2>Analyze Candidate</h2>

                <div class="candidate-info">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($applicant['NAME'] ?? 'N/A'); ?></p>
                    <p><strong>University:</strong> <?php echo htmlspecialchars($applicant['university'] ?? 'N/A'); ?></p>
                    <p><strong>Skills:</strong> <?php echo htmlspecialchars($applicant['skills'] ?? 'N/A'); ?></p>
                    <p><strong>Experience:</strong> <?php echo htmlspecialchars($applicant['experience'] ?? 'N/A'); ?></p>
                </div>

                <?php if (!empty($jobs)): ?>
                    <form action="result_ai.php" method="GET">
                        <input type="hidden" name="applicant_id" value="<?php echo $applicant_id; ?>">

                        <div class="form-group">
                            <label for="job_id">Select Job Position to Match</label>
                            <select id="job_id" name="job_id" required>
                                <option value="">-- Select a Job --</option>
                                <?php foreach ($jobs as $job): ?>
                                    <option value="<?php echo $job['job_id']; ?>">
                                        <?php echo htmlspecialchars($job['position_name'] ?? 'Unknown'); ?>
                                        (<?php echo htmlspecialchars($job['required_skills'] ?? 'N/A'); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn">Analyze Match</button>
                        <a href="candidate_list.php" class="btn btn-secondary">Cancel</a>
                    </form>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No jobs available for matching.</p>
                        <p><a href="add_job.php">Post a job first</a></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 AI Resume Matching System - University Mini Project</p>
        </div>
    </footer>
</body>
</html>
