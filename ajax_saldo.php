<?php
// ✅ Configurações de erro e timeout
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);
ini_set('max_execution_time', 30);
ini_set('memory_limit', '128M');

// ✅ Iniciar sessão apenas se não estiver ativa
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ✅ Verificar autenticação
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(array(
        'erro' => true, 
        'mensagem' => 'Usuário não autenticado',
        'saldo' => 0.00,
        'saldo_formatado' => 'R$ 0,00'
    ));
    exit;
}

try {
    // ✅ Incluir arquivo de consulta
    if (file_exists('consulta_saldo.php')) {
        require_once 'consulta_saldo.php';
    } else {
        throw new Exception('Arquivo consulta_saldo.php não encontrado');
    }
    
    // ✅ Consultar saldo passando o ID do usuário da sessão
    $usuario_id = $_SESSION['usuario_id'];
    $resultado = consultarSaldoAsaas($usuario_id);
    
    // ✅ Verificar se função retornou dados válidos
    if (!is_array($resultado)) {
        throw new Exception('Resposta inválida da função de consulta');
    }
    
    // ✅ Preparar resposta
    $saldo = isset($resultado['saldo']) ? (float)$resultado['saldo'] : 0.00;
    $resposta = array(
        'erro' => isset($resultado['erro']) ? $resultado['erro'] : false,
        'saldo' => $saldo,
        'saldo_formatado' => 'R$ ' . number_format($saldo, 2, ',', '.'),
        'mensagem' => isset($resultado['mensagem']) ? $resultado['mensagem'] : 'Saldo atualizado com sucesso!'
    );
    
    // ✅ Log para debug (opcional)
    error_log("AJAX Saldo - Usuário: " . $usuario_id . " | Saldo: " . $saldo);
    
} catch (Exception $e) {
    // ✅ Capturar qualquer erro
    error_log("Erro em ajax_saldo.php: " . $e->getMessage());
    $resposta = array(
        'erro' => true,
        'saldo' => 0.00,
        'saldo_formatado' => 'R$ 0,00',
        'mensagem' => 'Erro interno: ' . $e->getMessage()
    );
}

// ✅ Retornar resposta JSON
header('Content-Type: application/json');
echo json_encode($resposta);
exit;
?>