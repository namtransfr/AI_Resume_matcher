<?php
require_once 'config.php';
$data = [
    'position_name' => 'Test Job',
    'job_description' => 'desc',
    'required_skills' => 'PHP,HTML'
];
print_r(supabasePOST('jobs', $data));
?>