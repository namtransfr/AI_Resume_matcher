<?php
/**
 * Ascenda - AI Resume Matcher
 * Supabase Configuration & Helper Functions
 */

// Hide PHP warnings from UI
error_reporting(0);
ini_set('display_errors', 0);

define('GROQ_API_URL', '');
define('GROQ_MODEL', 'llama-3.1-8b-instant');
// Supabase Credentials
define('SUPABASE_URL', '');
define('SUPABASE_API_KEY', '');
define('GROQ_API_KEY', '');

/**
 * Sanitize input data
 */
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Make an HTTP request using PHP (fallback to curl executable if available)
 */
function httpRequest($method, $url, $data = null, $headers = array()) {
    // Try to use curl.exe on Windows
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        return curlExecRequest($method, $url, $data, $headers);
    }
    
    // Fallback to stream context
    return streamContextRequest($method, $url, $data, $headers);
}

/**
 * Execute HTTP request using curl.exe
 */
function curlExecRequest($method, $url, $data = null, $headers = array()) {
    $cmd = 'C:\\Windows\\System32\\curl.exe -X ' . escapeshellarg($method) . ' ';
    $cmd .= '--silent ';
    
    // Add headers
    foreach ($headers as $key => $value) {
        $cmd .= '-H ' . escapeshellarg("$key: $value") . ' ';
    }
    
    // Add data for POST/PATCH
    $temp_file = null;
    if ($data && in_array(strtoupper($method), ['POST', 'PATCH', 'PUT'])) {
        // Use temporary file to pass JSON data
        $temp_file = tempnam(sys_get_temp_dir(), 'curl_');
        file_put_contents($temp_file, json_encode($data));
        $cmd .= '-d @' . escapeshellarg($temp_file) . ' ';
    }
    
    $cmd .= escapeshellarg($url);
    
    $output = shell_exec($cmd);
    
    // Debug: Log the command and response
    error_log("CURL CMD: " . $cmd);
    error_log("CURL OUT: " . substr($output, 0, 500));
    
    if ($temp_file && file_exists($temp_file)) {
        @unlink($temp_file);
    }
    
    return array(
        'data' => $output ? json_decode($output, true) ?? array() : array(),
        'http_code' => 200,  // curl.exe without -w flag returns just response body
        'success' => $output !== null && !empty($output) && is_array(json_decode($output, true))
    );
}

/**
 * Execute HTTP request using stream context (for file_get_contents)
 */
function streamContextRequest($method, $url, $data = null, $headers = array()) {
    $header_string = '';
    foreach ($headers as $key => $value) {
        $header_string .= "$key: $value\r\n";
    }
    
    $opts = array(
        'http' => array(
            'method' => $method,
            'header' => $header_string,
        ),
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
        )
    );
    
    if ($data && in_array($method, ['POST', 'PATCH', 'PUT'])) {
        $body = is_string($data) ? $data : json_encode($data);
        $opts['http']['content'] = $body;
        $opts['http']['header'] .= 'Content-Length: ' . strlen($body) . "\r\n";
    }
    
    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);
    $http_code = 500;
    
    if (isset($http_response_header)) {
        foreach ($http_response_header as $header) {
            if (preg_match('/HTTP\/\d+\.\d+ (\d+)/', $header, $matches)) {
                $http_code = intval($matches[1]);
                break;
            }
        }
    }
    
    return array(
        'data' => $response ? json_decode($response, true) ?? array() : array(),
        'http_code' => $http_code,
        'success' => $http_code >= 200 && $http_code < 300
    );
}

/**
 * Make a GET request to Supabase REST API
 * 
 * @param string $table Table name
 * @param string $query Query parameters (e.g., "?select=*&id=eq.1")
 * @param array $filters Additional filters
 * @return array Decoded response
 */
function supabaseGET($table, $query = '', $filters = array()) {
    $url = SUPABASE_URL . '/rest/v1/' . $table . $query;
    
    $headers = array(
        'Content-Type' => 'application/json',
        'apikey' => SUPABASE_API_KEY,
        'Authorization' => 'Bearer ' . SUPABASE_API_KEY
    );
    
    return httpRequest('GET', $url, null, $headers);
}

/**
 * Make a POST request to Supabase REST API
 * 
 * @param string $table Table name
 * @param array $data Data to insert
 * @return array Decoded response with status
 */
function supabasePOST($table, $data = array()) {
    $url = SUPABASE_URL . '/rest/v1/' . $table;
    
    $headers = array(
        'Content-Type' => 'application/json',
        'apikey' => SUPABASE_API_KEY,
        'Authorization' => 'Bearer ' . SUPABASE_API_KEY,
        'Prefer' => 'return=representation'
    );
    
    return httpRequest('POST', $url, $data, $headers);
}

/**
 * Make a PATCH request to Supabase REST API (for updates)
 * 
 * @param string $table Table name
 * @param array $data Data to update
 * @param string $filter Filter condition (e.g., "id=eq.1")
 * @return array Decoded response with status
 */
function supabasePATCH($table, $data = array(), $filter = '') {
    $url = SUPABASE_URL . '/rest/v1/' . $table;
    if (!empty($filter)) {
        $url .= '?' . $filter;
    }
    
    $headers = array(
        'Content-Type' => 'application/json',
        'apikey' => SUPABASE_API_KEY,
        'Authorization' => 'Bearer ' . SUPABASE_API_KEY,
        'Prefer' => 'return=representation'
    );
    
    return httpRequest('PATCH', $url, $data, $headers);
}

/**
 * Make a DELETE request to Supabase REST API
 * 
 * @param string $table Table name
 * @param string $filter Filter condition (e.g., "id=eq.1")
 * @return array Response status
 */
function supabaseDELETE($table, $filter = '') {
    $url = SUPABASE_URL . '/rest/v1/' . $table;
    if (!empty($filter)) {
        $url .= '?' . $filter;
    }
    
    $headers = array(
        'Content-Type' => 'application/json',
        'apikey' => SUPABASE_API_KEY,
        'Authorization' => 'Bearer ' . SUPABASE_API_KEY
    );
    
    return httpRequest('DELETE', $url, null, $headers);
}

?>
