<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// handle file-uploaded form vs JSON API
if (!empty($_FILES) && isset($_FILES['resume_file'])) {
    // replicate logic from php/submit_resume.php for uploaded resume
    $upload_dir = __DIR__ . '/../uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $file = $_FILES['resume_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>'File upload error']);
        exit();
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>'Only PDF files allowed']);
        exit();
    }
    $max_size = 10*1024*1024;
    if ($file['size'] > $max_size) {
        http_response_code(413);
        echo json_encode(['status'=>'error','message'=>'File too large']);
        exit();
    }

    $newname = uniqid('resume_',true) . '_' . time() . '.' . $ext;
    $dest = $upload_dir . $newname;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        http_response_code(500);
        echo json_encode(['status'=>'error','message'=>'Failed to move uploaded file']);
        exit();
    }

    // gather other POST fields
    $name = sanitize($_POST['name'] ?? '');
    $university = sanitize($_POST['university'] ?? '');
    $skills = sanitize($_POST['skills'] ?? '');
    $experience = sanitize($_POST['experience'] ?? '');
    if (empty($name) || empty($university) || empty($skills)) {
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>'Name, university, and skills are required']);
        exit;
    }
    $applicant_data = [
        'NAME'=>$name,
        'university'=>$university,
        'skills'=>$skills,
        'experience'=>$experience ?: 'Not specified',
        'resume_file'=>$newname
    ];
} else {
    // existing JSON flow
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON input']);
        exit;
    }
    // Validate required fields
    $name = sanitize($input['name'] ?? '');
    $university = sanitize($input['university'] ?? '');
    $skills = sanitize($input['skills'] ?? '');
    $experience = sanitize($input['experience'] ?? '');

    if (empty($name) || empty($university) || empty($skills)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Name, university, and skills are required']);
        exit;
    }
    // Prepare data for Supabase
    $applicant_data = [
        'NAME' => $name,
        'university' => $university,
        'skills' => $skills,
        'experience' => $experience ?: 'Not specified'
    ];
}

// Insert into Supabase 'applicants' table
$result = supabasePOST('applicants', $applicant_data);

// debug: log response
error_log('API submit_resume POST response: ' . json_encode($result));

if ($result['success']) {
    if (isset($result['data']['code']) && $result['data']['code'] === '42501') {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Database policy prevents saving resume. Check Supabase RLS configuration.'
        ]);
    } else {
        http_response_code(201);
        echo json_encode([
            'status' => 'success',
            'message' => 'Resume submitted successfully'
        ]);
    }
} else {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to save resume',
        'debug' => $result
    ]);
}
?>