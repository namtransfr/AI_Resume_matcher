<?php
require_once 'config.php';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Get form data
    $name = sanitize($_POST['name']);
    $university = sanitize($_POST['university']);
    $skills = sanitize($_POST['skills']);
    $experience = sanitize($_POST['experience']);

    // Validate required fields
    if (empty($name) || empty($university) || empty($skills)) {
        header("Location: index.php?message=Please fill in all required fields&type=error");
        exit;
    }

    // Check if file was uploaded
    if (!isset($_FILES['resume']) || $_FILES['resume']['error'] !== UPLOAD_ERR_OK) {
        header("Location: index.php?message=Error uploading file&type=error");
        exit;
    }

    // Get file information
    $file = $_FILES['resume'];
    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileType = $file['type'];

    // Validate file type (only PDF)
    $allowedTypes = ['application/pdf'];
    if (!in_array($fileType, $allowedTypes)) {
        header("Location: index.php?message=Only PDF files are allowed&type=error");
        exit;
    }

    // Validate file size (max 5MB)
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($fileSize > $maxSize) {
        header("Location: index.php?message=File size must be less than 5MB&type=error");
        exit;
    }

    // Generate unique filename
    $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
    $newFileName = time() . '_' . uniqid() . '.' . $fileExtension;
    $uploadDir = __DIR__ . '/uploads/';

    // Create uploads directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Move uploaded file
    $destination = $uploadDir . $newFileName;
    if (move_uploaded_file($fileTmpName, $destination)) {

        // Insert data into database
        $stmt = $conn->prepare("INSERT INTO applicants (name, university, skills, experience, resume_file) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $name, $university, $skills, $experience, $newFileName);

        if ($stmt->execute()) {
            header("Location: index.php?message=Resume submitted successfully!&type=success");
            exit;
        } else {
            // Delete uploaded file if database insert fails
            unlink($destination);
            header("Location: index.php?message=Error saving data to database&type=error");
            exit;
        }
    } else {
        header("Location: index.php?message=Error uploading file&type=error");
        exit;
    }
} else {
    // Redirect to home if accessed directly
    header("Location: index.php");
    exit;
}
?>
