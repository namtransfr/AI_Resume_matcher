<?php
/**
 * Ascenda AI Resume Matcher
 * API: Update Candidate Status
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
if (empty($input['candidate_id']) || empty($input['status'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields: candidate_id and status'
    ]);
    exit();
}

$candidateId = htmlspecialchars($input['candidate_id'], ENT_QUOTES, 'UTF-8');
$status = htmlspecialchars($input['status'], ENT_QUOTES, 'UTF-8');

// Validate status values
$allowedStatuses = [
    'Interview Selected',
    'Pending Review',
    'Rejected',
    'Hired',
    'Shortlisted'
];

if (!in_array($status, $allowedStatuses)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid status value'
    ]);
    exit();
}

// Update status in Supabase
$result = updateCandidateStatus($candidateId, $status);

if ($result) {
    echo json_encode([
        'success' => true,
        'message' => 'Status updated successfully',
        'data' => [
            'candidate_id' => $candidateId,
            'status' => $status
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update status. Please try again.'
    ]);
}
