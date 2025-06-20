<?php
// ✅ Iniciar sessão apenas se não estiver ativa
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    // Se for uma requisição AJAX, retornar JSON
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['erro' => true, 'mensagem' => 'Usuário não logado', 'saldo' => 0.00]);
        exit;
    }
    // Se não for AJAX, redirecionar para login
    header('Location: login.php');
    exit;
}

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

        // ✅ CORREÇÃO: Verificar se a tabela e coluna existem
        $stmt = $conn->prepare("SELECT api_key FROM contas WHERE id = ? LIMIT 1");
        if ($stmt === false) {
            // ✅ Log detalhado do erro
            error_log("Erro na preparação da consulta SQL: " . $conn->error);
            error_log("Query tentada: SELECT api_key FROM contas WHERE id = ?");
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

// ✅ Função para consultar saldo da API Asaas (CORRIGIDA)
function consultarSaldoAsaas($usuario_id = null) {
    // ✅ Se não foi passado usuario_id, pegar da sessão
    if ($usuario_id === null) {
        if (isset($_SESSION['usuario_id'])) {
            $usuario_id = $_SESSION['usuario_id'];
        } else {
            return array('erro' => true, 'mensagem' => 'Usuário não identificado', 'saldo' => 0.00);
        }
    }
    
    // 🔥 Obter API Key específica do usuário
    $api_key = obterApiKeyUsuario($usuario_id);
    
    if (!$api_key) {
        return array('erro' => true, 'mensagem' => 'API Key não encontrada para este usuário', 'saldo' => 0.00);
    }
    
    // ✅ Inicializar cURL
    $curl = curl_init();
    
    if ($curl === false) {
        return array('erro' => true, 'mensagem' => 'Erro ao inicializar cURL', 'saldo' => 0.00);
    }
    
    // ✅ Configurar cURL
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api-sandbox.asaas.com/v3/finance/balance",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
            "accept: application/json",
            "access_token: " . $api_key,
            "User-Agent: MatrixPixBalance/1.0"
        ),
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => false
    ));
    
    // ✅ Executar requisição
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    
    // ✅ Fechar conexão cURL
    curl_close($curl);
    
    // ✅ Verificar se houve erro no cURL
    if ($response === false || !empty($curl_error)) {
        return array(
            'erro' => true, 
            'mensagem' => 'Erro na conexão com API: ' . $curl_error, 
            'saldo' => 0.00
        );
    }
    
    // ✅ Decodificar resposta JSON
    $resultado = json_decode($response, true);
    
    // ✅ Verificar se decodificação foi bem-sucedida
    if ($resultado === null) {
        return array(
            'erro' => true, 
            'mensagem' => 'Resposta inválida da API (JSON malformado)', 
            'saldo' => 0.00
        );
    }
    
    // ✅ Processar resposta com base no código HTTP
    if ($http_code == 200 && isset($resultado['balance'])) {
        return array(
            'erro' => false, 
            'saldo' => (float)$resultado['balance'],
            'mensagem' => 'Saldo consultado com sucesso'
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
        return array(
            'erro' => true, 
            'mensagem' => 'Resposta inesperada da API (HTTP: ' . $http_code . ')', 
            'saldo' => 0.00
        );
    }
}

// ✅ Função simplificada para obter apenas o saldo (CORRIGIDA)
function obterSaldoFormatado($usuario_id = null) {
    // Se não foi passado usuario_id, tentar pegar da sessão
    if ($usuario_id === null) {
        if (isset($_SESSION['usuario_id'])) {
            $usuario_id = $_SESSION['usuario_id'];
        } else {
            return 0.00; // Retorna 0 se não há usuário logado
        }
    }
    
    $consulta = consultarSaldoAsaas($usuario_id);
    return isset($consulta['saldo']) ? $consulta['saldo'] : 0.00;
}

