<?php
/**
 * Ascenda AI Resume Matcher
 * AI Resume Match Results Page
 */

session_start();
// Hide PHP warnings from UI
error_reporting(0);
ini_set('display_errors', 0);
// กำหนด default ค่า debug
$debug = $debug ?? false; // ถ้า $debug ยังไม่กำหนด ให้เป็น false
require_once __DIR__ . '/../config.php';

// ---------- DEBUG MODE ----------
// Enable debug output with ?debug=1
$debug = isset($_GET['debug']);

if ($debug) {
    echo "<pre>🐛 DEBUG MODE</pre>";
}

function fetchCandidate($applicantId) {
    $res = supabaseGET('applicants', '?applicant_id=eq.' . $applicantId);
    return $res['data'][0] ?? null;
}
function fetchJob($jobId) {
    $res = supabaseGET('jobs', '?job_id=eq.' . $jobId);
    return $res['data'][0] ?? null;
}
function saveMatchResult($applicantId, $jobId, $score, $matched, $missing, $rec) {
    return supabasePOST('match_results', [
        'applicant_id' => $applicantId,
        'job_id' => $jobId,
        'match_score' => $score,
        'strengths' => json_encode($matched),
        'weaknesses' => json_encode($missing),
        'recommendation' => $rec
    ]);
}



// ---------- CSRF Protection ----------
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}



// ---------- Matching Helpers ----------
function normalizeSkills($skills) {
    if (empty($skills)) return [];

    // JSON format
    if (is_string($skills) && str_starts_with(trim($skills), '[')) {
        $decoded = json_decode($skills, true);
        if (is_array($decoded)) return $decoded;
    }

    // comma string
    if (is_string($skills)) {
        return array_map('trim', explode(',', $skills));
    }

    // already array
    if (is_array($skills)) {
        return $skills;
    }

    return [];
}

function computeManualMatch($candidateSkills, $jobSkills) {
    $candidateSkills = array_map('strtolower', normalizeSkills($candidateSkills));
    $jobSkills = array_map('strtolower', normalizeSkills($jobSkills));

    $matched = array_values(array_intersect($candidateSkills, $jobSkills));
    $missing = array_values(array_diff($jobSkills, $candidateSkills));
    $score = 0;
    if (count($jobSkills) > 0) {
        $rawScore = (count($jobSkills) > 0)
    ? (count($matched) / count($jobSkills)) * 100
    : 0;

// 🔥 บังคับให้เป็น 0,25,50,75,100
if ($rawScore == 0) {
    $score = 0;
} elseif ($rawScore <= 25) {
    $score = 25;
} elseif ($rawScore <= 50) {
    $score = 50;
} elseif ($rawScore <= 75) {
    $score = 75;
} else {
    $score = 100;
}
    }

    return [
        'match_score' => $score,
        'matched_skills' => $matched,
        'missing_skills' => $missing,
    ];
}

// ฟังก์ชัน normalizeScore 
function normalizeScore($score) {
    return round($score / 25) * 25;
}

// ---------- Database Helpers ----------
function markInterview($applicantId, $jobId) {
    require_once __DIR__ . '/../config.php';
    $data = [
        'applicant_id' => $applicantId,
        'job_id' => $jobId,
        'status' => 'selected'
    ];
    supabasePOST('interviews', $data);
}

function saveFeedback($applicantId, $jobId, $feedbackText) {
    require_once __DIR__ . '/../config.php';
    $data = [
        'applicant_id' => $applicantId,
        'job_id' => $jobId,
        'feedback' => $feedbackText,
        'created_at' => date('Y-m-d H:i:s')
    ];
    supabasePOST('feedbacks', $data);
}


