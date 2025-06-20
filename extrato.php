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

// Incluir consulta_saldo.php para funções de saldo
require_once 'consulta_saldo.php';

// 🔥 Função para obter transações financeiras da API
function obterTransacoesFinanceiras($usuario_id, $startDate = null, $finishDate = null, $limit = 100) {
    $api_key = obterApiKeyUsuario($usuario_id);
    
    if (!$api_key) {
        return array('erro' => true, 'mensagem' => 'API Key não encontrada', 'transacoes' => array());
    }
    
    // Definir datas padrão (últimos 30 dias)
    if (!$startDate) {
        $startDate = date('Y-m-d', strtotime('-30 days'));
    }
    if (!$finishDate) {
        $finishDate = date('Y-m-d');
    }
    
    $url = "https://api-sandbox.asaas.com/v3/financialTransactions";
    $url .= "?startDate=" . $startDate;
    $url .= "&finishDate=" . $finishDate;
    $url .= "&limit=" . $limit;
    $url .= "&order=desc";
    
    $curl = curl_init();
    
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
            "accept: application/json",
            "access_token: " . $api_key,
            "User-Agent: MatrixExtrato/1.0"
        ),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ));
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    curl_close($curl);
    
    if ($response === false || !empty($curl_error)) {
        return array('erro' => true, 'mensagem' => 'Erro na conexão: ' . $curl_error, 'transacoes' => array());
    }
    
    $resultado = json_decode($response, true);
    
    if ($resultado === null) {
        return array('erro' => true, 'mensagem' => 'Resposta inválida da API', 'transacoes' => array());
    }
    
    if ($http_code == 200 && isset($resultado['data'])) {
        return array(
            'erro' => false,
            'transacoes' => $resultado['data'],
            'totalCount' => $resultado['totalCount'] ?? 0,
            'mensagem' => 'Transações obtidas com sucesso'
        );
    } else {
        return array('erro' => true, 'mensagem' => 'Erro ao obter transações', 'transacoes' => array());
    }
}

// 🔥 Função para formatar tipo de transação
function formatarTipoTransacao($tipo) {
    $tipos = array(
        'PAYMENT_RECEIVED' => 'Pagamento Recebido',
        'TRANSFER' => 'Transferência PIX',
        'TRANSFER_FEE' => 'Taxa de Transferência',
        'PIX_TRANSACTION_DEBIT' => 'PIX Enviado',
        'PIX_TRANSACTION_CREDIT' => 'PIX Recebido',
        'PIX_TRANSACTION_CREDIT_FEE' => 'Taxa PIX',
        'PAYMENT_FEE' => 'Taxa de Pagamento',
        'CREDIT' => 'Crédito',
        'DEBIT' => 'Débito'
    );
    
    return isset($tipos[$tipo]) ? $tipos[$tipo] : $tipo;
}

// Processar requisição
$usuario_id = $_SESSION['usuario_id'];
$startDate = isset($_GET['startDate']) ? $_GET['startDate'] : date('Y-m-d', strtotime('-30 days'));
$finishDate = isset($_GET['finishDate']) ? $_GET['finishDate'] : date('Y-m-d');
$gerar_pdf = isset($_GET['pdf']) && $_GET['pdf'] == '1';

// Obter dados do usuário
$servername = "localhost";
$username = "root";
$password = "";
$database = "Matrix";