// 🔥 Função para obter saldo real (igual ao transferencia_pix.php)
function obterSaldoReal($usuario_id = null) {
    // Se não foi passado usuario_id, tentar pegar da sessão
    if ($usuario_id === null) {
        if (isset($_SESSION['usuario_id'])) {
            $usuario_id = $_SESSION['usuario_id'];
        } else {
            return 0.00; // Retorna 0 se não há usuário logado
        }
    }
    
    $consulta = consultarSaldoAsaas($usuario_id);
    return isset($consulta['saldo']) ? $consulta['saldo'] : 0.00;
}

// ✅ Se for uma requisição AJAX para obter apenas o saldo
if (isset($_GET['ajax']) && $_GET['ajax'] == 'saldo') {
    header('Content-Type: application/json');
    $usuario_id = $_SESSION['usuario_id'];
    $resultado = consultarSaldoAsaas($usuario_id);
    
    $resposta = array(
        'erro' => $resultado['erro'],
        'saldo' => $resultado['saldo'],
        'saldo_formatado' => 'R$ ' . number_format($resultado['saldo'], 2, ',', '.'),
        'mensagem' => $resultado['mensagem']
    );
    
    echo json_encode($resposta);
    exit;
}

// Conectar ao banco para pegar dados do usuário
$servername = "localhost";
$username = "";
$password = "";
$database = "";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Erro ao conectar: " . $conn->connect_error);
}

$usuario_id = $_SESSION['usuario_id'];

