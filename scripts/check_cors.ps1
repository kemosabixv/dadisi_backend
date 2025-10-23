$uri = 'http://127.0.0.1:8000/api/auth/login'
$headers = @{ Origin = 'http://127.0.0.1:8000' }
try {
    $req = [System.Net.WebRequest]::Create($uri)
    $req.Method = 'OPTIONS'
    foreach ($k in $headers.Keys) { $req.Headers.Add($k, $headers[$k]) }
    $resp = $req.GetResponse()
    $resp.Headers | Format-List
} catch [System.Net.WebException] {
    $resp = $_.Exception.Response
    if ($resp -ne $null) { $resp.Headers | Format-List } else { Write-Host "No response" }
}
