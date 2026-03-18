<?php
/**
 * Ascenda - Match Result API
 * Calculates and returns AI match scores between applicants and jobs
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

// Get parameters
$applicant_id = (int)($_GET['applicant_id'] ?? 0);
$job_id = (int)($_GET['job_id'] ?? 0);

if (!$applicant_id || !$job_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'applicant_id and job_id are required']);
    exit();
}

// Fetch applicant from Supabase
$applicant_result = supabaseGET('applicants', '?applicant_id=eq.' . $applicant_id);

if (!$applicant_result['success'] || empty($applicant_result['data'])) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Applicant not found']);
    exit();
}

$applicant = $applicant_result['data'][0];

// Fetch job from Supabase
$job_result = supabaseGET('jobs', '?job_id=eq.' . $job_id);

if (!$job_result['success'] || empty($job_result['data'])) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Job not found']);
    exit();
}

$job = $job_result['data'][0];

// Calculate match score
$applicant_skills = array_map('strtolower', array_map('trim', explode(',', $applicant['skills'] ?? '')));
$required_skills = array_map('strtolower', array_map('trim', explode(',', $job['required_skills'] ?? '')));

$applicant_skills = array_filter($applicant_skills);
$required_skills = array_filter($required_skills);

$matched_skills = array_intersect($applicant_skills, $required_skills);
$missing_skills = array_diff($required_skills, $applicant_skills);

// Calculate match percentage
$total_required = count($required_skills) > 0 ? count($required_skills) : 1;
$match_score = $total_required > 0 ? round((count($matched_skills) / $total_required) * 100) : 0;

// Generate strengths and weaknesses
$strengths = !empty($matched_skills) ? 'Strong match in: ' . implode(', ', $matched_skills) : 'No matching skills';
$weaknesses = !empty($missing_skills) ? 'Missing skills: ' . implode(', ', $missing_skills) : 'All required skills present';

// Prepare response
echo json_encode([
    'applicant_id' => $applicant_id,
    'job_id' => $job_id,
    'applicant_name' => $applicant['NAME'] ?? 'Unknown',
    'job_position' => $job['position_name'] ?? 'Unknown',
    'match_score' => $match_score,
    'matched_skills' => array_values($matched_skills),
    'missing_skills' => array_values($missing_skills),
    'strengths' => $strengths,
    'weaknesses' => $weaknesses,
    'recommendation' => $match_score >= 75 ? 'Highly Recommended' : ($match_score >= 50 ? 'Consider' : 'Not Recommended')
]);
?>