// ---------- Groq AI ----------
function callGroqAI($candidate, $job) {
    global $debug;
    if ($debug) {
    echo "<pre>🔥 ENTERED AI FUNCTION</pre>";
}
    error_log("🔥 AI FUNCTION CALLED");
    $apiKey = GROQ_API_KEY;

    // Fail fast if no API key
    if (empty($apiKey)) {
        return ['error' => 'GROQ_API_KEY environment variable not set. Please configure the API key.'];
    }

    // Build candidate data string with ALL available fields
    $candidateText = "Candidate Profile:\n";
    $candidateText .= "- Full Name: " . ($candidate['NAME'] ?? 'N/A') . "\n";
    if (!empty($candidate['email'])) {
        $candidateText .= "- Email: " . $candidate['email'] . "\n";
    }
    if (!empty($candidate['university'])) {
        $candidateText .= "- University: " . $candidate['university'] . "\n";
    }
    if (!empty($candidate['education'])) {
        $candidateText .= "- Education: " . $candidate['education'] . "\n";
    }
    if (!empty($candidate['experience'])) {
        $candidateText .= "- Experience: " . $candidate['experience'] . "\n";
    }
    if (!empty($candidate['experience_details'])) {
        $candidateText .= "- Experience Details: " . $candidate['experience_details'] . "\n";
    }
    if (!empty($candidate['resume_file'])) {
        $candidateText .= "- Resume File: " . $candidate['resume_file'] . "\n";
    }
    
    $candidateSkills = normalizeSkills($candidate['skills'] ?? '');
    $candidateText .= "- Skills: " . implode(", ", $candidateSkills) . "\n";

    // Build job data string
    $jobText = "Job Position:\n";
    $jobText .= "- Title: " . ($job['position_name'] ?? 'N/A') . "\n";
    if (!empty($job['company'])) {
        $jobText .= "- Company: " . $job['company'] . "\n";
    }
    if (!empty($job['location'])) {
        $jobText .= "- Location: " . $job['location'] . "\n";
    }
    if (!empty($job['job_description'])) {
        $jobText .= "- Description: " . $job['job_description'] . "\n";
    }
    
    $jobSkills = normalizeSkills($job['required_skills'] ?? '');
    $jobText .= "- Required Skills: " . implode(", ", $jobSkills) . "\n";

    // Build strong prompt that FORCES JSON output
    $prompt = <<<PROMPT
You are an expert recruiter analyzing candidate-job fit.

Analyze the following candidate and job position. Calculate a match score (0-100) based on skills overlap, experience relevance, and qualifications.

$candidateText

$jobText

CRITICAL: Return ONLY a valid JSON object. No other text. No markdown. Just raw JSON.

{
  "match_score": <number between 0 and 100>,
  "matched_skills": [<list of skills the candidate has that are required>],
  "missing_skills": [<list of required skills the candidate lacks>],
  "analysis": {
    "summary": "<one sentence overall assessment>",
    "strengths": [<list of 2-3 candidate strengths for this role>],
    "weaknesses": [<list of 2-3 gaps or areas for improvement>]
  }
}

REMEMBER: Output ONLY valid JSON. No explanations. No markdown backticks. Just the JSON object.
PROMPT;

    $payload = [
        'model' => GROQ_MODEL,
        'messages' => [
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => 0.3,
        'max_tokens' => 1000
    ];

    $response = httpRequest(
    'POST',
    GROQ_API_URL,
    $payload,
    [
        'Authorization' => 'Bearer ' . $apiKey,
        'Content-Type' => 'application/json'
    ]
);

$httpCode = $response['http_code'];
$curlError = '';
$apiResponse = $response['data'];
// ✅ DEBUG ก่อน
if ($debug) {
    echo "<pre>📦 API RESPONSE:\n";
    print_r($apiResponse);
    echo "</pre>";
}

// ✅ เช็คแบบกันพัง
if (!$apiResponse) {
    return ['error' => 'Empty API response'];
}

if (!isset($apiResponse['choices'])) {
    return [
        'error' => 'No choices in response',
        'debug' => $apiResponse
    ];
}

$content = $apiResponse['choices'][0]['message']['content'] ?? null;

if (!$content) {
    return [
        'error' => 'No content in response',
        'debug' => $apiResponse
    ];
}
    // Check for cURL errors
    if ($curlError) {
        return ['error' => 'cURL Error: ' . $curlError];
    }

    // Check HTTP status
    if ($httpCode !== 200) {
        return ['error' => "HTTP Error: $httpCode. Response: " . substr(json_encode($apiResponse), 0, 200)];
    }

    // Extract JSON using regex - allow for extra text before/after
    if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
        $jsonStr = $matches[0];
        $parsed = json_decode($jsonStr, true);

        if ($parsed === null) {
            return ['error' => 'JSON parsing failed. Invalid JSON syntax.'];
        }

        // Validate required fields
        if (!isset($parsed['match_score']) || !is_numeric($parsed['match_score'])) {
            return ['error' => 'Missing or invalid match_score in response'];
        }

        // Ensure arrays
        if (!isset($parsed['matched_skills'])) {
            $parsed['matched_skills'] = [];
        }
        if (!isset($parsed['missing_skills'])) {
            $parsed['missing_skills'] = [];
        }
        if (!isset($parsed['analysis'])) {
            $parsed['analysis'] = [];
        }

        return $parsed;
    }

    return ['error' => 'No JSON found in response: ' . substr($content, 0, 100)];

}

