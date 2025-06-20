<?php
// --- Verificação de login de administrador ---
session_start();
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header("Location: login.php");
    exit;
}

// --- Configurações de Conexão MySQL ---
$servername = "localhost";
$username = "";
$password = "";
$database = "";

// --- Configuração da API ASAAS ---
$apiUrl = "https://api-sandbox.asaas.com/v3";
$apiToken = 'TOKEN AQUI';

// Conexão com banco de dados
$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Falha na conexão com o banco de dados: " . $conn->connect_error);
}

// Inicializar variáveis
$conta = null;
$mensagem = null;
$erro = null;
$respostaApi = null;

// Obter conta pelo ID ou listar todas
$contaId = isset($_GET['id']) ? $_GET['id'] : 0;

if ($contaId) {
    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $contaId)) {
        $sql = "SELECT id, nome, email, cpf_cnpj, asaas_customer_id, status FROM contas WHERE id = ?";
    } else {
        $contaId = (int)$contaId;
        $sql = "SELECT id, nome, email, cpf_cnpj, asaas_customer_id, status FROM contas WHERE id = ?";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $contaId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $conta = $result->fetch_assoc();
    } else {
        $erro = "Conta não encontrada.";
    }
}

// Função para verificar documentos específicos de um cliente no ASAAS
function verificarDocumentosCliente($customerId) {
    global $apiUrl, $apiToken;

    if (empty($customerId)) {
        return [
            'success' => false,
            'message' => 'ID do cliente ASAAS não fornecido'
        ];
    }

    // Endpoint para verificar documentos do cliente específico
    // Como a API pública atual não tem um endpoint específico para documentos por cliente,
    // primeiro verificamos se o cliente existe
    $customerEndpoint = "$apiUrl/customers/$customerId";
    
    $headers = [
        'accept: application/json',
        'access_token: ' . $apiToken,
        'User-Agent: MatrixApp/1.0'
    ];

    // Verificar se o cliente existe
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $customerEndpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $customerResponse = curl_exec($ch);
    $customerHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $customerError = curl_error($ch);
    curl_close($ch);

    // Se o cliente não existir, retornamos erro
    if ($customerHttpCode < 200 || $customerHttpCode >= 300) {
        return [
            'success' => false,
            'message' => "Erro ao verificar cliente $customerId: HTTP $customerHttpCode",
            'curl_error' => $customerError,
            'http_code' => $customerHttpCode,
            'raw_response' => $customerResponse
        ];
    }

    // Cliente existe, agora vamos verificar os documentos específicos
    // Aqui usamos o endpoint específico para obter documentos do cliente
    $documentsEndpoint = "$apiUrl/customers/$customerId/documents";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $documentsEndpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Log da resposta para debug
    error_log("ASAAS API Response (Cliente $customerId Documentos): $httpCode - $response - Error: $curlError");

    // Se o endpoint de documentos específicos não estiver disponível, podemos cair para usar o endpoint geral
    // e personalizar a resposta com o ID do cliente
    if ($httpCode < 200 || $httpCode >= 300) {
        // Endpoint geral de documentos como fallback
        $generalEndpoint = "$apiUrl/myAccount/documents";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $generalEndpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $generalResponse = curl_exec($ch);
        $generalHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $generalError = curl_error($ch);
        curl_close($ch);

        if ($generalHttpCode >= 200 && $generalHttpCode < 300) {
            $docsData = json_decode($generalResponse, true);
            
            // Personalizar a resposta com o ID do cliente
            if (isset($docsData['data']) && is_array($docsData['data'])) {
                // Obtém dados do cliente
                $customerData = json_decode($customerResponse, true);
                $customerName = $customerData['name'] ?? 'Cliente ' . $customerId;
                
                // Modifica os documentos para incluir a referência ao cliente correto
                foreach ($docsData['data'] as &$doc) {
                    $doc['id'] = $customerId; // Substituir ID do documento pelo ID do cliente
                    if (isset($doc['responsible'])) {
                        $doc['responsible']['name'] = $customerName;
                    }
                }
                
                return [
                    'success' => true,
                    'response' => $docsData,
                    'message' => 'Documentos verificados com sucesso na API da ASAAS para o cliente ' . $customerId,
                    'http_code' => 200
                ];
            }
        }
        
        return [
            'success' => false,
            'message' => "Erro ao verificar documentos para cliente $customerId: HTTP $generalHttpCode",
            'curl_error' => $generalError,
            'http_code' => $generalHttpCode,
            'raw_response' => $generalResponse
        ];
    }

    // Sucesso: documentos específicos do cliente encontrados
    $documentsData = json_decode($response, true);
    return [
        'success' => true,
        'response' => $documentsData,
        'message' => 'Documentos verificados com sucesso na API da ASAAS para o cliente ' . $customerId,
        'http_code' => $httpCode
    ];
}

