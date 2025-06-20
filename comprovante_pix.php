<?php
// ✅ Iniciar sessão apenas se não estiver ativa
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// 🔥 Incluir biblioteca TCPDF - VERSÃO CORRIGIDA
// Tenta diferentes caminhos para encontrar a TCPDF
$tcpdf_paths = [
    'vendor/tecnickcom/tcpdf/tcpdf.php',    // Composer
    'tcpdf/tcpdf.php',                      // Download manual
    '../tcpdf/tcpdf.php',                   // Pasta pai
    'libs/tcpdf/tcpdf.php'                  // Pasta libs
];

$tcpdf_loaded = false;
foreach ($tcpdf_paths as $path) {
    if (file_exists($path)) {
        require_once($path);
        $tcpdf_loaded = true;
        break;
    }
}

// Se não encontrar TCPDF, mostrar erro amigável
if (!$tcpdf_loaded) {
    die('
    <html>
    <head>
        <title>Erro - TCPDF não encontrada</title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                background: #000; 
                color: #00ff99; 
                padding: 50px; 
                text-align: center; 
            }
            .error-box {
                background: rgba(255, 0, 0, 0.1);
                border: 2px solid #ff6b6b;
                border-radius: 10px;
                padding: 30px;
                max-width: 600px;
                margin: 0 auto;
            }
            .solution {
                background: rgba(0, 255, 153, 0.1);
                border: 1px solid #00ff99;
                border-radius: 10px;
                padding: 20px;
                margin: 20px 0;
                text-align: left;
            }
            code {
                background: #333;
                padding: 5px 10px;
                border-radius: 5px;
                color: #00ff99;
                font-family: monospace;
            }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h1>⚠️ Biblioteca TCPDF não encontrada!</h1>
            <p>Para gerar os comprovantes em PDF, você precisa instalar a biblioteca TCPDF.</p>
            
            <div class="solution">
                <h3>🔧 Solução 1 - Via Composer (Recomendado):</h3>
                <p>1. Abra o terminal/cmd na pasta do projeto</p>
                <p>2. Execute: <code>composer require tecnickcom/tcpdf</code></p>
            </div>
            
            <div class="solution">
                <h3>📁 Solução 2 - Download Manual:</h3>
                <p>1. Baixe TCPDF em: <a href="https://tcpdf.org/" style="color: #00ff99;">https://tcpdf.org/</a></p>
                <p>2. Extraia na pasta: <code>tcpdf/</code></p>
                <p>3. Certifique-se que existe: <code>tcpdf/tcpdf.php</code></p>
            </div>
            
            <p><a href="consulta_saldo.php" style="color: #00ff99;">← Voltar ao Saldo</a></p>
        </div>
    </body>
    </html>
    ');
}