// ---------- Helpers for UI ----------
function getScoreLevel($score) {
    if ($score >= 70) return 'high';
    if ($score >= 50) return 'medium';
    return 'low';
}

function getScoreLabel($score) {
    if ($score >= 70) return 'Strong Match';
    if ($score >= 50) return 'Moderate Match';
    return 'Low Match';
}

// ---------- Input Handling ----------
$applicantId = isset($_REQUEST['applicant_id']) ? intval($_REQUEST['applicant_id']) : null;
$jobId = isset($_REQUEST['job_id']) ? intval($_REQUEST['job_id']) : null;

// Enforce CSRF on POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'select_interview' && $applicantId && $jobId) {
        markInterview($applicantId, $jobId);

        // Return JSON for AJAX requests
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Interview selected']);
            exit;
        }

        // Redirect back with message for non-AJAX requests
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . "?applicant_id=" . urlencode($applicantId) . "&job_id=" . urlencode($jobId) . "&status=interview_selected");
        exit;
    }

    if ($action === 'submit_feedback' && $applicantId && $jobId) {
        $feedbackText = trim($_POST['feedback'] ?? '');
        if ($feedbackText !== '') {
            saveFeedback($applicantId, $jobId, $feedbackText);
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                echo json_encode(['success' => true, 'message' => 'Feedback saved']);
                exit;
            }
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . "?applicant_id=" . urlencode($applicantId) . "&job_id=" . urlencode($jobId) . "&status=feedback_submitted");
            exit;
        }
    }
}

// ============================================
// MAIN LOGIC: FETCH DATA, THEN CALL AI
// ============================================

$candidate = null;
$job = null;
$analysis = null;
$aiError = null;