// Buscar informações da conta
$stmt = $conn->prepare("SELECT * FROM contas WHERE id = ?");
if ($stmt === false) {
    die("Erro na preparação da consulta: " . $conn->error);
}
$stmt->bind_param("s", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
$conta = $result->fetch_assoc();

// 🔥 OBTER SALDO REAL DA API ASAAS (com API Key do usuário)
$saldoAtual = obterSaldoReal($usuario_id);

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head><meta charset="utf-8">
    
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta de Saldo - Banco Matrix</title>
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
        
        .saldo-display {
            text-align: center;
            margin-bottom: 40px;
            padding: 30px;
            background: linear-gradient(135deg, rgba(0, 255, 153, 0.1) 0%, rgba(0, 255, 153, 0.05) 100%);
            border-radius: 15px;
            border: 1px solid #00ff99;
            position: relative;
            overflow: hidden;
        }
        
        .saldo-display::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(0, 255, 153, 0.1), transparent);
            animation: shimmer 3s linear infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }
        
        .saldo-label {
            font-size: 16px;
            color: #00cc7a;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .saldo-valor {
            font-size: 48px;
            font-weight: bold;
            color: #00ff99;
            text-shadow: 0 0 20px #00ff99;
            position: relative;
            z-index: 1;
        }
        
        .saldo-badge {
            position: absolute;
            top: 15px;
            right: 20px;
            background: linear-gradient(45deg, #00ff99, #00cc7a);
            color: #000;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            z-index: 2;
        }
        
        .buttons-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .action-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 20px;
            background: linear-gradient(135deg, rgba(0, 255, 153, 0.1) 0%, rgba(0, 255, 153, 0.2) 100%);
            color: #00ff99;
            text-decoration: none;
            border-radius: 15px;
            border: 2px solid #00ff99;
            font-weight: bold;
            font-size: 16px;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .action-btn:hover {
            background: linear-gradient(135deg, #00ff99 0%, #00cc7a 100%);
            color: #000;
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 255, 153, 0.4);
        }
        
        .action-btn i {
            font-size: 24px;
        }
        
        .refresh-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: linear-gradient(45deg, #00ff99, #00cc7a);
            color: #000;
            border: none;
            border-radius: 50%;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 5px 15px rgba(0, 255, 153, 0.4);
            transition: all 0.3s ease;
            z-index: 1000;
        }
        
        .refresh-btn:hover {
            transform: scale(1.1) rotate(180deg);
            box-shadow: 0 10px 25px rgba(0, 255, 153, 0.6);
        }
        
        .conta-info {
            background: rgba(0, 255, 153, 0.05);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 4px solid #00ff99;
        }
        
        .conta-info h4 {
            margin: 0 0 15px 0;
            color: #00ff99;
            font-size: 18px;
        }
        
        .conta-info p {
            margin: 5px 0;
            color: #00cc7a;
        }
        
        .loading {
            display: none;
            text-align: center;
            color: #00ff99;
            font-size: 18px;
        }
        
        .loading.show {
            display: block;
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
            
            .saldo-valor {
                font-size: 36px;
            }
            
            .buttons-container {
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
        
        <div class="main-card">
            <div class="conta-info">
                <h4>📋 Informações da Conta</h4>
                <p><strong>Nome:</strong> <?php echo htmlspecialchars($conta['nome'] ?? 'MARCOS TAVARES FERREIRA'); ?></p>
                <p><strong>Agência:</strong> 0001</p>
                <p><strong>Conta:</strong> 166456-8</p>
                <p><strong>CPF/CNPJ:</strong> <?php echo htmlspecialchars($conta['cpf_cnpj'] ?? '08059329847'); ?></p>
            </div>
            
            <div class="saldo-display">
                <div class="saldo-badge">SALDO REAL-TIME</div>
                <div class="saldo-label">Saldo Disponível</div>
                <div class="saldo-valor" id="saldo-valor">
                    R$ <?php echo number_format($saldoAtual, 2, ',', '.'); ?>
                </div>
            </div>
            
            <div class="loading" id="loading">
                🔄 Atualizando saldo...
            </div>
            
            <div class="buttons-container">
                <a href="transferencia_pix.php" class="action-btn">
                    <i>💸</i>
                    <span>Transferência PIX</span>
                </a>
                
                <a href="extrato.php" class="action-btn">
                    <i>📄</i>
                    <span>Extrato em PDF</span>
                </a>
                
                <a href="comprovante_pix.php" class="action-btn">
                    <i>🧾</i>
                    <span>Comprovante PIX</span>
                </a>
                
                <a href="cliente.php" class="action-btn">
                    <i>🏠</i>
                    <span>Painel Principal</span>
                </a>
            </div>
        </div>
    </div>
    
    <button class="refresh-btn" onclick="atualizarSaldo()" title="Atualizar Saldo">
        🔄
    </button>
    
    <script>
        // Função para atualizar saldo
        async function atualizarSaldo() {
            const loadingEl = document.getElementById('loading');
            const saldoEl = document.getElementById('saldo-valor');
            const refreshBtn = document.querySelector('.refresh-btn');
            
            try {
                loadingEl.classList.add('show');
                refreshBtn.style.transform = 'scale(1.1) rotate(360deg)';
                
                const response = await fetch('?ajax=saldo');
                const data = await response.json();
                
                if (!data.erro) {
                    saldoEl.textContent = data.saldo_formatado;
                    saldoEl.style.animation = 'pulse 0.5s ease-in-out';
                    
                    setTimeout(() => {
                        saldoEl.style.animation = '';
                    }, 500);
                } else {
                    console.error('Erro ao atualizar saldo:', data.mensagem);
                }
            } catch (error) {
                console.error('Erro na requisição:', error);
            } finally {
                loadingEl.classList.remove('show');
                refreshBtn.style.transform = '';
            }
        }
        
        // Auto-refresh a cada 30 segundos
        setInterval(atualizarSaldo, 30000);
        
        // Animação de pulse para o saldo
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.05); }
                100% { transform: scale(1); }
            }
        `;
        document.head.appendChild(style);
        
        // Efeito de hover nos botões
        document.querySelectorAll('.action-btn').forEach(btn => {
            btn.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px) scale(1.02)';
            });
            
            btn.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });
        
        // Notificação de boas-vindas
        setTimeout(() => {
            if (typeof Notification !== 'undefined' && Notification.permission === 'granted') {
                new Notification('Banco Matrix', {
                    body: 'Bem-vindo ao seu painel de saldo!',
                    icon: '/favicon.ico'
                });
            }
        }, 2000);
    </script>
</body>
</html>