// 🔥 Função para obter API Key do usuário logado
function obterApiKeyUsuario($usuario_id) {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $database = "Matrix";

    try {
        $conn = new mysqli($servername, $username, $password, $database);
        if ($conn->connect_error) {
            throw new Exception("Erro ao conectar: " . $conn->connect_error);
        }

        $stmt = $conn->prepare("SELECT api_key FROM contas WHERE id = ? LIMIT 1");
        if ($stmt === false) {
            error_log("Erro na preparação da consulta SQL: " . $conn->error);
            throw new Exception("Erro na preparação da consulta: " . $conn->error);
        }
        
        $stmt->bind_param("s", $usuario_id);
        
        if (!$stmt->execute()) {
            error_log("Erro na execução da consulta: " . $stmt->error);
            throw new Exception("Erro na execução da consulta: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        if ($result === false) {
            error_log("Erro ao obter resultado: " . $stmt->error);
            throw new Exception("Erro ao obter resultado: " . $stmt->error);
        }
        
        $conta = $result->fetch_assoc();
        
        $stmt->close();
        $conn->close();
        
        if ($conta && !empty($conta['api_key'])) {
            return $conta['api_key'];
        } else {
            error_log("API Key não encontrada para usuário: " . $usuario_id);
            throw new Exception("API Key não encontrada para este usuário");
        }
        
    } catch (Exception $e) {
        error_log("Erro ao obter API Key: " . $e->getMessage());
        return false;
    }
}

// Continue com o resto do código original...
// (O restante do código PHP permanece igual)
// 🔥 Função para buscar transações PIX
function buscarTransacoesPix($usuario_id, $startDate = null, $finishDate = null, $limit = 20) {
    $api_key = obterApiKeyUsuario($usuario_id);
    
    if (!$api_key) {
        return array('erro' => true, 'mensagem' => 'API Key não encontrada para este usuário', 'transacoes' => []);
    }
    
    // Definir datas padrão (últimos 30 dias)
    if ($startDate === null) {
        $startDate = date('Y-m-d', strtotime('-30 days'));
    }
    if ($finishDate === null) {
        $finishDate = date('Y-m-d');
    }
    
    $curl = curl_init();
    
    if ($curl === false) {
        return array('erro' => true, 'mensagem' => 'Erro ao inicializar cURL', 'transacoes' => []);
    }
    
    // Construir URL com parâmetros
    $url = "https://api-sandbox.asaas.com/v3/financialTransactions?" . http_build_query([
        'startDate' => $startDate,
        'finishDate' => $finishDate,
        'limit' => $limit,
        'offset' => 0
    ]);
    
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
            "accept: application/json",
            "access_token: " . $api_key,
            "User-Agent: MatrixPixComprovante/1.0"
        ),
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => false
    ));
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    
    curl_close($curl);
    
    if ($response === false || !empty($curl_error)) {
        return array(
            'erro' => true, 
            'mensagem' => 'Erro na conexão com API: ' . $curl_error, 
            'transacoes' => []
        );
    }
    
    $resultado = json_decode($response, true);
    
    if ($resultado === null) {
        return array(
            'erro' => true, 
            'mensagem' => 'Resposta inválida da API (JSON malformado)', 
            'transacoes' => []
        );
    }
    
    if ($http_code == 200 && isset($resultado['data'])) {
        // Filtrar apenas transações PIX
        $transacoesPix = array_filter($resultado['data'], function($transacao) {
            return isset($transacao['pixTransactionId']) || 
                   strpos(strtolower($transacao['description'] ?? ''), 'pix') !== false ||
                   in_array($transacao['type'], ['PIX_TRANSACTION_DEBIT', 'PIX_TRANSACTION_CREDIT', 'TRANSFER']);
        });
        
        return array(
            'erro' => false, 
            'transacoes' => array_values($transacoesPix),
            'mensagem' => 'Transações encontradas com sucesso'
        );
    } else {
        return array(
            'erro' => true, 
            'mensagem' => 'Erro ao buscar transações (HTTP: ' . $http_code . ')', 
            'transacoes' => []
        );
    }
}

// 🔥 Processar geração de PDF
if (isset($_POST['gerar_pdf']) && isset($_POST['transacao_id'])) {
    $transacao_id = $_POST['transacao_id'];
    $usuario_id = $_SESSION['usuario_id'];
    
    // Buscar todas as transações para encontrar a específica
    $resultado = buscarTransacoesPix($usuario_id, date('Y-m-d', strtotime('-90 days')), date('Y-m-d'), 100);
    
    if (!$resultado['erro'] && !empty($resultado['transacoes'])) {
        $transacaoSelecionada = null;
        foreach ($resultado['transacoes'] as $transacao) {
            if ($transacao['id'] === $transacao_id) {
                $transacaoSelecionada = $transacao;
                break;
            }
        }
        
        if ($transacaoSelecionada) {
            // Gerar PDF do comprovante
            gerarComprovantePDF($transacaoSelecionada, $usuario_id);
            exit;
        } else {
            $erro_msg = "Transação não encontrada.";
        }
    } else {
        $erro_msg = "Erro ao buscar transações: " . $resultado['mensagem'];
    }
}