if ($applicantId && $jobId) {
    // STEP 1: FETCH CANDIDATE & JOB DATA
    $candidate = fetchCandidate($applicantId);
    $job = fetchJob($jobId);

    if ($debug) {
        echo "<pre>📥 FETCHED CANDIDATE:\n";
        print_r($candidate);
        echo "\n📥 FETCHED JOB:\n";
        print_r($job);
        echo "</pre>";
    }

    // STEP 2: VALIDATE DATA EXISTS
    if (!$candidate) {
        die('❌ ERROR: Candidate not found (applicant_id=' . htmlspecialchars($applicantId) . ')');
    }
    if (!$job) {
        die('❌ ERROR: Job not found (job_id=' . htmlspecialchars($jobId) . ')');
    }

    // STEP 3: NORMALIZE FIELD NAMES
    if (isset($job['job_description']) && !isset($job['description'])) {
        $job['description'] = $job['job_description'];
    }

    // STEP 4: CALL GROQ AI
    if ($debug) {
        echo "<pre>🤖 CALLING GROQ AI WITH:\n";
        echo "Candidate: " . $candidate['NAME'] . "\n";
        echo "Job: " . $job['position_name'] . "\n";
        echo "</pre>";
    }

    $aiResult = callGroqAI($candidate, $job);

    if ($debug) {
        echo "<pre>📤 AI RESULT:\n";
        print_r($aiResult);
        echo "</pre>";
    }

    // STEP 5: PROCESS AI RESULT
    if (isset($aiResult['error'])) {
        $aiError = $aiResult['error'];
        if ($debug) {
            echo "<pre style='background:#FFE5E5;'>⚠️ AI ERROR: " . htmlspecialchars($aiError) . "</pre>";
        }

        // FALLBACK: Use manual skill matching
        $manual = computeManualMatch(
            $candidate['skills'] ?? '',
            $job['required_skills'] ?? ''
        );

        $analysis = [
            'match_score' => $manual['match_score'],
            'matched_skills' => $manual['matched_skills'],
            'missing_skills' => $manual['missing_skills'],
            'analysis' => [
                'summary' => 'Using skill-based matching. AI analysis encountered an issue.',
                'strengths' => [],
                'weaknesses' => []
            ],
            'strengths' => [],
            'improvements' => [],
            'source' => 'manual',
            'error' => $aiError
        ];
    } else {
        // STEP 6: EXTRACT & PARSE AI RESPONSE
        $analysis = $aiResult;
        $analysis['source'] = 'ai';
        
        // Ensure all required fields exist
        $analysis['match_score'] = isset($analysis['match_score']) ? intval($analysis['match_score']) : 0;
        $analysis['matched_skills'] = $analysis['matched_skills'] ?? [];
        $analysis['missing_skills'] = $analysis['missing_skills'] ?? [];
        $analysis['analysis'] = $analysis['analysis'] ?? [];
        $analysis['strengths'] = $analysis['analysis']['strengths'] ?? [];
        $analysis['improvements'] = $analysis['analysis']['weaknesses'] ?? [];
        
        // Normalize score to 0/25/50/75/100
        $analysis['match_score'] = normalizeScore($analysis['match_score']);
    }

    // STEP 7: SAVE TO DATABASE
    if ($debug) {
        echo "<pre>💾 SAVING TO DATABASE:\n";
        echo "Score: " . $analysis['match_score'] . "\n";
        echo "Matched: " . count($analysis['matched_skills']) . " skills\n";
        echo "Missing: " . count($analysis['missing_skills']) . " skills\n";
        echo "</pre>";
    }

    saveMatchResult(
        $applicantId,
        $jobId,
        $analysis['match_score'] ?? 0,
        $analysis['matched_skills'] ?? [],
        $analysis['missing_skills'] ?? [],
        $analysis['analysis']['summary'] ?? ''
    );

} else {
    // FALLBACK: Demo data (no applicantId or jobId provided)
    $candidate = [
        'applicant_id' => 0,
        'NAME' => 'Sarah Johnson',
        'university' => 'Stanford University',
        'skills' => 'Python, SQL, Machine Learning, TensorFlow, Data Analysis, Statistics',
        'experience' => '5 years Senior Data Scientist',
        'experience_details' => 'Senior Data Scientist at Tech Corp with expertise in building ML pipelines and deploying models to production.',
        'status' => 'Pending Review'
    ];

    $job = [
        'job_id' => 0,
        'position_name' => 'Senior Machine Learning Engineer',
        'company' => 'Ascenda Technologies',
        'job_description' => 'We are looking for an experienced Machine Learning Engineer to join our AI team. The ideal candidate will design, develop, and deploy machine learning models at scale.',
        'description' => 'We are looking for an experienced Machine Learning Engineer to join our AI team. The ideal candidate will design, develop, and deploy machine learning models at scale.',
        'required_skills' => 'Python, SQL, Machine Learning, Docker, AWS, Data Engineering, TensorFlow'
    ];

    $analysis = [
        'match_score' => 85,
        'matched_skills' => ['Python', 'SQL', 'Machine Learning', 'TensorFlow'],
        'missing_skills' => ['Docker', 'AWS', 'Data Engineering'],
        'analysis' => [
            'summary' => 'Sarah is a strong candidate for this position with excellent technical skills in core areas. Her 5 years of experience and leadership background make her well-suited for a senior role.'
        ],
        'strengths' => [
            'Strong foundation in Python and machine learning',
            'Practical experience with TensorFlow',
            'Leadership experience managing a team'
        ],
        'improvements' => [
            'Cloud platform experience (AWS) would strengthen the profile',
            'Container technologies like Docker are recommended',
            'Data engineering skills would be beneficial'
        ],
        'source' => 'demo'
    ];
}

