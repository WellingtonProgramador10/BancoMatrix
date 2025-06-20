<?php
header('Content-Type: application/json');

// ✅ Configurações
$apiUrl = 'https://api-sandbox.asaas.com/v3';
$apiToken = 'TOKEN AQUI';

// ✅ Forçar exibição de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ Pega o customer_id da URL
if (!isset($_GET['action']) || $_GET['action'] !== 'verificar_status') {
    echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    exit;
}

if (!isset($_GET['customer_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID do cliente não fornecido']);
    exit;
}

$customerId = $_GET['customer_id'];

// ✅ Função para consultar documentos
function verificarStatusDocumentos($customerId, $apiUrl, $apiToken) {
    $documentsEndpoint = "$apiUrl/customers/$customerId/documents";

    $headers = [
        'accept: application/json',
        'access_token: ' . $apiToken,
        'User-Agent: MatrixStatusChecker/1.0'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $documentsEndpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return [
            'success' => false,
            'message' => "Erro CURL: $err",
            'http_code' => $httpCode
        ];
    }

    $data = json_decode($response, true);

    if ($httpCode != 200 || !isset($data['data'])) {
        return [
            'success' => false,
            'message' => "Erro na resposta: " . $response,
            'http_code' => $httpCode
        ];
    }

    $todosAprovados = true;
    $responsavel = null;

    foreach ($data['data'] as $doc) {
        if (isset($doc['responsible']['name'])) {
            $responsavel = $doc['responsible']['name'];
        }
        if ($doc['status'] !== 'APPROVED') {
            $todosAprovados = false;
        }
    }

    return [
        'success' => true,
        'customer_id' => $customerId,
        'responsavel' => $responsavel,
        'aprovado' => $todosAprovados,
        'message' => $todosAprovados ? "✅ Conta Aprovada: $responsavel" : "❌ Conta NÃO Aprovada: $responsavel"
    ];
}

// ✅ Executa a verificação
$resultado = verificarStatusDocumentos($customerId, $apiUrl, $apiToken);

// ✅ Retorna o resultado em JSON
echo json_encode($resultado);
?>
