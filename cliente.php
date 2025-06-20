<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
?>

<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// ✅ Incluir o arquivo de consulta de saldo
include 'consulta_saldo.php';

// Conectar ao banco de dados
$servername = "localhost";
$username = "";
$password = "";
$database = "";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Erro ao conectar: " . $conn->connect_error);
}

// Converter usuario_id para o tipo correto (varchar na tabela contas)
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

// ✅ Obter saldo atual da API
$saldoAtual = obterSaldoFormatado();

$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head><meta charset="utf-8">
    
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Área do Cliente - Banco Matrix</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #000;
            color: #00ff99;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #00ff99;
        }
        
        .logo {
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 2px;
        }
        
        .user-info {
            text-align: right;
        }
        
        .logout {
            margin-top: 5px;
        }
        
        .logout a {
            color: #00ff99;
            text-decoration: none;
        }
        
        .logout a:hover {
            text-decoration: underline;
        }
        
        .card {
            background-color: #111;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 0 10px rgba(0, 255, 153, 0.2);
            border: 1px solid #00ff99;
        }
        
        .dashboard {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 20px;
        }
        
        .account-info h3, .pix-operations h3 {
            color: #00ff99;
            margin-top: 0;
            border-bottom: 1px solid #00ff99;
            padding-bottom: 10px;
        }
        
        .balance {
            font-size: 24px;
            font-weight: bold;
            margin: 20px 0;
        }
        
        .tabs {
            display: flex;
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            background-color: #111;
            border: 1px solid #00ff99;
            color: #00ff99;
            text-decoration: none;
            text-align: center;
            flex: 1;
        }
        
        .tab:hover {
            background-color: #00ff99;
            color: #000;
        }
        
        .tab.active {
            background-color: #00ff99;
            color: #000;
        }
        
        .tab-content {
            padding: 20px 0;
        }
        
        .btn {
            padding: 15px 30px;
            background-color: #00ff99;
            color: #000;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            font-size: 16px;
            width: 100%;
            box-sizing: border-box;
        }
        
        .btn:hover {
            background-color: #00cc7a;
        }
        
        .info-box {
            background-color: #222;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            border-left: 4px solid #00ff99;
        }
        
        .coming-soon {
            text-align: center;
            padding: 40px 20px;
            background-color: rgba(0, 255, 153, 0.05);
            border-radius: 10px;
            border: 2px dashed #00ff99;
        }
        
        .coming-soon h4 {
            margin-top: 0;
            color: #00ff99;
        }
        
        .coming-soon p {
            color: #888;
            font-style: italic;
        }

        /* ✅ Estilos para indicador de saldo */
        .saldo-info {
            position: relative;
        }
        
        .saldo-status {
            font-size: 12px;
            margin-top: 5px;
            opacity: 0.8;
        }
        
        .saldo-erro {
            color: #ff6b6b;
        }
        
        .saldo-ok {
            color: #00ff99;
        }

        /* ✅ Estilos para botão de atualizar saldo */
        .saldo-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 20px 0;
        }
        
        .btn-atualizar {
            background-color: #00ff99;
            color: #000;
            border: none;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            margin-left: 15px;
        }
        
        .btn-atualizar:hover {
            background-color: #00cc7a;
            transform: rotate(180deg);
        }
        
        .btn-atualizar:disabled {
            background-color: #666;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-atualizar.loading {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .saldo-valor {
            font-size: 24px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">BANCO MATRIX</div>
            <div class="user-info">
                <div>Olá, <?php echo $_SESSION['usuario_nome']; ?></div>
                <div class="logout"><a href="logout.php">Sair</a></div>
            </div>
        </div>
        
        <div class="dashboard">
            <div class="account-info">
                <div class="card">
                    <h3>Informações da Conta</h3>
                    <p><strong>Nome:</strong> <?php echo $conta['nome']; ?></p>
                    <p><strong>Agência:</strong> <?php echo $conta['agencia'] ?? '0001'; ?></p>
                    <p><strong>Conta:</strong> <?php echo $conta['conta_numero'] ?? '000000'; ?>-<?php echo $conta['digito_conta'] ?? '0'; ?></p>
                    <p><strong>CPF/CNPJ:</strong> <?php echo $conta['cpf_cnpj']; ?></p>
                    <div class="balance">
                        <div class="saldo-info">
                            <div class="saldo-container">
                                <div>
                                    <p><strong>Saldo:</strong> <span id="saldo-valor" class="saldo-valor">R$ <?php echo number_format($saldoAtual, 2, ',', '.'); ?></span></p>
                                </div>
                                <button id="btn-atualizar-saldo" class="btn-atualizar" title="Atualizar Saldo">
                                    🔄
                                </button>
                            </div>
                            <div id="saldo-status" class="saldo-status saldo-ok">
                                ✅ Conectado à API Asaas
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="pix-operations">
                <div class="card">
                    <h3>Operações PIX</h3>
                    
                    <div class="tabs">
                        <a href="gerador_pix.php" class="tab">Gerar PIX</a>
                        <div class="tab">Criar Chave</div>
                        <a href="transferencia_pix.php" class="tab">Enviar PIX</a>
                    </div>
                    
                    <div class="tab-content">
                        <div class="info-box">
                            <h4>🔐 Gerar PIX Aleatório</h4>
                            <p>Clique no botão abaixo para gerar uma chave PIX aleatória para sua conta:</p>
                            <a href="gerador_pix.php" class="btn">Gerar PIX Aleatório</a>
                        </div>
                        
                        <div class="info-box">
                            <h4>📤 Transferir PIX</h4>
                            <p>Envie dinheiro instantaneamente para qualquer chave PIX:</p>
                            <a href="transferencia_pix.php" class="btn">Transferir PIX</a>
                        </div>
                        
                        <div class="coming-soon">
                            <h4>🚧 Criar Chave Personalizada</h4>
                            <p>A funcionalidade "Criar Chave" personalizada estará disponível em breve!</p>
                            <p><small>Aguarde as próximas atualizações do sistema.</small></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Script para destacar a aba ativa quando voltar da página
        document.addEventListener('DOMContentLoaded', function() {
            // Se vier da página do gerador PIX ou transferência, destacar a aba
            if (document.referrer.includes('gerador_pix.php')) {
                const tabs = document.querySelectorAll('.tab');
                tabs.forEach(tab => tab.classList.remove('active'));
                tabs[0].classList.add('active'); // Primeira aba (Gerar PIX)
            } else if (document.referrer.includes('transferencia_pix.php')) {
                const tabs = document.querySelectorAll('.tab');
                tabs.forEach(tab => tab.classList.remove('active'));
                tabs[2].classList.add('active'); // Terceira aba (Enviar PIX)
            }
        });

        // ✅ Função para atualizar saldo via AJAX
        function atualizarSaldo() {
            const btnAtualizar = document.getElementById('btn-atualizar-saldo');
            const saldoValor = document.getElementById('saldo-valor');
            const saldoStatus = document.getElementById('saldo-status');
            
            // ✅ Desabilitar botão e mostrar loading
            btnAtualizar.disabled = true;
            btnAtualizar.classList.add('loading');
            saldoStatus.className = 'saldo-status';
            saldoStatus.textContent = '🔄 Atualizando saldo...';
            
            // ✅ Fazer requisição AJAX
            fetch('ajax_saldo.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.erro) {
                    // ✅ Erro na consulta
                    saldoStatus.className = 'saldo-status saldo-erro';
                    saldoStatus.textContent = '❌ ' + data.mensagem;
                } else {
                    // ✅ Sucesso - atualizar saldo
                    saldoValor.textContent = data.saldo_formatado;
                    saldoStatus.className = 'saldo-status saldo-ok';
                    saldoStatus.textContent = '✅ Saldo atualizado com sucesso!';
                    
                    // ✅ Animação de sucesso
                    saldoValor.style.animation = 'none';
                    setTimeout(() => {
                        saldoValor.style.animation = 'pulse 0.6s ease-in-out';
                    }, 10);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                saldoStatus.className = 'saldo-status saldo-erro';
                saldoStatus.textContent = '❌ Erro de conexão';
            })
            .finally(() => {
                // ✅ Reabilitar botão
                setTimeout(() => {
                    btnAtualizar.disabled = false;
                    btnAtualizar.classList.remove('loading');
                }, 1000);
            });
        }

        // ✅ Adicionar evento de clique no botão
        document.addEventListener('DOMContentLoaded', function() {
            const btnAtualizar = document.getElementById('btn-atualizar-saldo');
            if (btnAtualizar) {
                btnAtualizar.addEventListener('click', atualizarSaldo);
            }
        });

        // ✅ Animação de pulse para o saldo
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.05); color: #00ff99; }
                100% { transform: scale(1); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>