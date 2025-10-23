<?php
$base='http://127.0.0.1:8000';
function request($method, $path, $data=null, $token=null) {
    $headers = ["Content-Type: application/json"];
    if ($token) $headers[] = "Authorization: Bearer $token";

    $opts = [
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers) . "\r\n",
            'ignore_errors' => true,
        ]
    ];

    if ($data !== null) {
        $opts['http']['content'] = json_encode($data);
    }

    $context = stream_context_create($opts);
    $res = @file_get_contents($base . $path, false, $context);
    $status = $http_response_header[0] ?? 'HTTP/1.1 000 No Response';
    return [$status, $res];
}

// Signup
list($h, $r) = request('POST', '/api/auth/signup', [
    'name' => 'Curl Flow',
    'email' => 'curlflow@example.com',
    'password' => 'password123',
    'password_confirmation' => 'password123',
]);
echo "--- SIGNUP ---\n";
echo "$h\n";
echo "$r\n\n";

// Login
list($h, $r) = request('POST', '/api/auth/login', [
    'email' => 'curlflow@example.com',
    'password' => 'password123',
]);
echo "--- LOGIN ---\n";
echo "$h\n";
echo "$r\n\n";

$body = json_decode($r, true) ?: [];
$token = $body['access_token'] ?? $body['token'] ?? '';
echo "TOKEN: " . ($token ?: '<none>') . "\n\n";

// Get user
list($h, $r) = request('GET', '/api/auth/user', null, $token);
echo "--- USER ---\n";
echo "$h\n";
echo "$r\n\n";

// Logout
list($h, $r) = request('POST', '/api/auth/logout', null, $token);
echo "--- LOGOUT ---\n";
echo "$h\n";
echo "$r\n\n";

// User after logout
list($h, $r) = request('GET', '/api/auth/user', null, $token);
echo "--- USER AFTER LOGOUT ---\n";
echo "$h\n";
echo "$r\n\n";

?>