// 🔥 Função para gerar PDF do comprovante usando TCPDF
function gerarComprovantePDF($transacao, $usuario_id) {
    // Conectar ao banco para pegar dados do usuário
    $servername = "localhost";
    $username = "root";
    $password = "";
    $database = "Matrix";

    $conn = new mysqli($servername, $username, $password, $database);
    if ($conn->connect_error) {
        die("Erro ao conectar: " . $conn->connect_error);
    }

    $stmt = $conn->prepare("SELECT * FROM contas WHERE id = ?");
    $stmt->bind_param("s", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $conta = $result->fetch_assoc();
    $conn->close();
    
    // Criar nova instância do TCPDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Configurações do documento
    $pdf->SetCreator('Banco Matrix');
    $pdf->SetAuthor('Banco Matrix');
    $pdf->SetTitle('Comprovante PIX - ' . $transacao['id']);
    $pdf->SetSubject('Comprovante de Transferência PIX');
    $pdf->SetKeywords('PIX, Comprovante, Transferência, Banco Matrix');
    
    // Remover header e footer padrão
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Configurar margens
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Adicionar página
    $pdf->AddPage();
    
    // Preparar dados
    $dataFormatada = date('d/m/Y H:i:s', strtotime($transacao['date']));
    $valorFormatado = 'R$ ' . number_format(abs($transacao['value']), 2, ',', '.');
    $tipoTransacao = $transacao['value'] < 0 ? 'ENVIADO' : 'RECEBIDO';
    $corTipo = $transacao['value'] < 0 ? '#FF4444' : '#00FF99';
    
    // Definir fonte
    $pdf->SetFont('helvetica', '', 12);
    
    // HTML do comprovante
    $html = '
    <style>
        .header {
            text-align: center;
            background-color: #000000;
            color: #00FF99;
            padding: 20px;
            margin-bottom: 20px;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 3px;
        }
        .subtitulo {
            font-size: 14px;
            margin-top: 10px;
        }
        .status {
            text-align: center;
            margin: 20px 0;
        }
        .status-badge {
            background-color: ' . $corTipo . ';
            color: white;
            padding: 10px 20px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 16px;
            display: inline-block;
        }
        .valor-principal {
            text-align: center;
            margin: 30px 0;
        }
        .valor {
            font-size: 36px;
            font-weight: bold;
            color: #333333;
            margin: 10px 0;
        }
        .detalhes {
            border-top: 2px solid #EEEEEE;
            padding-top: 20px;
            margin-top: 20px;
        }
        .linha-detalhe {
            margin: 12px 0;
            padding: 8px 0;
            border-bottom: 1px solid #F0F0F0;
        }
        .label {
            font-weight: bold;
            color: #666666;
            display: inline-block;
            width: 40%;
        }
        .valor-detalhe {
            color: #333333;
            display: inline-block;
            width: 55%;
            text-align: right;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #EEEEEE;
            text-align: center;
            color: #666666;
            font-size: 10px;
        }
        .id-transacao {
            font-family: monospace;
            background-color: #F0F0F0;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 10px;
        }
    </style>
    
    <div class="header">
        <div class="logo">🏦 BANCO MATRIX</div>
        <div class="subtitulo">COMPROVANTE DE TRANSFERÊNCIA PIX</div>
    </div>
    
    <div class="status">
        <div class="status-badge">PIX ' . $tipoTransacao . '</div>
    </div>
    
    <div class="valor-principal">
        <div>Valor</div>
        <div class="valor">' . $valorFormatado . '</div>
    </div>
    
    <div class="detalhes">
        <div class="linha-detalhe">
            <span class="label">Data e Hora:</span>
            <span class="valor-detalhe">' . $dataFormatada . '</span>
        </div>
        
        <div class="linha-detalhe">
            <span class="label">Tipo:</span>
            <span class="valor-detalhe">' . htmlspecialchars($transacao['type']) . '</span>
        </div>
        
        <div class="linha-detalhe">
            <span class="label">Descrição:</span>
            <span class="valor-detalhe">' . htmlspecialchars($transacao['description']) . '</span>
        </div>
        
        <div class="linha-detalhe">
            <span class="label">Saldo após transação:</span>
            <span class="valor-detalhe">R$ ' . number_format($transacao['balance'], 2, ',', '.') . '</span>
        </div>';
        
    if (isset($transacao['transferId'])) {
        $html .= '
        <div class="linha-detalhe">
            <span class="label">ID Transferência:</span>
            <span class="valor-detalhe id-transacao">' . htmlspecialchars($transacao['transferId']) . '</span>
        </div>';
    }
    
    if (isset($transacao['pixTransactionId'])) {
        $html .= '
        <div class="linha-detalhe">
            <span class="label">ID PIX:</span>
            <span class="valor-detalhe id-transacao">' . htmlspecialchars($transacao['pixTransactionId']) . '</span>
        </div>';
    }
    
    $html .= '
        <div class="linha-detalhe">
            <span class="label">Remetente:</span>
            <span class="valor-detalhe">' . htmlspecialchars($conta['nome'] ?? 'MARCOS TAVARES FERREIRA') . '</span>
        </div>
        
        <div class="linha-detalhe">
            <span class="label">CPF/CNPJ:</span>
            <span class="valor-detalhe">' . htmlspecialchars($conta['cpf_cnpj'] ?? '08059329847') . '</span>
        </div>
        
        <div class="linha-detalhe">
            <span class="label">Instituição:</span>
            <span class="valor-detalhe">BANCO MATRIX - 001</span>
        </div>
    </div>
    
    <div class="footer">
        <p><strong>COMPROVANTE VÁLIDO</strong></p>
        <p>Este comprovante tem validade jurídica nos termos da legislação vigente.</p>
        <p>Documento gerado em ' . date('d/m/Y H:i:s') . '</p>
        <p>ID do Comprovante: ' . htmlspecialchars($transacao['id']) . '</p>
    </div>';
    
    // Escrever HTML no PDF
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Configurar headers para download do PDF
    $filename = 'comprovante_pix_' . $transacao['id'] . '.pdf';
    
    // Limpar buffer de saída
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Gerar e enviar PDF
    $pdf->Output($filename, 'D'); // 'D' = forçar download
}

// Buscar transações PIX para exibição
$usuario_id = $_SESSION['usuario_id'];
$resultado = buscarTransacoesPix($usuario_id);

// Conectar ao banco para pegar dados do usuário
$servername = "localhost";
$username = "root";
$password = "";
$database = "Matrix";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Erro ao conectar: " . $conn->connect_error);
}

