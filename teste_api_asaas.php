<?php
/**
 * Script para testar diretamente a API ASAAS para confirmação de documentos
 * Este arquivo pode ser executado separadamente para testar a comunicação com a API
 */

// --- Configuração da API ASAAS ---
$apiUrl = "https://api-sandbox.asaas.com/v3";
$apiToken = 'TOKEN AQUI';

// Função para verificar documentos disponíveis na ASAAS
function verificarDocumentosAsaas() {
    global $apiUrl, $apiToken;
    
    // Endpoint para verificar documentos 
    $endpoint = "$apiUrl/myAccount/documents";
    
    $headers = [
        'accept: application/json',
        'access_token: ' . $apiToken,
        'User-Agent: MatrixApp/1.0'
    ];
    
    // Inicializar cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Apenas para ambiente de teste
    
    // Executar a requisição
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    echo "=== VERIFICAÇÃO DE DOCUMENTOS ===\n";
    echo "Status: $httpCode\n";
    echo "Resposta: $response\n";
    echo "Erro (se houver): $curlError\n\n";
    
    return json_decode($response, true);
}

// Função para enviar confirmação de um documento IDENTIFICATION para um cliente específico
function confirmarDocumentoIdentificacao($customerId, $documentId) {
    global $apiUrl, $apiToken;
    
    // Endpoint para enviar documento para um cliente específico
    $endpoint = "$apiUrl/customers/$customerId/documents";
    
    // Dados do documento a ser enviado
    $data = [
        'type' => 'IDENTIFICATION',
        'documentId' => $documentId
    ];
    
    $headers = [
        'Content-Type: application/json',
        'access_token: ' . $apiToken,
        'User-Agent: MatrixApp/1.0'
    ];
    
    // Inicializar cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Apenas para ambiente de teste
    
    // Executar a requisição
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    echo "=== CONFIRMAÇÃO DE DOCUMENTO ===\n";
    echo "Cliente ID: $customerId\n";
    echo "Documento ID: $documentId\n";
    echo "Status: $httpCode\n";
    echo "Resposta: $response\n";
    echo "Erro (se houver): $curlError\n\n";
    
    return [
        'success' => ($httpCode >= 200 && $httpCode < 300),
        'http_code' => $httpCode,
        'response' => json_decode($response, true),
        'curl_error' => $curlError
    ];
}

// Execução do teste

// 1. Verificar documentos disponíveis
$documentos = verificarDocumentosAsaas();

// Identificar documentos do tipo IDENTIFICATION
$documentosIdentificacao = [];
if (isset($documentos['data']) && is_array($documentos['data'])) {
    foreach ($documentos['data'] as $doc) {
        if ($doc['type'] === 'IDENTIFICATION') {
            echo "Encontrado documento IDENTIFICATION: {$doc['id']}\n";
            $documentosIdentificacao[] = $doc['id'];
        }
    }
}

if (empty($documentosIdentificacao)) {
    echo "ERRO: Nenhum documento de identificação encontrado!\n";
    exit;
}

// 2. Define o customerId para teste - SUBSTITUIR pelo ID real do cliente
$customerId = "cus_000006674980"; // Substitua pelo ID do cliente

// 3. Confirmar o primeiro documento encontrado
if (!empty($documentosIdentificacao)) {
    $documentId = $documentosIdentificacao[0];
    echo "Enviando confirmação para o documento ID: $documentId\n";
    $resultado = confirmarDocumentoIdentificacao($customerId, $documentId);
    
    echo "Resultado da confirmação: " . ($resultado['success'] ? "SUCESSO" : "FALHA") . "\n";
    if (isset($resultado['response'])) {
        echo "Resposta detalhada: " . json_encode($resultado['response'], JSON_PRETTY_PRINT) . "\n";
    }
}
?>
