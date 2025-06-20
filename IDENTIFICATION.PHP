<?php
// Exemplo simples de como confirmar o documento IDENTIFICATION via API ASAAS

// Parâmetros necessários
$apiToken = 'TOKEN AQUI';
$apiUrl = 'https://api-sandbox.asaas.com/v3';
$customerId = 'cus_000006674980'; // Substitua pelo ID real do cliente
$documentId = '53724532-69d5-4021-8220-6c77bb723704'; // ID do documento IDENTIFICATION obtido da API

// Endpoint para confirmar documento para um cliente específico
$endpointConfirmacao = "$apiUrl/customers/$customerId/documents";

// Payload específico para IDENTIFICATION - conforme solicitado pelo suporte ASAAS
$data = [
    'type' => 'IDENTIFICATION',
    'documentId' => $documentId
];

$headers = [
    'Content-Type: application/json',
    'access_token: ' . $apiToken
];

// Inicializar cURL para confirmar documento
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $endpointConfirmacao);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Apenas para ambiente de teste

// Executar a requisição
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

// Registrar resposta para debug
echo "Código HTTP: $httpCode\n";
echo "Resposta: $response\n";
echo "Erro CURL: $curlError\n";

curl_close($ch);

// Resultado em formato de array para fácil manipulação
$resultado = [
    'success' => ($httpCode >= 200 && $httpCode < 300),
    'http_code' => $httpCode,
    'response' => json_decode($response, true),
    'curl_error' => $curlError
];

// Exibir resultado formatado
echo "\nResultado processado:\n";
print_r($resultado);
?>