$matchScore = $analysis['match_score'] ?? 0;
$scoreLevel = getScoreLevel($matchScore);
$scoreLabel = getScoreLabel($matchScore);

// Get initials for avatar
$nameParts = explode(' ', $candidate['NAME'] ?? 'Unknown');
$initials = '';
foreach ($nameParts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

// Handle status messages
$statusMessage = '';
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'interview_selected') {
        $statusMessage = 'Candidate successfully selected for interview!';
    } elseif ($_GET['status'] === 'feedback_submitted') {
        $statusMessage = 'Feedback submitted successfully!';
    }
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Resume Match Results - Ascenda</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/stylesre.css">
    <style>
        .status-message {
            background: #10B981;
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            margin-top: 10px;
            text-align: center;
        }
        .status-message.success {
            background: #10B981;
        }

        /* Toast notification */
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(16, 185, 129, 0.95);
            color: white;
            padding: 10px 16px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            z-index: 10010;
            opacity: 1;
            transition: opacity 0.3s ease, transform 0.3s ease;
        }
        .toast.toast-error {
            background: rgba(220, 38, 38, 0.95);
        }
        .toast.toast-hide {
            opacity: 0;
            transform: translateY(10px);
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <a href="../index.php" class="navbar-brand">
            <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect width="40" height="40" rx="8" fill="white" fill-opacity="0.2"/>
                <path d="M20 8L8 32H14L20 20L26 32H32L20 8Z" fill="white"/>
                <circle cx="20" cy="28" r="3" fill="white"/>
            </svg>
            <h1>AI Resume Matcher</h1>
        </a>

        <button class="mobile-menu-btn" aria-label="Toggle menu">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>

        <ul class="navbar-nav">
            <li><a href="../index.php">Home</a></li>
            <li><a href="../submit-resume.php">Resume</a></li>
            <li><a href="../add_job.php">Post Job</a></li>
            <li><a href="../candidate_list.php">Candidates</a></li>
            <li><a href="../job_analysis.php">Job Analysis</a></li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="main-container">
        <!-- Page Header -->
        <header class="page-header">
            <a href="../job_analysis.php" class="btn btn-back">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Candidate List
            </a>
            <h2>AI Resume Match Results</h2>
            <p>Detailed analysis of candidate compatibility with job requirements</p>
            <?php if ($statusMessage): ?>
                <div class="status-message success"><?php echo htmlspecialchars($statusMessage); ?></div>
            <?php endif; ?>
            
            <?php if ($aiError): ?>
                <div style="background: #FEE2E2; border: 1px solid #FCA5A5; color: #7F1D1D; padding: 12px; border-radius: 8px; margin-top: 10px;">
                    <strong>⚠️ AI Analysis Note:</strong> <?php echo htmlspecialchars($aiError); ?><br>
                    <small>Using skill-based matching as fallback.</small>
                </div>
            <?php endif; ?>
        </header>

        <!-- Hidden inputs for form submissions -->
        <input type="hidden" id="applicantId" value="<?php echo htmlspecialchars($applicantId); ?>">
        <input type="hidden" id="jobId" value="<?php echo htmlspecialchars($jobId); ?>">
        <input type="hidden" id="csrfToken" value="<?php echo htmlspecialchars($csrfToken); ?>">

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Left Column -->
            <div class="left-column">
                <!-- Match Score Card -->
                <div class="card">
                    <div class="card-body match-score-card">
                        <div class="score-circle <?php echo $scoreLevel; ?>">
                            <svg viewBox="0 0 160 160">
                                <circle class="bg-circle" cx="80" cy="80" r="72"/>
                                <circle class="progress-circle" cx="80" cy="80" r="72" data-score="<?php echo $matchScore; ?>"/>
                            </svg>
                            <div class="score-value">0<span>%</span></div>
                        </div>
                        <div class="score-label">Match Score</div>
                        <span class="score-badge <?php echo $scoreLevel; ?>"><?php echo $scoreLabel; ?></span>
                    </div>
                </div>

                <!-- Candidate Information Card -->
                <div class="card">
                    <div class="card-header">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        <h3>Candidate Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="candidate-profile">
                            <div class="candidate-avatar"><?php echo $initials; ?></div>
                            <div class="candidate-name"><?php echo htmlspecialchars($candidate['NAME'] ?? 'Unknown'); ?></div>
                            <div class="candidate-university"><?php echo htmlspecialchars($candidate['university'] ?? ''); ?></div>
                        </div>
                        <div class="candidate-details">
                            <div class="detail-item">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                </svg>
                                <div>
                                    <div class="label">Skills</div>
                                    <div class="value">
                                        <?php
                                        $skills = $candidate['skills'] ?? '';
                                        if (is_array($skills)) {
                                            echo htmlspecialchars(implode(', ', $skills));
                                        } else {
                                            echo htmlspecialchars($skills);
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <div class="detail-item">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                <div>
                                    <div class="label">Experience</div>
                                    <div class="value"><?php echo htmlspecialchars($candidate['experience'] ?? ''); ?></div>
                                </div>
                            </div>
                            <div class="detail-item">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <div>
                                    <div class="label">Status</div>
                                    <div class="value"><?php echo htmlspecialchars($candidate['status'] ?? 'Unknown'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="right-column">
                <!-- Job Information Card -->
                <div class="card">
                    <div class="card-header">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        <h3>Job Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="job-title"><?php echo htmlspecialchars($job['position_name'] ?? 'Unknown Position'); ?></div>
                        <div class="job-company"><?php echo htmlspecialchars($job['company'] ?? ''); ?></div>
                        <p class="job-description"><?php echo htmlspecialchars($job['description'] ?? ''); ?></p>
                        <div class="required-skills-label">Required Skills</div>
                        <div class="skills-container">
                            <?php
                            $requiredSkills = $job['required_skills'] ?? '';
                            if (is_array($requiredSkills)) {
                                foreach ($requiredSkills as $skill):
                            ?>
                                <span class="skill-tag required"><?php echo htmlspecialchars($skill); ?></span>
                            <?php endforeach;
                            } else {
                                $skillsArray = explode(',', $requiredSkills);
                                foreach ($skillsArray as $skill):
                            ?>
                                <span class="skill-tag required"><?php echo htmlspecialchars(trim($skill)); ?></span>
                            <?php endforeach;
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Skills Analysis Card -->
                <div class="card">
                    <div class="card-header">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        <h3>Skills Analysis</h3>
                    </div>
                    <div class="card-body">
                        <div class="skills-grid">
                            <!-- Matched Skills -->
                            <div class="skills-section matched">
                                <h4>
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Matched Skills
                                </h4>
                                <div class="skills-container">
                                    <?php
                                    $matchedSkills = $analysis['matched_skills'] ?? [];
                                    foreach ($matchedSkills as $skill):
                                    ?>
                                        <span class="skill-tag matched">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            <?php echo htmlspecialchars($skill); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Missing Skills -->
                            <div class="skills-section missing">
                                <h4>
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                    Missing Skills
                                </h4>
                                <div class="skills-container">
                                    <?php
                                    $missingSkills = $analysis['missing_skills'] ?? [];
                                    foreach ($missingSkills as $skill):
                                    ?>
                                        <span class="skill-tag missing">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                            </svg>
                                            <?php echo htmlspecialchars($skill); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- AI Analysis Details Card -->
                <div class="card">
                    <div class="card-header">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        <h3>AI Analysis Details</h3>
                        <?php
                            $source = $analysis['source'] ?? 'manual';
                            if ($source === 'ai') {
                                $bgColor = '#D1FAE5';
                                $txtColor = '#065F46';
                                $label = '🤖 AI-Powered';
                            } elseif ($source === 'cached') {
                                $bgColor = '#DBEAFE';
                                $txtColor = '#0C2540';
                                $label = '💾 Cached';
                            } else {
                                $bgColor = '#FEE2E2';
                                $txtColor = '#7F1D1D';
                                $label = '⚙️ Skill-Based';
                            }
                            ?>
                            <span style="margin-left: auto; font-size: 12px; padding: 4px 10px; border-radius: 12px; background: <?= $bgColor ?>; color: <?= $txtColor ?>;">
                                <?= $label ?>
                            </span>
                    </div>
                    <div class="card-body">
                        <div class="ai-analysis-box">
                            <h4>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                Analysis Summary
                            </h4>

                            <div class="analysis-section">
                                <h5>Overall Assessment</h5>
                                <p>
                                    <?php 
                                        // Handle both nested and flat structure
                                        $summary = '';
                                        if (isset($analysis['analysis']['summary'])) {
                                            $summary = $analysis['analysis']['summary'];
                                        } elseif (isset($analysis['summary'])) {
                                            $summary = $analysis['summary'];
                                        } else {
                                            $summary = 'No summary available';
                                        }
                                        echo htmlspecialchars($summary);
                                    ?>
                                </p>
                            </div>

                            <div class="analysis-section">
                                <h5>Candidate Strengths</h5>
                                <ul>
                                    <?php
                                    $strengths = $analysis['strengths'] ?? [];
                                    if (empty($strengths) && isset($analysis['analysis']['strengths'])) {
                                        $strengths = $analysis['analysis']['strengths'];
                                    }
                                    
                                    if (!empty($strengths)) {
                                        foreach ($strengths as $strength) {
                                            echo '<li>' . htmlspecialchars($strength) . '</li>';
                                        }
                                    } else {
                                        echo '<li>No strengths identified</li>';
                                    }
                                    ?>
                                </ul>
                            </div>

                            <div class="analysis-section">
                                <h5>Areas for Improvement</h5>
                                <ul>
                                    <?php
                                    $improvements = $analysis['improvements'] ?? [];
                                    if (empty($improvements) && isset($analysis['analysis']['weaknesses'])) {
                                        $improvements = $analysis['analysis']['weaknesses'];
                                    }
                                    
                                    if (!empty($improvements)) {
                                        foreach ($improvements as $improvement) {
                                            echo '<li>' . htmlspecialchars($improvement) . '</li>';
                                        }
                                    } else {
                                        echo '<li>No improvements needed</li>';
                                    }
                                    ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <button type="button" id="interviewBtn" class="btn btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        Select for Interview
                    </button>
                    <button type="button" id="reportBtn" class="btn btn-outline">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        Report Incorrect AI Result
                    </button>
                </div>
            </div>
        </div>
    </main>

    <!-- Feedback Modal -->
    <div class="modal-overlay" id="feedbackModal">
        <div class="modal">
            <div class="modal-header">
                <h3>Report AI Analysis Issue</h3>
                <button type="button" class="modal-close" aria-label="Close modal">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <label for="feedbackText">Please describe the issue with the AI analysis:</label>
                <textarea
                    id="feedbackText"
                    placeholder="The AI missed some skills that exist in the resume..."
                ></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" id="cancelFeedback" class="btn btn-secondary">Cancel</button>
                <button type="button" id="submitFeedback" class="btn btn-primary">Submit Feedback</button>
            </div>
        </div>
    </div>

    <script src="js/app.js"></script>

    <script>
        const applicantId = document.getElementById('applicantId').value;
        const jobId = document.getElementById('jobId').value;
        const csrfToken = document.getElementById('csrfToken').value;

        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.classList.add('toast-hide');
                setTimeout(() => toast.remove(), 400);
            }, 3000);
        }

        function setMatchScore(score) {
            const circle = document.querySelector('.progress-circle');
            const valueEl = document.querySelector('.score-value');
            if (!circle || !valueEl) return;

            const radius = circle.r.baseVal.value;
            const circumference = 2 * Math.PI * radius;
            circle.style.strokeDasharray = `${circumference} ${circumference}`;
            circle.style.strokeDashoffset = circumference;

            const offset = circumference - (score / 100) * circumference;
            circle.style.strokeDashoffset = offset;

            valueEl.innerHTML = `${score}<span>%</span>`;
        }

        function ajaxPost(data) {
            return fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams(data)
            }).then(res => res.json());
        }

        document.addEventListener('DOMContentLoaded', function() {
            setMatchScore(<?php echo (int) $matchScore; ?>);
        });

        document.getElementById('interviewBtn').addEventListener('click', function() {
            if (!applicantId || !jobId) {
                showToast('Missing candidate or job information.', 'error');
                return;
            }

            if (!confirm('Are you sure you want to select this candidate for interview?')) {
                return;
            }

            ajaxPost({
                action: 'select_interview',
                applicant_id: applicantId,
                job_id: jobId,
                csrf_token: csrfToken
            })
                .then(result => {
                    if (result && result.success) {
                        showToast('Candidate selected for interview.');
                        document.getElementById('interviewBtn').disabled = true;
                    } else {
                        showToast(result.message || 'Failed to select interview', 'error');
                    }
                })
                .catch(() => showToast('Network error submitting request.', 'error'));
        });

        document.getElementById('reportBtn').addEventListener('click', function() {
            document.getElementById('feedbackModal').style.display = 'flex';
            document.getElementById('feedbackText').focus();
        });

        document.getElementById('cancelFeedback').addEventListener('click', function() {
            document.getElementById('feedbackModal').style.display = 'none';
            document.getElementById('feedbackText').value = '';
        });

        document.getElementById('submitFeedback').addEventListener('click', function() {
            const feedback = document.getElementById('feedbackText').value.trim();
            if (feedback === '') {
                showToast('Please enter your feedback.', 'error');
                return;
            }

            ajaxPost({
                action: 'submit_feedback',
                applicant_id: applicantId,
                job_id: jobId,
                feedback: feedback,
                csrf_token: csrfToken
            })
                .then(result => {
                    if (result && result.success) {
                        showToast('Feedback submitted. Thank you!');
                        document.getElementById('feedbackModal').style.display = 'none';
                        document.getElementById('feedbackText').value = '';
                    } else {
                        showToast(result.message || 'Failed to submit feedback.', 'error');
                    }
                })
                .catch(() => showToast('Network error submitting feedback.', 'error'));
        });

        // Close modal when clicking outside
        document.getElementById('feedbackModal').addEventListener('click', function(e) {
            if (e.target === this) {
                document.getElementById('feedbackModal').style.display = 'none';
                document.getElementById('feedbackText').value = '';
            }
        });
    </script>
</body>
</html>