$conn = new mysqli($servername, $username, $password, $database);
$stmt = $conn->prepare("SELECT * FROM contas WHERE id = ?");
$stmt->bind_param("s", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
$conta = $result->fetch_assoc();
$conn->close();

// Obter transações
$resultado_transacoes = obterTransacoesFinanceiras($usuario_id, $startDate, $finishDate);
$transacoes = $resultado_transacoes['transacoes'];
$saldo_atual = obterSaldoReal($usuario_id);

// Se solicitado PDF, gerar PDF
if ($gerar_pdf) {
    // Definir cabeçalhos para PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="extrato_banco_matrix.pdf"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    // Gerar HTML para conversão em PDF
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: Arial, sans-serif; font-size: 12px; color: #333; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #00ff99; padding-bottom: 20px; }
            .logo { font-size: 24px; font-weight: bold; color: #00ff99; margin-bottom: 10px; }
            .info-conta { background: #f8f9fa; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
            .info-conta h3 { color: #00ff99; margin-bottom: 10px; }
            .periodo { text-align: center; background: #e9ecef; padding: 10px; margin-bottom: 20px; border-radius: 5px; }
            .saldo-info { text-align: right; margin-bottom: 20px; font-size: 14px; font-weight: bold; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
            th { background-color: #00ff99; color: white; font-weight: bold; }
            .valor-positivo { color: #28a745; font-weight: bold; }
            .valor-negativo { color: #dc3545; font-weight: bold; }
            .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #666; }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="logo">🏦 BANCO MATRIX</div>
            <h2>EXTRATO BANCÁRIO</h2>
        </div>
        
        <div class="info-conta">
            <h3>Informações da Conta</h3>
            <p><strong>Titular:</strong> <?php echo htmlspecialchars($conta['nome'] ?? 'MARCOS TAVARES FERREIRA'); ?></p>
            <p><strong>CPF/CNPJ:</strong> <?php echo htmlspecialchars($conta['cpf_cnpj'] ?? '08059329847'); ?></p>
            <p><strong>Agência:</strong> 0001 | <strong>Conta:</strong> 166456-8</p>
        </div>
        
        <div class="periodo">
            <strong>Período:</strong> <?php echo date('d/m/Y', strtotime($startDate)); ?> até <?php echo date('d/m/Y', strtotime($finishDate)); ?>
        </div>
        
        <div class="saldo-info">
            <p>Saldo Atual: <span class="valor-positivo">R$ <?php echo number_format($saldo_atual, 2, ',', '.'); ?></span></p>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Descrição</th>
                    <th>Tipo</th>
                    <th>Valor</th>
                    <th>Saldo</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transacoes)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 20px;">Nenhuma transação encontrada no período</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transacoes as $transacao): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($transacao['date'])); ?></td>
                            <td><?php echo htmlspecialchars($transacao['description'] ?? 'Transação'); ?></td>
                            <td><?php echo formatarTipoTransacao($transacao['type']); ?></td>
                            <td class="<?php echo $transacao['value'] >= 0 ? 'valor-positivo' : 'valor-negativo'; ?>">
                                <?php echo ($transacao['value'] >= 0 ? '+' : '') . 'R$ ' . number_format($transacao['value'], 2, ',', '.'); ?>
                            </td>
                            <td>R$ <?php echo number_format($transacao['balance'], 2, ',', '.'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div class="footer">
            <p>Documento gerado em <?php echo date('d/m/Y H:i:s'); ?></p>
            <p>Banco Matrix - Sua instituição financeira digital</p>
        </div>
    </body>
    </html>
    <?php
    $html = ob_get_clean();
    
    // Usar biblioteca simples para conversão HTML para PDF
    // Para uma implementação completa, você poderia usar bibliotecas como TCPDF, DOMPDF ou wkhtmltopdf
    echo $html;
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Extrato Bancário - Banco Matrix</title>
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
            max-width: 1200px;
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
        
        .back-btn {
            color: #00ff99;
            text-decoration: none;
            padding: 10px 20px;
            border: 1px solid #00ff99;
            border-radius: 25px;
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            background-color: #00ff99;
            color: #000;
            box-shadow: 0 0 15px #00ff99;
        }
        
        .main-card {
            background: rgba(17, 17, 17, 0.9);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 255, 153, 0.3);
            border: 2px solid #00ff99;
            backdrop-filter: blur(10px);
        }
        
        .filters {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
            gap: 15px;
            margin-bottom: 30px;
            align-items: end;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 5px;
            color: #00cc7a;
            font-size: 14px;
        }
        
        .form-group input {
            padding: 12px;
            border: 1px solid #00ff99;
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.5);
            color: #00ff99;
            font-size: 14px;
        }
        
        .btn {
            padding: 12px 20px;
            background: linear-gradient(135deg, #00ff99, #00cc7a);
            color: #000;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 255, 153, 0.4);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, rgba(0, 255, 153, 0.2), rgba(0, 255, 153, 0.3));
            color: #00ff99;
            border: 1px solid #00ff99;
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: linear-gradient(135deg, rgba(0, 255, 153, 0.1), rgba(0, 255, 153, 0.05));
            padding: 20px;
            border-radius: 15px;
            border: 1px solid #00ff99;
            text-align: center;
        }
        
        .summary-card h4 {
            margin: 0 0 10px 0;
            color: #00cc7a;
            font-size: 14px;
        }
        
        .summary-card .value {
            font-size: 24px;
            font-weight: bold;
            color: #00ff99;
        }
        
        .transactions-table {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 15px;
            overflow: hidden;
            border: 1px solid #00ff99;
        }
        
        .table-header {
            background: linear-gradient(135deg, #00ff99, #00cc7a);
            color: #000;
            padding: 15px 20px;
            font-weight: bold;
            display: grid;
            grid-template-columns: 100px 2fr 1fr 120px 120px;
            gap: 15px;
        }
        
        .transaction-row {
            padding: 15px 20px;
            border-bottom: 1px solid rgba(0, 255, 153, 0.2);
            display: grid;
            grid-template-columns: 100px 2fr 1fr 120px 120px;
            gap: 15px;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .transaction-row:hover {
            background: rgba(0, 255, 153, 0.05);
        }
        
        .transaction-row:last-child {
            border-bottom: none;
        }
        
        .valor-positivo {
            color: #28a745;
            font-weight: bold;
        }
        
        .valor-negativo {
            color: #dc3545;
            font-weight: bold;
        }
        
        .no-transactions {
            text-align: center;
            padding: 40px;
            color: #00cc7a;
        }
        
        .pdf-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .filters {
                grid-template-columns: 1fr;
            }
            
            .table-header,
            .transaction-row {
                grid-template-columns: 1fr;
                gap: 5px;
            }
            
            .summary-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">🏦 BANCO Matrix - Extrato</div>
            <a href="consulta_saldo.php" class="back-btn">← Voltar ao Saldo</a>
        </div>
        
        <div class="main-card">
            <form method="GET" class="filters">
                <div class="form-group">
                    <label for="startDate">Data Inicial:</label>
                    <input type="date" id="startDate" name="startDate" value="<?php echo $startDate; ?>">
                </div>
                
                <div class="form-group">
                    <label for="finishDate">Data Final:</label>
                    <input type="date" id="finishDate" name="finishDate" value="<?php echo $finishDate; ?>">
                </div>
                
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn">🔍 Filtrar</button>
                </div>
                
                <div class="form-group">
                    <label>&nbsp;</label>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['pdf' => '1'])); ?>" class="btn btn-secondary" target="_blank">
                        📄 Gerar PDF
                    </a>
                </div>
            </form>
            
            <div class="summary-cards">
                <div class="summary-card">
                    <h4>Saldo Atual</h4>
                    <div class="value">R$ <?php echo number_format($saldo_atual, 2, ',', '.'); ?></div>
                </div>
                
                <div class="summary-card">
                    <h4>Total de Transações</h4>
                    <div class="value"><?php echo count($transacoes); ?></div>
                </div>
                
                <div class="summary-card">
                    <h4>Período</h4>
                    <div class="value" style="font-size: 16px;">
                        <?php echo date('d/m', strtotime($startDate)); ?> - <?php echo date('d/m', strtotime($finishDate)); ?>
                    </div>
                </div>
            </div>
            
            <div class="transactions-table">
                <div class="table-header">
                    <div>Data</div>
                    <div>Descrição</div>
                    <div>Tipo</div>
                    <div>Valor</div>
                    <div>Saldo</div>
                </div>
                
                <?php if (empty($transacoes)): ?>
                    <div class="no-transactions">
                        <h3>📋 Nenhuma transação encontrada</h3>
                        <p>Não há movimentações no período selecionado.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($transacoes as $transacao): ?>
                        <div class="transaction-row">
                            <div><?php echo date('d/m/Y', strtotime($transacao['date'])); ?></div>
                            <div><?php echo htmlspecialchars($transacao['description'] ?? 'Transação'); ?></div>
                            <div><?php echo formatarTipoTransacao($transacao['type']); ?></div>
                            <div class="<?php echo $transacao['value'] >= 0 ? 'valor-positivo' : 'valor-negativo'; ?>">
                                <?php echo ($transacao['value'] >= 0 ? '+' : '') . 'R$ ' . number_format($transacao['value'], 2, ',', '.'); ?>
                            </div>
                            <div>R$ <?php echo number_format($transacao['balance'], 2, ',', '.'); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($transacoes)): ?>
            <div class="pdf-actions">
                <a href="?<?php echo http_build_query(array_merge($_GET, ['pdf' => '1'])); ?>" class="btn" target="_blank">
                    📄 Baixar Extrato em PDF
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>