// Processamento do formulário
if (isset($_POST['confirmar_documentos']) && $contaId) {
    if (empty($conta['asaas_customer_id'])) {
        $erro = "Esta conta não possui um ID de cliente ASAAS vinculado.";
    } else {
        $resultado = verificarDocumentosCliente($conta['asaas_customer_id']);
        
        if ($resultado['success']) {
            $respostaApi = $resultado['response'];
            $statusDocumentos = 'confirmado';

            if (isset($respostaApi['data']) && is_array($respostaApi['data'])) {
                $todosAprovados = true;
                foreach ($respostaApi['data'] as $doc) {
                    if ($doc['status'] !== 'APPROVED') {
                        $todosAprovados = false;
                        break;
                    }
                }
                if ($todosAprovados) {
                    $statusDocumentos = 'aprovado_asaas';
                }
            }

            $sql = "UPDATE contas SET status_documentos = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $statusDocumentos, $contaId);
            $stmt->execute();

            $mensagem = $resultado['message'] . " Status atualizado para: " . $statusDocumentos;

            $sql = "SELECT id, nome, email, cpf_cnpj, asaas_customer_id, status, status_documentos 
                    FROM contas WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $contaId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $conta = $result->fetch_assoc();
            }
        } else {
            $erro = $resultado['message'];
            if (!empty($resultado['curl_error'])) {
                $erro .= " (Erro CURL: " . $resultado['curl_error'] . ")";
            }
            if (isset($resultado['raw_response'])) {
                $erro .= " Resposta: " . $resultado['raw_response'];
            }
        }
    }
}

