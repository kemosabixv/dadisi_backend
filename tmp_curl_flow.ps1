$base = 'http://127.0.0.1:8000'
function Send($method, $path, $body, $token) {
    $headers = @{}
    # Request JSON responses to avoid HTML error pages from redirects
    $headers['Accept'] = 'application/json'
    if ($token) { $headers['Authorization'] = "Bearer $token" }
    try {
        if ($body) {
            $bodyJson = $body | ConvertTo-Json -Depth 5
            $resp = Invoke-WebRequest -Uri ($base + $path) -Method $method -Body $bodyJson -ContentType 'application/json' -Headers $headers -ErrorAction Stop
        } else {
            $resp = Invoke-WebRequest -Uri ($base + $path) -Method $method -Headers $headers -ErrorAction Stop
        }
        return @{ Status = $resp.StatusCode.value__; Body = $resp.Content }
    } catch {
        $err = $_.Exception.Response
        if ($err) {
            $sr = New-Object System.IO.StreamReader($err.GetResponseStream())
            $body = $sr.ReadToEnd()
            $status = 0
            try { $status = [int]$err.StatusCode } catch {}
            return @{ Status = $status; Body = $body }
        } else {
            return @{ Status = 0; Body = $_.Exception.Message }
        }
    }
}

Write-Host "Running auth flow against $base"`n
# Signup
$signup = Send -method 'POST' -path '/api/auth/signup' -body @{ name='Curl Flow'; email='curlflow@example.com'; password='password123'; password_confirmation='password123' } -token $null
Write-Host "--- SIGNUP ---"; Write-Host "Status: $($signup.Status)"; Write-Host "Body:"; Write-Host $signup.Body; Write-Host ''

# Login
$login = Send -method 'POST' -path '/api/auth/login' -body @{ email='curlflow@example.com'; password='password123' } -token $null
Write-Host "--- LOGIN ---"; Write-Host "Status: $($login.Status)"; Write-Host "Body:"; Write-Host $login.Body; Write-Host ''

$token = ''
try {
    $j = $login.Body | ConvertFrom-Json -ErrorAction Stop
    if ($j -ne $null) {
        if ($j.PSObject.Properties.Name -contains 'access_token') { $token = $j.access_token }
        elseif ($j.PSObject.Properties.Name -contains 'token') { $token = $j.token }
        elseif ($j.PSObject.Properties.Name -contains 'data' -and $j.data -and $j.data.token) { $token = $j.data.token }
        elseif ($j.PSObject.Properties.Name -contains 'accessToken') { $token = $j.accessToken }
    }
} catch {
    # ignore parse errors
}
Write-Host "TOKEN: $token`n"

# Get user
$user = Send -method 'GET' -path '/api/auth/user' -body $null -token $token
Write-Host "--- USER ---"; Write-Host "Status: $($user.Status)"; Write-Host "Body:"; Write-Host $user.Body; Write-Host ''

# Logout
$logout = Send -method 'POST' -path '/api/auth/logout' -body $null -token $token
Write-Host "--- LOGOUT ---"; Write-Host "Status: $($logout.Status)"; Write-Host "Body:"; Write-Host $logout.Body; Write-Host ''

# User after logout
$userAfter = Send -method 'GET' -path '/api/auth/user' -body $null -token $token
Write-Host "--- USER AFTER LOGOUT ---"; Write-Host "Status: $($userAfter.Status)"; Write-Host "Body:"; Write-Host $userAfter.Body; Write-Host ''
