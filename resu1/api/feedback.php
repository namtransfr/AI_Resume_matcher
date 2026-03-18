<?php
/**
 * Ascenda - Feedback API
 * Handles feedback submission for job-candidate matches
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON input']);
    exit();
}

// Validate required fields
$applicant_id = (int)($input['applicant_id'] ?? 0);
$job_id = (int)($input['job_id'] ?? 0);
$feedback_text = sanitize($input['feedback_text'] ?? '');

if (!$applicant_id || !$job_id || empty($feedback_text)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'applicant_id, job_id, and feedback_text are required']);
    exit();
}

// Prepare feedback data for Supabase
$feedback_data = [
    'applicant_id' => $applicant_id,
    'job_id' => $job_id,
    'feedback_text' => $feedback_text
];

// Insert into feedbacks table using Supabase REST API
$result = supabasePOST('feedbacks', $feedback_data);

if ($result['success']) {
    // detect row level security error in response data
    if (isset($result['data']['code']) && $result['data']['code'] === '42501') {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Database policy prevents writing feedback. Please check Supabase RLS settings.'
        ]);
    } else {
        http_response_code(201);
        echo json_encode([
            'status' => 'success',
            'message' => 'Feedback submitted successfully'
        ]);
    }
} else {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to save feedback',
        'debug' => $result
    ]);
}
?>
