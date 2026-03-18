<?php
require_once 'config.php';

// Test data
$test_data = [
    'NAME' => 'Test Direct User',
    'university' => 'Test University',
    'skills' => 'PHP, JavaScript',
    'experience' => '5 years'
];

echo "Testing Supabase POST...\n";
echo "Data: " . json_encode($test_data) . "\n\n";

$result = supabasePOST('applicants', $test_data);

echo "HTTP Code: " . $result['http_code'] . "\n";
echo "Success: " . ($result['success'] ? 'Yes' : 'No') . "\n";
echo "Response: " . json_encode($result['data'], JSON_PRETTY_PRINT) . "\n";

// Now test GET
echo "\n\nTesting Supabase GET...\n";
$get_result = supabaseGET('applicants', '?order=created_at.desc&limit=3');
echo "HTTP Code: " . $get_result['http_code'] . "\n";
echo "Success: " . ($get_result['success'] ? 'Yes' : 'No') . "\n";
echo "Count: " . count($get_result['data']) . " records\n";
echo "Data: " . json_encode($get_result['data'], JSON_PRETTY_PRINT) . "\n";
?>
