<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
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

// ✅ Função para consultar saldo da API Asaas
function consultarSaldoAsaas($usuario_id) {
    // 🔥 Obter API Key específica do usuário
    $api_key = obterApiKeyUsuario($usuario_id);
    
    if (!$api_key) {
        return array('erro' => true, 'mensagem' => 'API Key não encontrada', 'saldo' => 0.00);
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
            'mensagem' => 'Erro na conexão: ' . $curl_error, 
            'saldo' => 0.00
        );
    }
    
    // ✅ Decodificar resposta JSON
    $resultado = json_decode($response, true);
    
    // ✅ Verificar se decodificação foi bem-sucedida
    if ($resultado === null) {
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
        return array(
            'erro' => true, 
            'mensagem' => 'Resposta inesperada (HTTP: ' . $http_code . ')', 
            'saldo' => 0.00
        );
    }
}

// ✅ Função simplificada para obter apenas o saldo
function obterSaldoReal($usuario_id) {
    $consulta = consultarSaldoAsaas($usuario_id);
    return isset($consulta['saldo']) ? $consulta['saldo'] : 0.00;
}

// Conectar ao banco de dados
$servername = "localhost";
$username = "root";
$password = "";
$database = "Matrix";

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

// Processar transferência PIX
$mensagem = '';
$sucesso = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 🔥 Obter API Key específica do usuário para a transferência    
    $api_key = obterApiKeyUsuario($usuario_id);
    
    if (!$api_key) {
        $mensagem = "❌ Erro: API Key não encontrada para este usuário.";
        $sucesso = false;
    } else {
        $pixAddressKey = $_POST['pixAddressKey'];
        $pixAddressKeyType = $_POST['pixAddressKeyType'];
        $description = $_POST['description'];
        $scheduleDate = $_POST['scheduleDate'];
        $externalReference = $_POST['externalReference'];
        $value = $_POST['value'];
        
        // 🔥 VALIDAR SALDO ANTES DA TRANSFERÊNCIA (com API Key do usuário)
        $saldoAtualValidacao = obterSaldoReal($usuario_id);
        
        if ($value > $saldoAtualValidacao) {
            $mensagem = "❌ <strong>Saldo insuficiente!</strong><br>";
            $mensagem .= "Valor solicitado: R$ " . number_format($value, 2, ',', '.') . "<br>";
            $mensagem .= "Saldo disponível: R$ " . number_format($saldoAtualValidacao, 2, ',', '.');
            $sucesso = false;
        } else {
            $data = [
                "operationType" => "PIX",
                "pixAddressKey" => $pixAddressKey,
                "pixAddressKeyType" => $pixAddressKeyType,
                "description" => $description,
                "externalReference" => $externalReference,
                "value" => (float)$value
            ];
            
            if (!empty($scheduleDate)) {
                $data["scheduleDate"] = $scheduleDate;
            }
            
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://api-sandbox.asaas.com/v3/transfers",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => [
                    "accept: application/json",
                    "access_token: $api_key",
                    "content-type: application/json",
                    "User-Agent: MatrixPixTransfer/1.0"
                ],
                CURLOPT_HEADER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 15
            ]);
            
            $response = curl_exec($curl);
            $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            $header = substr($response, 0, $header_size);
            $body = substr($response, $header_size);
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $err = curl_error($curl);
            curl_close($curl);
            
            // LOG: salvar tudo
            $log = "Data: " . date('Y-m-d H:i:s') . "\n";
            $log .= "Usuário ID: $usuario_id\n";
            $log .= "API Key: " . substr($api_key, 0, 20) . "...\n";
            $log .= "HTTP Code: $http_code\n";
            $log .= "Saldo antes da transferência: R$ " . number_format($saldoAtualValidacao, 2, ',', '.') . "\n";
            $log .= "Dados enviados: " . json_encode($data) . "\n";
            $log .= "Resposta bruta: $body\n";
            $log .= "Erro: $err\n\n";
            file_put_contents('asaas_transfer_log.txt', $log, FILE_APPEND);
            
            if ($err) {
                $mensagem = "❌ Erro na requisição: $err";
                $sucesso = false;
            } else {
                $resultado = json_decode($body, true);
                if (isset($resultado['errors'])) {
                    $mensagem = "❌ Erro na transferência:<br>";
                    foreach ($resultado['errors'] as $erro) {
                        if ($erro['code'] == 'invalid_action' && strpos($erro['description'], 'Saldo insuficiente') !== false) {
                            $mensagem .= "• <strong>Saldo insuficiente para realizar a operação</strong><br>";
                        } else {
                            $mensagem .= "• " . $erro['description'] . "<br>";
                        }
                    }
                    $sucesso = false;
                } elseif (!empty($resultado)) {
                    $mensagem = "✅ Transferência PIX realizada com sucesso!<br>";
                    $mensagem .= "Valor transferido: R$ " . number_format($value, 2, ',', '.') . "<br>";
                    $mensagem .= "Para: " . $pixAddressKey . "<br>";
                    $mensagem .= "ID da transferência: " . (isset($resultado['id']) ? $resultado['id'] : 'N/A');
                    $sucesso = true;
                    
                    // 🔥 ATUALIZAR SALDO APÓS TRANSFERÊNCIA BEM-SUCEDIDA
                    $saldoAtual = obterSaldoReal($usuario_id);
                } else {
                    $mensagem = "⚠️ A resposta da API está vazia ou malformada.";
                    $sucesso = false;
                }
            }
        }
    } // 🔥 CHAVE FECHADA CORRIGIDA AQUI
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head><meta charset="utf-8">
    
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transferência PIX - Banco Matrix</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #000;
            color: #00ff99;
        }
        
        .container {
            max-width: 800px;
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
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 0 10px rgba(0, 255, 153, 0.2);
            border: 1px solid #00ff99;
        }
        
        .back-btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #333;
            color: #00ff99;
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #00ff99;
        }
        
        .back-btn:hover {
            background-color: #00ff99;
            color: #000;
        }
        
        h2 {
            color: #00ff99;
            margin-top: 0;
            border-bottom: 1px solid #00ff99;
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            color: #00ff99;
            font-weight: bold;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 12px;
            background-color: #222;
            border: 1px solid #00ff99;
            border-radius: 5px;
            color: #00ff99;
            font-size: 16px;
            box-sizing: border-box;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #00cc7a;
            box-shadow: 0 0 5px rgba(0, 255, 153, 0.3);
        }
        
        .btn {
            padding: 15px 30px;
            background-color: #00ff99;
            color: #000;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 16px;
            width: 100%;
            box-sizing: border-box;
        }
        
        .btn:hover {
            background-color: #00cc7a;
        }
        
        .btn:disabled {
            background-color: #666;
            cursor: not-allowed;
        }
        
        .saldo-info {
            background-color: #222;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #00ff99;
            text-align: center;
            position: relative;
        }
        
        .saldo-info h3 {
            margin: 0;
            color: #00ff99;
        }
        
        .saldo-real-badge {
            position: absolute;
            top: 5px;
            right: 10px;
            background-color: #00ff99;
            color: #000;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: bold;
        }
        
        .resultado {
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: bold;
            text-align: center;
        }
        
        .sucesso {
            background-color: rgba(0, 255, 0, 0.1);
            border: 1px solid #00ff00;
            color: #00ff00;
        }
        
        .erro {
            background-color: rgba(255, 0, 0, 0.1);
            border: 1px solid #ff0000;
            color: #ff4444;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        small {
            color: #888;
            font-style: italic;
        }
        
        .refresh-balance {
            background: none;
            border: none;
            color: #00ff99;
            cursor: pointer;
            font-size: 14px;
            margin-left: 10px;
            text-decoration: underline;
        }
        
        .refresh-balance:hover {
            color: #00cc7a;
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
        
        <a href="cliente.php" class="back-btn">← Voltar ao Painel</a>
        
        <div class="card">
            <h2>📤 Transferência PIX</h2>
            
            <div class="saldo-info">
                <span class="saldo-real-badge">SALDO REAL</span>
                <h3>Saldo Disponível: R$ <?php echo number_format($saldoAtual, 2, ',', '.'); ?></h3>
                <button type="button" class="refresh-balance" onclick="location.reload()">🔄 Atualizar</button>
            </div>
            
            <?php if (!empty($mensagem)): ?>
                <div class="resultado <?php echo $sucesso ? 'sucesso' : 'erro'; ?>">
                    <?php echo $mensagem; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" onsubmit="return validarTransferencia()">
                <div class="form-group">
                    <label for="pixAddressKey">Chave PIX do Destinatário:</label>
                    <input type="text" id="pixAddressKey" name="pixAddressKey" required 
                           placeholder="CPF, CNPJ, e-mail, telefone ou chave aleatória">
                </div>
                
                <div class="form-group">
                    <label for="pixAddressKeyType">Tipo da Chave PIX:</label>
                    <select id="pixAddressKeyType" name="pixAddressKeyType" required>
                        <option value="">Selecione o tipo</option>
                        <option value="CPF">CPF</option>
                        <option value="CNPJ">CNPJ</option>
                        <option value="EMAIL">E-mail</option>
                        <option value="PHONE">Telefone</option>
                        <option value="EVP">Chave Aleatória</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="value">Valor (R$):</label>
                        <input type="number" id="value" name="value" step="0.01" min="0.01" required 
                               placeholder="0,00" max="<?php echo $saldoAtual; ?>">
                        <small>Máximo: R$ <?php echo number_format($saldoAtual, 2, ',', '.'); ?></small>
                    </div>
                    
                    <div class="form-group">
                        <label for="scheduleDate">Data de Agendamento (opcional):</label>
                        <input type="date" id="scheduleDate" name="scheduleDate" min="<?php echo date('Y-m-d'); ?>">
                        <small>Deixe em branco para transferência imediata</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Descrição:</label>
                    <input type="text" id="description" name="description" 
                           placeholder="Descrição da transferência" maxlength="100">
                </div>
                
                <div class="form-group">
                    <label for="externalReference">Referência Externa (opcional):</label>
                    <input type="text" id="externalReference" name="externalReference" 
                           placeholder="Sua referência interna" maxlength="50">
                </div>
                
                <button type="submit" class="btn" id="btnTransferir">🚀 Realizar Transferência PIX</button>
            </form>
        </div>
    </div>
    
    <script>
        const saldoDisponivel = <?php echo $saldoAtual; ?>;
        
        // Validação antes do envio
        function validarTransferencia() {
            const valor = parseFloat(document.getElementById('value').value);
            const chave = document.getElementById('pixAddressKey').value.trim();
            const tipo = document.getElementById('pixAddressKeyType').value;
            
            if (!chave || !tipo) {
                alert('❌ Preencha todos os campos obrigatórios!');
                return false;
            }
            
            if (isNaN(valor) || valor <= 0) {
                alert('❌ Informe um valor válido!');
                return false;
            }
            
            if (valor > saldoDisponivel) {
                alert(`❌ Saldo insuficiente!\nValor: R$ ${valor.toFixed(2).replace('.', ',')}\nSaldo: R$ ${saldoDisponivel.toFixed(2).replace('.', ',')}`);
                return false;
            }
            
            const confirmacao = confirm(`🔥 Confirmar transferência PIX?\n\nValor: R$ ${valor.toFixed(2).replace('.', ',')}\nPara: ${chave}\nTipo: ${tipo}`);
            
            if (confirmacao) {
                document.getElementById('btnTransferir').disabled = true;
                document.getElementById('btnTransferir').innerHTML = '⏳ Processando...';
            }
            
            return confirmacao;
        }
        
        // Auto-formatação do valor
        document.getElementById('value').addEventListener('input', function(e) {
            let value = parseFloat(e.target.value);
            
            if (!isNaN(value)) {
                // Verificar se excede o saldo
                if (value > saldoDisponivel) {
                    e.target.style.borderColor = '#ff0000';
                    e.target.style.boxShadow = '0 0 5px rgba(255, 0, 0, 0.5)';
                } else {
                    e.target.style.borderColor = '#00ff99';
                    e.target.style.boxShadow = '0 0 5px rgba(0, 255, 153, 0.3)';
                }
                
                // Limitar a 2 casas decimais
                if (e.target.value.includes('.') && e.target.value.split('.')[1].length > 2) {
                    e.target.value = value.toFixed(2);
                }
            }
        });
        
        // Validação do CPF/CNPJ
        document.getElementById('pixAddressKey').addEventListener('input', function(e) {
            const tipo = document.getElementById('pixAddressKeyType').value;
            let value = e.target.value;
            
            if (tipo === 'CPF' && value.length > 11) {
                e.target.value = value.substring(0, 11);
            } else if (tipo === 'CNPJ' && value.length > 14) {
                e.target.value = value.substring(0, 14);
            }
        });
        
        // Auto-selecionar tipo baseado na chave digitada
        document.getElementById('pixAddressKey').addEventListener('blur', function(e) {
            const value = e.target.value.replace(/\D/g, '');
            const select = document.getElementById('pixAddressKeyType');
            
            if (value.length === 11) {
                select.value = 'CPF';
            } else if (value.length === 14) {
                select.value = 'CNPJ';
            } else if (e.target.value.includes('@')) {
                select.value = 'EMAIL';
            } else if (value.length >= 10 && value.length <= 11 && e.target.value.includes('(')) {
                select.value = 'PHONE';
            }
        });
        
        // Auto-refresh do saldo a cada 30 segundos
        setInterval(function() {
            // Atualizar apenas se não estiver fazendo uma transferência
            if (!document.getElementById('btnTransferir').disabled) {
                const xhr = new XMLHttpRequest();
                xhr.open('GET', 'consulta_saldo_ajax.php', true);
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        const response = JSON.parse(xhr.responseText);
                        if (response.saldo !== undefined) {
                            const saldoElement = document.querySelector('.saldo-info h3');
                            saldoElement.innerHTML = `Saldo Disponível: R$ ${response.saldo.toFixed(2).replace('.', ',')}`;
                        }
                    }
                };
                xhr.send();
            }
        }, 30000); // 30 segundos
    </script>
</body>
</html>