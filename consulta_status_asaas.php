<?php
// ✅ Configuração da API ASAAS
$api_key = 'TOKEN AQUI';

// ✅ Função para consultar status da conta geral da ASAAS
function consultarStatusContaGeral() {
    global $api_key;
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api-sandbox.asaas.com/v3/myAccount/status/",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => [
            "accept: application/json",
            "access_token: $api_key",
            "User-Agent: MatrixPixStatus/1.0"
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $err = curl_error($curl);
    curl_close($curl);
    
    if ($err) {
        return [
            'success' => false,
            'error' => $err,
            'status' => 'ERRO'
        ];
    }
    
    $resultado = json_decode($response, true);
    
    if ($http_code == 200 && isset($resultado['general'])) {
        return [
            'success' => true,
            'status' => $resultado['general'],
            'data' => $resultado
        ];
    }
    
    return [
        'success' => false,
        'status' => 'DESCONHECIDO',
        'http_code' => $http_code
    ];
}

// ✅ Função para consultar status de um cliente específico
function consultarStatusCliente($customerId) {
    global $api_key;
    
    if (empty($customerId)) {
        return [
            'success' => false,
            'status' => 'SEM_VINCULO'
        ];
    }
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api-sandbox.asaas.com/v3/customers/$customerId",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => [
            "accept: application/json",
            "access_token: $api_key"
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $err = curl_error($curl);
    curl_close($curl);
    
    if ($err) {
        return [
            'success' => false,
            'status' => 'ERRO',
            'error' => $err
        ];
    }
    
    $resultado = json_decode($response, true);
    
    if ($http_code == 200 && isset($resultado['id'])) {
        // Cliente existe na ASAAS
        return [
            'success' => true,
            'status' => 'APROVADO',
            'data' => $resultado
        ];
    } elseif ($http_code == 404) {
        return [
            'success' => false,
            'status' => 'NAO_ENCONTRADO'
        ];
    }
    
    return [
        'success' => false,
        'status' => 'ERRO_API',
        'http_code' => $http_code
    ];
}

// ✅ Função para formatar o status visualmente
function formatarStatusBadge($status) {
    switch($status) {
        case 'APPROVED':
        case 'APROVADO':
            return '<span class="badge badge-approved">✅ Aprovado</span>';
        case 'PENDING':
        case 'PENDENTE':
            return '<span class="badge badge-pending">⚠️ Pendente</span>';
        case 'REJECTED':
        case 'REJEITADO':
            return '<span class="badge badge-rejected">❌ Rejeitado</span>';
        case 'SEM_VINCULO':
            return '<span class="badge badge-not-sent">➖ Sem Vínculo</span>';
        case 'NAO_ENCONTRADO':
            return '<span class="badge badge-rejected">🔍 Não Encontrado</span>';
        case 'ERRO':
        case 'ERRO_API':
            return '<span class="badge badge-rejected">⚡ Erro de Conexão</span>';
        case 'DESCONHECIDO':
        default:
            return '<span class="badge badge-not-sent">❓ Desconhecido</span>';
    }
}

// ✅ Função para obter texto simples do status
function obterTextoStatus($status) {
    switch($status) {
        case 'APPROVED':
        case 'APROVADO':
            return 'Aprovado';
        case 'PENDING':
        case 'PENDENTE':
            return 'Pendente';
        case 'REJECTED':
        case 'REJEITADO':
            return 'Rejeitado';
        case 'SEM_VINCULO':
            return 'Sem Vínculo';
        case 'NAO_ENCONTRADO':
            return 'Não Encontrado';
        case 'ERRO':
        case 'ERRO_API':
            return 'Erro';
        case 'DESCONHECIDO':
        default:
            return 'Desconhecido';
    }
}
?>