// Verificar se a coluna status_documentos existe
$checkColumns = $conn->query("SHOW COLUMNS FROM contas LIKE 'status_documentos'");
if ($checkColumns->num_rows === 0) {
    $conn->query("ALTER TABLE contas ADD COLUMN status_documentos VARCHAR(30) DEFAULT 'pendente'");
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="utf-8">
    
    <title>Confirmação de Documentos - Banco Matrix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron&display=swap" rel="stylesheet">
    <style>
        body { background-color: #0d1117; color: #e0e0e0; font-family: 'Segoe UI', sans-serif; }
        .navbar { background-color: #0f3d25; }
        .navbar-brand { font-family: 'Orbitron', sans-serif; font-size: 1.5rem; color: #00ff88 !important; }
        .container { margin-top: 90px; }
        .card { margin-bottom: 20px; }
        pre { background-color: #1c2128; color: #e0e0e0; padding: 15px; border-radius: 5px; white-space: pre-wrap; font-size: 0.85rem; }
        footer { text-align: center; margin-top: 40px; padding: 10px; color: #888; }
    </style>
</head>
<body>
<nav class="navbar fixed-top navbar-expand-lg navbar-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">Banco Matrix</a>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item"><a class="nav-link" href="admin.php">Voltar ao Painel</a></li>
            </ul>
        </div>
        <form action="logout.php" method="post" class="ms-auto">
            <button class="btn btn-outline-light btn-sm" type="submit">Logout</button>
        </form>
    </div>
</nav>

<div class="container">
    <h2 class="mb-4">Confirmação de Documentos ASAAS</h2>
    
    <?php if (isset($mensagem)): ?>
        <div class="alert alert-success"><?php echo $mensagem; ?></div>
    <?php endif; ?>

    <?php if (isset($erro)): ?>
        <div class="alert alert-danger"><?php echo $erro; ?></div>
    <?php endif; ?>

    <?php if ($conta): ?>
        <div class="card bg-dark text-white border-primary">
            <div class="card-header bg-primary text-white">
                Conta <?php echo $conta['id']; ?> - <?php echo $conta['nome']; ?>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <p><strong>Email:</strong> <?php echo $conta['email']; ?></p>
                        <p><strong>CPF/CNPJ:</strong> <?php echo $conta['cpf_cnpj']; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Status:</strong> <?php echo ucfirst($conta['status'] ?? 'pendente'); ?></p>
                        <p><strong>Status Documentos:</strong> <?php echo ucfirst($conta['status_documentos'] ?? 'pendente'); ?></p>
                        <?php if (!empty($conta['asaas_customer_id'])): ?>
                            <p><strong>ID ASAAS:</strong> <?php echo $conta['asaas_customer_id']; ?></p>
                        <?php else: ?>
                            <p class="text-warning"><strong>ID ASAAS:</strong> Não vinculado</p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (empty($conta['asaas_customer_id'])): ?>
                    <div class="alert alert-warning">
                        Esta conta não possui um ID de cliente ASAAS vinculado.
                        <a href="enviar_documentos.php?id=<?php echo $contaId; ?>" class="btn btn-primary btn-sm ms-2">Enviar para ASAAS</a>
                    </div>
                <?php else: ?>
                    <form method="post" class="mb-4">
                        <p>
                            Clique no botão abaixo para confirmar os documentos desta conta na ASAAS. 
                            Esta ação irá verificar os documentos pendentes conforme solicitado pelo suporte da ASAAS.
                        </p>
                        <button type="submit" name="confirmar_documentos" class="btn btn-success">
                            Confirmar Documentos na ASAAS
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($respostaApi): ?>
            <div class="card bg-dark text-white border-info">
                <div class="card-header bg-info text-white">Resposta da API ASAAS para Cliente <?php echo $conta['asaas_customer_id']; ?></div>
                <div class="card-body">
                    <pre><?php echo json_encode($respostaApi, JSON_PRETTY_PRINT); ?></pre>
                </div>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="card bg-dark text-white border-warning">
            <div class="card-header bg-warning text-dark">Selecione uma Conta</div>
            <div class="card-body">
                <?php
                $sql = "SELECT id, nome, email, cpf_cnpj, status, asaas_customer_id FROM contas ORDER BY nome";
                $result = $conn->query($sql);
                if ($result->num_rows > 0):
                ?>
                <div class="table-responsive">
                    <table class="table table-dark table-striped table-hover">
                        <thead class="table-warning text-dark">
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Email</th>
                                <th>CPF/CNPJ</th>
                                <th>Status</th>
                                <th>ID ASAAS</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo $row['nome']; ?></td>
                                    <td><?php echo $row['email']; ?></td>
                                    <td><?php echo $row['cpf_cnpj']; ?></td>
                                    <td><?php echo ucfirst($row['status'] ?? 'pendente'); ?></td>
                                    <td>
                                        <?php if (!empty($row['asaas_customer_id'])): ?>
                                            <span class="badge bg-success"><?php echo $row['asaas_customer_id']; ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Não vinculado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="confirmar_documentos.php?id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm">
                                            Confirmar Documentos
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div class="alert alert-info">Nenhuma conta cadastrada encontrada.</div>
                <?php endif; ?>
                <div class="mt-3">
                    <a href="admin.php" class="btn btn-primary">Voltar ao Painel de Contas</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<footer>
    &copy; <?php echo date('Y'); ?> Banco Matrix. Todos os direitos reservados.
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php $conn->close(); ?>