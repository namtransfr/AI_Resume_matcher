<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Candidate List - Ascenda</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">

<!-- ใช้ CSS กลางเหมือนหน้า Post Job -->
<link rel="stylesheet" href="css/styles.css">

<style>

/* เฉพาะ table ของหน้านี้ */

.main-container{
max-width:1000px;
margin:auto;
padding:20px;
}

.card{
background:white;
padding:30px;
border-radius:12px;
box-shadow:0 10px 25px rgba(0,0,0,0.08);
border:1px solid var(--gray-200);
margin-top:20px;
}

.card h2{
font-family:'Poppins',sans-serif;
margin-bottom:10px;
color:var(--primary);
}

/* TABLE */

.table-container{
margin-top:20px;
overflow-x:auto;
border-radius:10px;
border:1px solid var(--gray-200);
}

.table{
width:100%;
border-collapse:collapse;
}

.table th{
background:var(--primary);
color:white;
padding:12px;
text-align:left;
}

.table td{
padding:12px;
border-bottom:1px solid var(--gray-200);
text-align:left;
}

.table tr:hover{
background:var(--gray-50);
}

/* BADGES */

.badge{
padding:4px 10px;
border-radius:20px;
font-size:12px;
font-weight:600;
}

.badge-selected{
background:#10B981;
color:white;
}

.badge-pending{
background:#E8D4EF;
color:#5E2D66;
}

/* SKILLS */

.skills-list{
font-size:13px;
color:var(--gray-500);
}


.btn{
padding:6px 12px;
border-radius:6px;
background:var(--primary);
color:white;
text-decoration:none;
font-size:13px;
border:none;
cursor:pointer;
}

.btn:hover{
opacity:0.9;
}

.btn-success{
background:#28a745;
}

.btn-success:hover{
background:#218838;
}



/* EMPTY */

.empty-state{
text-align:center;
padding:40px;
color:var(--gray-500);
}

</style>

</head>

<body>

<!-- NAVBAR (เหมือนหน้า Post Job) -->

<nav class="navbar" id="navbar">
<div class="nav-container">

<a href="index.php" class="nav-logo">Ascenda</a>

<ul class="nav-menu" id="navMenu">
<li><a href="index.php" class="nav-link">Home</a></li>
<li><a href="submit-resume.php" class="nav-link">Submit Resume</a></li>
<li><a href="add_job.php" class="nav-link">Post Job</a></li>
<li><a href="candidate_list.php" class="nav-link active">Candidates</a></li>
<li><a href="job_analysis.php" class="nav-link">Job Analysis</a></li>
</ul>

<div class="mobile-toggle" id="mobileToggle">
<span></span>
<span></span>
<span></span>
</div>

</div>
</nav>

<!-- HEADER -->

<header class="page-header">
<h1 class="page-title">Candidate List</h1>
<p class="page-subtitle">View all applicants submitted to the system</p>
</header>

<!-- MAIN -->

<main class="main-container">

<div class="card">

<h2>All Applicants</h2>

<p style="margin-top:5px;color:var(--gray-500);">
To evaluate candidates against a job position, go to 
<strong>Job Analysis</strong> and select a job.
</p>

<?php
// Hide PHP warnings from UI
error_reporting(0);
ini_set('display_errors', 0);
require_once 'config.php';

$applicants_result = supabaseGET('applicants', '?order=created_at.desc');

if ($applicants_result['success'] && !empty($applicants_result['data'])) {

echo '<div class="table-container">';
echo '<table class="table">';

echo '<tr>
<th>Name</th>
<th>University</th>
<th>Skills</th>
<th>Resume</th>
<th>Status</th>
</tr>';

foreach ($applicants_result['data'] as $applicant) {

$interview_result = supabaseGET('interviews','?applicant_id=eq.'.($applicant['applicant_id'] ?? 0));
$interview_count = $interview_result['success'] ? count($interview_result['data']) : 0;

echo '<tr>';

echo '<td>'.htmlspecialchars($applicant['NAME'] ?? 'N/A').'</td>';

echo '<td>'.htmlspecialchars($applicant['university'] ?? 'N/A').'</td>';

echo '<td class="skills-list">'.htmlspecialchars($applicant['skills'] ?? 'N/A').'</td>';

// resume column
$filePath = !empty($applicant['resume_file']) ? 'uploads/' . $applicant['resume_file'] : '';
echo '<td>';
if ($filePath) {
    echo '<a href="' . htmlspecialchars($filePath) . '" target="_blank" class="btn view-resume-btn">View Resume</a>';
} else {
    echo 'N/A';
}
echo '</td>';

echo '<td>';

if ($interview_count > 0) {

echo '<span class="badge badge-selected">Selected</span>';

}else{

echo '<span class="badge badge-pending">Pending</span>';

}

echo '</td>';

echo '</tr>';

}

echo '</table>';
echo '</div>';

}else{

echo '<div class="empty-state">';
echo '<p>No applicants found.</p>';
echo '<p><a href="index.php">Submit the first resume</a></p>';
echo '</div>';

}
?>

</div>

</main>


<!-- NAV SCRIPT -->

<script>

const mobileToggle=document.getElementById("mobileToggle");
const navMenu=document.getElementById("navMenu");

if(mobileToggle && navMenu){
mobileToggle.addEventListener("click",()=>{
navMenu.classList.toggle("active");
});
}

const navbar=document.getElementById("navbar");

window.addEventListener("scroll",()=>{
if(navbar){
if(window.scrollY>50){
navbar.classList.add("scrolled");
}else{
navbar.classList.remove("scrolled");
}
}
});

</script>



</body>
</html>