$stmt = $conn->prepare("SELECT * FROM contas WHERE id = ?");
$stmt->bind_param("s", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
$conta = $result->fetch_assoc();
$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprovante PIX - Banco Matrix</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #000 0%, #001a0d 50%, #000 100%);
            color: #00ff99;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: rgba(0, 255, 153, 0.1);
            border-radius: 15px;
            border: 1px solid #00ff99;
            backdrop-filter: blur(10px);
        }
        
        .logo {
            font-size: 28px;
            font-weight: bold;
            letter-spacing: 3px;
            text-shadow: 0 0 10px #00ff99;
        }
        
        .user-info {
            text-align: right;
        }
        
        .user-info h3 {
            margin: 0;
            color: #00ff99;
            font-size: 18px;
        }
        
        .logout {
            margin-top: 8px;
        }
        
        .logout a {
            color: #00ff99;
            text-decoration: none;
            padding: 5px 15px;
            border: 1px solid #00ff99;
            border-radius: 20px;
            transition: all 0.3s ease;
        }
        
        .logout a:hover {
            background-color: #00ff99;
            color: #000;
            box-shadow: 0 0 15px #00ff99;
        }
        
        .main-card {
            background: rgba(17, 17, 17, 0.9);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 255, 153, 0.3);
            border: 2px solid #00ff99;
            backdrop-filter: blur(10px);
        }
        
        .section-title {
            font-size: 24px;
            font-weight: bold;
            color: #00ff99;
            margin-bottom: 20px;
            text-align: center;
            text-shadow: 0 0 10px #00ff99;
        }
        
        .transacoes-list {
            max-height: 500px;
            overflow-y: auto;
        }
        
        .transacao-item {
            background: rgba(0, 255, 153, 0.05);
            border: 1px solid rgba(0, 255, 153, 0.3);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        
        .transacao-item:hover {
            background: rgba(0, 255, 153, 0.1);
            border-color: #00ff99;
            transform: translateY(-2px);
        }
        
        .transacao-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .transacao-valor {
            font-size: 18px;
            font-weight: bold;
        }
        
        .valor-positivo {
            color: #00ff99;
        }
        
        .valor-negativo {
            color: #ff6b6b;
        }
        
        .transacao-data {
            color: #00cc7a;
            font-size: 14px;
        }
        
        .transacao-descricao {
            color: #ccc;
            margin: 10px 0;
            font-size: 14px;
        }
        
        .transacao-detalhes {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 15px;
            font-size: 12px;
        }
        
        .detalhe-item {
            color: #aaa;
        }
        
        .detalhe-item strong {
            color: #00ff99;
        }
        
        .btn-comprovante {
            background: linear-gradient(45deg, #00ff99, #00cc7a);
            color: #000;
            border: none;
            padding: 10px 20px;
            border-radius: 20px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .btn-comprovante:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(0, 255, 153, 0.4);
        }
        
        .btn-comprovante:active {
            transform: scale(0.95);
        }
        
        .no-transactions {
            text-align: center;
            padding: 40px;
            color: #00cc7a;
        }
        
        .no-transactions i {
            font-size: 48px;
            margin-bottom: 20px;
            display: block;
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 15px 25px;
            background: linear-gradient(135deg, rgba(0, 255, 153, 0.1) 0%, rgba(0, 255, 153, 0.2) 100%);
            color: #00ff99;
            text-decoration: none;
            border-radius: 10px;
            border: 2px solid #00ff99;
            font-weight: bold;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .back-btn:hover {
            background: linear-gradient(135deg, #00ff99 0%, #00cc7a 100%);
            color: #000;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 255, 153, 0.4);
        }
        
        .error-message {
            background: rgba(255, 107, 107, 0.1);
            border: 1px solid #ff6b6b;
            color: #ff6b6b;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .success-message {
            background: rgba(0, 255, 153, 0.1);
            border: 1px solid #00ff99;
            color: #00ff99;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .main-card {
                padding: 20px;
            }
            
            .transacao-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            
            .transacao-detalhes {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">🏦 BANCO MATRIX</div>
            <div class="user-info">
                <h3>Olá, <?php echo htmlspecialchars($_SESSION['usuario_nome'] ?? 'Usuário'); ?></h3>
                <div class="logout">
                    <a href="logout.php">🚪 Sair</a>
                </div>
            </div>
        </div>
        
        <a href="consulta_saldo.php" class="back-btn">
            <span>⬅️</span>
            <span>Voltar ao Saldo</span>
        </a>
        
        <div class="main-card">
            <div class="section-title">
                📄 COMPROVANTES PIX DISPONÍVEIS
            </div>
            
            <?php if (isset($erro_msg)): ?>
                <div class="error-message">
                    ⚠️ <?php echo htmlspecialchars($erro_msg); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!$resultado['erro'] && !empty($resultado['transacoes'])): ?>
                <div class="transacoes-list">
                    <?php foreach ($resultado['transacoes'] as $transacao): ?>
                        <div class="transacao-item">
                            <div class="transacao-header">
                                <div class="transacao-valor <?php echo $transacao['value'] < 0 ? 'valor-negativo' : 'valor-positivo'; ?>">
                                    <?php echo $transacao['value'] < 0 ? '-' : '+'; ?> R$ <?php echo number_format(abs($transacao['value']), 2, ',', '.'); ?>
                                </div>
                                <div class="transacao-data">
                                    📅 <?php echo date('d/m/Y H:i', strtotime($transacao['date'])); ?>
                                </div>
                            </div>
                            
                            <div class="transacao-descricao">
                                <?php echo htmlspecialchars($transacao['description']); ?>
                            </div>
                            
                            <div class="transacao-detalhes">
                                <div class="detalhe-item">
                                    <strong>Tipo:</strong> <?php echo $transacao['type']; ?>
                                </div>
                                <div class="detalhe-item">
                                    <strong>ID:</strong> <?php echo $transacao['id']; ?>
                                </div>
                                <?php if (isset($transacao['transferId'])): ?>
                                    <div class="detalhe-item">
                                        <strong>ID Transferência:</strong> <?php echo substr($transacao['transferId'], 0, 20) . '...'; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($transacao['pixTransactionId'])): ?>
                                    <div class="detalhe-item">
                                        <strong>ID PIX:</strong> <?php echo substr($transacao['pixTransactionId'], 0, 20) . '...'; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="detalhe-item">
                                    <strong>Saldo após:</strong> R$ <?php echo number_format($transacao['balance'], 2, ',', '.'); ?>
                                </div>
                            </div>
                            
                            <form method="POST" style="margin-top: 15px;">
                                <input type="hidden" name="transacao_id" value="<?php echo $transacao['id']; ?>">
                                <button type="submit" name="gerar_pdf" class="btn-comprovante">
                                    📄 Gerar Comprovante PDF
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif (!$resultado['erro'] && empty($resultado['transacoes'])): ?>
                <div class="no-transactions">
                    <i>📭</i>
                    <h3>Nenhuma transação PIX encontrada</h3>
                    <p>Você ainda não realizou nenhuma transação PIX nos últimos 30 dias.</p>
                </div>
            <?php else: ?>
                <div class="error-message">
                    ⚠️ Erro ao carregar transações: <?php echo htmlspecialchars($resultado['mensagem']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Animação ao carregar
        document.addEventListener('DOMContentLoaded', function() {
            const items = document.querySelectorAll('.transacao-item');
            items.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    item.style.transition = 'all 0.5s ease';
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
        
        // Efeito nos botões
        document.querySelectorAll('.btn-comprovante').forEach(button => {
            button.addEventListener('click', function(e) {
                // Adicionar efeito visual de clique
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = 'scale(1.05)';
                }, 100);
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 200);
                
                // Mostrar loading
                const originalText = this.textContent;
                this.textContent = '📄 Gerando PDF...';
                this.disabled = true;
                
                // Restaurar após 3 segundos (caso não redirecione)
                setTimeout(() => {
                    this.textContent = originalText;
                    this.disabled = false;
                }, 3000);
            });
        });
        
        // Efeito hover nos itens de transação
        document.querySelectorAll('.transacao-item').forEach(item => {
            item.addEventListener('mouseenter', function() {
                this.style.boxShadow = '0 5px 20px rgba(0, 255, 153, 0.2)';
            });
            
            item.addEventListener('mouseleave', function() {
                this.style.boxShadow = 'none';
            });
        });
        
        // Smooth scroll para lista de transações
        const transacoesList = document.querySelector('.transacoes-list');
        if (transacoesList) {
            transacoesList.style.scrollBehavior = 'smooth';
        }
        
        // Adicionar efeito de digitação no título
        function typeWriter(element, text, speed = 100) {
            let i = 0;
            element.innerHTML = '';
            
            function type() {
                if (i < text.length) {
                    element.innerHTML += text.charAt(i);
                    i++;
                    setTimeout(type, speed);
                }
            }
            type();
        }
        
        // Aplicar efeito de digitação no título principal
        const sectionTitle = document.querySelector('.section-title');
        if (sectionTitle) {
            const originalText = sectionTitle.textContent;
            typeWriter(sectionTitle, originalText, 50);
        }
        
        // Adicionar efeito de pulso no logo
        const logo = document.querySelector('.logo');
        if (logo) {
            setInterval(() => {
                logo.style.textShadow = '0 0 20px #00ff99, 0 0 30px #00ff99, 0 0 40px #00ff99';
                setTimeout(() => {
                    logo.style.textShadow = '0 0 10px #00ff99';
                }, 500);
            }, 3000);
        }
        
        // Efeito matrix rain no fundo (opcional)
        function createMatrixRain() {
            const canvas = document.createElement('canvas');
            canvas.style.position = 'fixed';
            canvas.style.top = '0';
            canvas.style.left = '0';
            canvas.style.width = '100%';
            canvas.style.height = '100%';
            canvas.style.zIndex = '-1';
            canvas.style.pointerEvents = 'none';
            canvas.style.opacity = '0.1';
            document.body.appendChild(canvas);
            
            const ctx = canvas.getContext('2d');
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            
            const chars = '0123456789ABCDEF';
            const charArray = chars.split('');
            const fontSize = 14;
            const columns = canvas.width / fontSize;
            const drops = [];
            
            for (let x = 0; x < columns; x++) {
                drops[x] = 1;
            }
            
            function draw() {
                ctx.fillStyle = 'rgba(0, 0, 0, 0.05)';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                
                ctx.fillStyle = '#00ff99';
                ctx.font = fontSize + 'px monospace';
                
                for (let i = 0; i < drops.length; i++) {
                    const text = charArray[Math.floor(Math.random() * charArray.length)];
                    ctx.fillText(text, i * fontSize, drops[i] * fontSize);
                    
                    if (drops[i] * fontSize > canvas.height && Math.random() > 0.975) {
                        drops[i] = 0;
                    }
                    drops[i]++;
                }
            }
            
            setInterval(draw, 100);
            
            // Redimensionar canvas quando a janela mudar
            window.addEventListener('resize', () => {
                canvas.width = window.innerWidth;
                canvas.height = window.innerHeight;
            });
        }
        
        // Ativar matrix rain apenas em telas maiores
        if (window.innerWidth > 768) {
            createMatrixRain();
        }
        
        // Adicionar tooltips nos detalhes das transações
        document.querySelectorAll('.detalhe-item').forEach(item => {
            item.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.02)';
                this.style.backgroundColor = 'rgba(0, 255, 153, 0.1)';
                this.style.borderRadius = '5px';
                this.style.padding = '5px';
                this.style.transition = 'all 0.3s ease';
            });
            
            item.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
                this.style.backgroundColor = 'transparent';
                this.style.padding = '0';
            });
        });
        
        // Contador de transações
        const transacaoItems = document.querySelectorAll('.transacao-item');
        if (transacaoItems.length > 0) {
            console.log(`💰 Total de transações PIX encontradas: ${transacaoItems.length}`);
        }
        
        // Adicionar atalhos de teclado
        document.addEventListener('keydown', function(e) {
            // ESC para voltar
            if (e.key === 'Escape') {
                window.location.href = 'consulta_saldo.php';
            }
            
            // F5 para atualizar
            if (e.key === 'F5') {
                e.preventDefault();
                window.location.reload();
            }
        });
        
        // Notificação de sucesso/erro
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 10px;
                color: white;
                font-weight: bold;
                z-index: 9999;
                transform: translateX(100%);
                transition: transform 0.3s ease;
                ${type === 'success' ? 'background: linear-gradient(45deg, #00ff99, #00cc7a);' : 'background: linear-gradient(45deg, #ff6b6b, #ff5252);'}
            `;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);
            
            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }
        
        // Verificar se há mensagem de sucesso na URL
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('success')) {
            showNotification('✅ Comprovante gerado com sucesso!', 'success');
        }
        if (urlParams.get('error')) {
            showNotification('❌ Erro ao gerar comprovante!', 'error');
        }
    </script>
</body>
</html>