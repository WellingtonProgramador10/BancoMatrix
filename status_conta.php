<?php
header('Content-Type: application/json');

$customerId = $_GET['customer_id'];
$api_key = 'TOKEN AQUI';

$url = "https://api-sandbox.asaas.com/v3/customers/" . urlencode($customerId);

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => "GET",
    CURLOPT_HTTPHEADER => [
        "accept: application/json",
        "access_token: $api_key",
        "User-Agent: MatrixPixStatus/1.0"
    ]
]);

$response = curl_exec($curl);
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$err = curl_error($curl);
curl_close($curl);

echo json_encode([
    'http_code' => $http_code,
    'err' => $err,
    'response' => json_decode($response, true)
]);
?>
