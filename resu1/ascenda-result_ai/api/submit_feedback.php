<?php
/**
 * Ascenda AI Resume Matcher
 * API: Submit AI Feedback
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

require_once __DIR__ . '/../config/supabase.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (empty($input['candidate_id']) || empty($input['feedback_text'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields: candidate_id and feedback_text'
    ]);
    exit();
}

$candidateId = htmlspecialchars($input['candidate_id'], ENT_QUOTES, 'UTF-8');
$feedbackText = htmlspecialchars($input['feedback_text'], ENT_QUOTES, 'UTF-8');

// Validate feedback text length
if (strlen($feedbackText) < 10) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Feedback must be at least 10 characters long'
    ]);
    exit();
}

if (strlen($feedbackText) > 2000) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Feedback must not exceed 2000 characters'
    ]);
    exit();
}

// Submit feedback to Supabase
$result = submitAIFeedback($candidateId, $feedbackText);

if ($result) {
    echo json_encode([
        'success' => true,
        'message' => 'Feedback submitted successfully'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to submit feedback. Please try again.'
    ]);
}
