<?php
/**
 * Ascenda AI Resume Matcher
 * Supabase Configuration
 */

// Supabase credentials - Replace with your actual credentials
define('SUPABASE_URL', getenv('SUPABASE_URL') ?: 'https://your-project.supabase.co');
define('SUPABASE_KEY', getenv('SUPABASE_KEY') ?: 'your-anon-key');

/**
 * Make a request to Supabase REST API
 *
 * @param string $endpoint The API endpoint (e.g., '/rest/v1/candidates')
 * @param string $method HTTP method (GET, POST, PATCH, DELETE)
 * @param array|null $data Data to send with the request
 * @param array $headers Additional headers
 * @return array Response data
 */
function supabaseRequest($endpoint, $method = 'GET', $data = null, $headers = []) {
    $url = SUPABASE_URL . $endpoint;

    $defaultHeaders = [
        'apikey: ' . SUPABASE_KEY,
        'Authorization: Bearer ' . SUPABASE_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation'
    ];

    $allHeaders = array_merge($defaultHeaders, $headers);

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    switch ($method) {
        case 'POST':
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
            break;
        case 'PATCH':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
            break;
        case 'DELETE':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            break;
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return [
            'success' => false,
            'error' => $error,
            'http_code' => $httpCode
        ];
    }

    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'data' => json_decode($response, true),
        'http_code' => $httpCode
    ];
}

/**
 * Get candidate by ID
 *
 * @param string $candidateId
 * @return array|null
 */
function getCandidate($candidateId) {
    $endpoint = '/rest/v1/candidates?id=eq.' . urlencode($candidateId) . '&select=*';
    $result = supabaseRequest($endpoint);

    if ($result['success'] && !empty($result['data'])) {
        return $result['data'][0];
    }

    return null;
}

/**
 * Get AI analysis results for a candidate-job match
 *
 * @param string $candidateId
 * @param string $jobId
 * @return array|null
 */
function getAIAnalysis($candidateId, $jobId) {
    $endpoint = '/rest/v1/ai_analysis?candidate_id=eq.' . urlencode($candidateId)
              . '&job_id=eq.' . urlencode($jobId) . '&select=*';
    $result = supabaseRequest($endpoint);

    if ($result['success'] && !empty($result['data'])) {
        return $result['data'][0];
    }

    return null;
}

/**
 * Get job by ID
 *
 * @param string $jobId
 * @return array|null
 */
function getJob($jobId) {
    $endpoint = '/rest/v1/jobs?id=eq.' . urlencode($jobId) . '&select=*';
    $result = supabaseRequest($endpoint);

    if ($result['success'] && !empty($result['data'])) {
        return $result['data'][0];
    }

    return null;
}

/**
 * Update candidate status
 *
 * @param string $candidateId
 * @param string $status
 * @return bool
 */
function updateCandidateStatus($candidateId, $status) {
    // First check if interview record exists
    $checkResult = supabaseRequest('/rest/v1/interviews?applicant_id=eq.' . urlencode($candidateId), 'GET');
    
    if ($checkResult['success'] && !empty($checkResult['data'])) {
        // Update existing record
        $endpoint = '/rest/v1/interviews?applicant_id=eq.' . urlencode($candidateId);
        $data = [
            'status' => $status,
            'updated_at' => date('c')
        ];
        $result = supabaseRequest($endpoint, 'PATCH', $data);
        return $result['success'];
    } else {
        // Insert new record - but we need job_id, so this might fail
        // For now, return false if no existing record
        return false;
    }
}

/**
 * Submit feedback for AI analysis
 *
 * @param string $candidateId
 * @param string $feedbackText
 * @return bool
 */
function submitAIFeedback($candidateId, $feedbackText) {
    $endpoint = '/rest/v1/ai_feedback';
    $data = [
        'candidate_id' => $candidateId,
        'feedback_text' => $feedbackText,
        'created_at' => date('c')
    ];

    $result = supabaseRequest($endpoint, 'POST', $data);
    return $result['success'];
}

/**
 * Get match score level based on percentage
 *
 * @param int $score
 * @return string
 */
function getScoreLevel($score) {
    if ($score >= 70) return 'high';
    if ($score >= 50) return 'medium';
    return 'low';
}

/**
 * Get score label text
 *
 * @param int $score
 * @return string
 */
function getScoreLabel($score) {
    if ($score >= 70) return 'Strong Match';
    if ($score >= 50) return 'Moderate Match';
    return 'Low Match';
}
