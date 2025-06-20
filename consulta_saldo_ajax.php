<?php
session_start();
header('Content-Type: application/json');

// Verificar se usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(array('erro' => true, 'mensagem' => 'Usuário não logado'));
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// 🔥 Função para obter API Key do usuário logado
function obterApiKeyUsuario($usuario_id) {
    $servername = "localhost";
    $username = "";
    $password = "";
    $database = "";

    try {
        $conn = new mysqli($servername, $username, $password, $database);
        if ($conn->connect_error) {
            throw new Exception("Erro ao conectar: " . $conn->connect_error);
        }

        $stmt = $conn->prepare("SELECT api_key FROM contas WHERE id = ?");
        if ($stmt === false) {
            throw new Exception("Erro na preparação da consulta: " . $conn->error);
        }
        
        $stmt->bind_param("s", $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $conta = $result->fetch_assoc();
        
        $stmt->close();
        $conn->close();
        
        if ($conta && !empty($conta['api_key'])) {
            return $conta['api_key'];
        } else {
            throw new Exception("API Key não encontrada para este usuário");
        }
        
    } catch (Exception $e) {
        error_log("Erro ao obter API Key: " . $e->getMessage());
        return false;
    }
}

// ✅ Função para consultar saldo da API Asaas (OTIMIZADA)
function consultarSaldoAsaas($usuario_id) {
    // 🔥 Obter API Key específica do usuário
    $api_key = obterApiKeyUsuario($usuario_id);
    
    if (!$api_key) {
        return array('erro' => true, 'mensagem' => 'API Key não encontrada', 'saldo' => 0.00);
    }
    
    // ✅ Verificar se cURL está disponível
    if (!function_exists('curl_init')) {
        return array('erro' => true, 'mensagem' => 'cURL não disponível', 'saldo' => 0.00);
    }
    
    // ✅ Inicializar cURL
    $curl = curl_init();
    
    if ($curl === false) {
        return array('erro' => true, 'mensagem' => 'Erro ao inicializar cURL', 'saldo' => 0.00);
    }
    
    // ✅ Configurar cURL (configuração otimizada)
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api-sandbox.asaas.com/v3/finance/balance",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
            "accept: application/json",
            "access_token: " . $api_key,
            "User-Agent: MatrixPixBalance/1.0",
            "Connection: close"
        ),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,  // Desabilitar temporariamente se houver problema SSL
        CURLOPT_SSL_VERIFYHOST => false,  // Desabilitar temporariamente se houver problema SSL
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_FRESH_CONNECT => true,
        CURLOPT_FORBID_REUSE => true,
        CURLOPT_DNS_CACHE_TIMEOUT => 60
    ));
    
    // ✅ Executar requisição com retry
    $tentativas = 0;
    $max_tentativas = 3;
    
    do {
        $tentativas++;
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($curl);
        $curl_errno = curl_errno($curl);
        
        // Se sucesso, sair do loop
        if ($response !== false && empty($curl_error)) {
            break;
        }
        
        // Se não é a última tentativa, aguardar um pouco
        if ($tentativas < $max_tentativas) {
            sleep(1);
        }
        
    } while ($tentativas < $max_tentativas);
    
    // ✅ Fechar conexão cURL
    curl_close($curl);
    
    // ✅ Verificar se houve erro no cURL após todas as tentativas
    if ($response === false || !empty($curl_error)) {
        // Log do erro para o administrador
        error_log("Erro cURL na consulta Asaas: #{$curl_errno} - {$curl_error}");
        
        return array(
            'erro' => true, 
            'mensagem' => 'Erro temporário na conexão. Tente novamente.', 
            'saldo' => 0.00
        );
    }
    
    // ✅ Decodificar resposta JSON
    $resultado = json_decode($response, true);
    
    // ✅ Verificar se decodificação foi bem-sucedida
    if ($resultado === null) {
        error_log("Resposta JSON inválida da API Asaas: " . $response);
        return array(
            'erro' => true, 
            'mensagem' => 'Resposta inválida da API', 
            'saldo' => 0.00
        );
    }
    
    // ✅ Processar resposta com base no código HTTP
    if ($http_code == 200 && isset($resultado['balance'])) {
        return array(
            'erro' => false, 
            'saldo' => (float)$resultado['balance']
        );
    } 
    elseif (isset($resultado['errors']) && is_array($resultado['errors'])) {
        $mensagem_erro = '';
        foreach ($resultado['errors'] as $erro) {
            if (isset($erro['description'])) {
                $mensagem_erro .= $erro['description'] . ' ';
            }
        }
        return array(
            'erro' => true, 
            'mensagem' => !empty($mensagem_erro) ? trim($mensagem_erro) : 'Erro na API', 
            'saldo' => 0.00
        );
    } 
    else {
        error_log("Resposta inesperada da API Asaas (HTTP {$http_code}): " . $response);
        return array(
            'erro' => true, 
            'mensagem' => 'Erro temporário no serviço', 
            'saldo' => 0.00
        );
    }
}

// Consultar saldo e retornar JSON
$consulta = consultarSaldoAsaas($usuario_id);
echo json_encode($consulta, JSON_UNESCAPED_UNICODE);
?>