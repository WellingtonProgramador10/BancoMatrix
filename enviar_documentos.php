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
$apiToken = '$aact_hmlg_000MzkwODA2MWY2OGM3MWRlMDU2NWM3MzJlNzZmNGZhZGY6OjAxZmM0NzY4LTVhM2ItNDJlMS1hM2ZhLThmMjI2ZTFiNzA3Yzo6JGFhY2hfNDEzOGE3NGMtMTkzZi00NmNmLTk4NmEtM2I0M2VlMTFkNTkz';

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

// ===== ABORDAGEM 1: GET documentos do cliente =====
function getClientDocuments($customerId) {
    global $apiUrl, $apiToken;
    
    $endpoint = "$apiUrl/customers/$customerId/documents";
    
    error_log("GET: Solicitando documentos do cliente ASAAS: $customerId");
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json',
        'access_token: ' . $apiToken,
        'User-Agent: MatrixApp/1.0'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    error_log("GET status: $httpCode | Erro: $curlError");
    error_log("GET response: $response");
    
    return [
        'http_code' => $httpCode,
        'response' => json_decode($response, true),
        'error' => $curlError
    ];
}

// ===== ABORDAGEM 2: POST para confirmar documentos =====
function postConfirmDocuments($customerId) {
    global $apiUrl, $apiToken;
    
    $endpoint = "$apiUrl/customers/$customerId/documents/confirm";
    
    error_log("POST: Confirmando documentos do cliente ASAAS: $customerId");
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "{}"); // POST vazio, apenas confirmar
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json',
        'Content-Type: application/json',
        'access_token: ' . $apiToken,
        'User-Agent: MatrixApp/1.0'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    error_log("POST status: $httpCode | Erro: $curlError");
    error_log("POST response: $response");
    
    return [
        'http_code' => $httpCode,
        'response' => json_decode($response, true),
        'error' => $curlError
    ];
}

// Função combinada para garantir a confirmação dos documentos
function confirmarDocumentosAsaas($customerId) {
    if (empty($customerId)) {
        return [
            'success' => false,
            'message' => 'ID de cliente ASAAS vazio.'
        ];
    }
    
    // 1. Primeiro GET para "acordar" a API
    $getResponse = getClientDocuments($customerId);
    
    // 2. Tentar POST para confirmar documentos
    $postResponse = postConfirmDocuments($customerId);
    
    // 3. GET novamente para obter o status atualizado
    $finalGetResponse = getClientDocuments($customerId);
    
    // Verificar resultados
    if ($finalGetResponse['http_code'] == 200) {
        return [
            'success' => true,
            'response' => $finalGetResponse['response'],
            'message' => 'Documentos verificados e confirmados com sucesso na API da ASAAS.',
            'post_status' => $postResponse['http_code'],
            'post_response' => $postResponse['response']
        ];
    } else {
        return [
            'success' => false,
            'response' => $finalGetResponse['response'],
            'message' => 'Erro ao verificar documentos: HTTP ' . $finalGetResponse['http_code'],
            'post_status' => $postResponse['http_code'],
            'post_response' => $postResponse['response'],
            'error' => $finalGetResponse['error']
        ];
    }
}

// Processamento do formulário
if (isset($_POST['confirmar_documentos']) && $contaId) {
    if (empty($conta['asaas_customer_id'])) {
        $erro = "Esta conta não possui um ID de cliente ASAAS vinculado.";
    } else {
        // Registrar informações para debug
        error_log("Iniciando confirmação para conta: " . $conta['id'] . " | ASAAS ID: " . $conta['asaas_customer_id']);
        
        // Confirmar documentos via API
        $resultado = confirmarDocumentosAsaas($conta['asaas_customer_id']);
        $respostaApi = $resultado['response'];
        
        if ($resultado['success']) {
            $statusDocumentos = 'confirmado';

            // Verificar status dos documentos na resposta
            if (isset($respostaApi['data']) && is_array($respostaApi['data'])) {
                $temDocumentoIdentificacao = false;
                $todosAprovados = true;
                
                foreach ($respostaApi['data'] as $doc) {
                    if ($doc['type'] === 'IDENTIFICATION') {
                        $temDocumentoIdentificacao = true;
                        if ($doc['status'] !== 'APPROVED') {
                            $todosAprovados = false;
                        }
                    }
                }
                
                if ($temDocumentoIdentificacao && $todosAprovados) {
                    $statusDocumentos = 'aprovado_asaas';
                }
            }

            // Atualizar status no banco de dados
            $sql = "UPDATE contas SET status_documentos = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $statusDocumentos, $contaId);
            $stmt->execute();

            $mensagem = $resultado['message'] . " Status atualizado para: " . $statusDocumentos;
            
            // Informações adicionais do POST (para debug)
            if (isset($resultado['post_status'])) {
                $mensagem .= " (POST status: " . $resultado['post_status'] . ")";
            }

            // Recarregar informações da conta
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
            if (!empty($resultado['error'])) {
                $erro .= " (Erro: " . $resultado['error'] . ")";
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
                <div class="card-header bg-info text-white">Resposta da API ASAAS</div>
                <div class="card-body">
                    <pre><?php echo json_encode($respostaApi, JSON_PRETTY_PRINT); ?></pre>
                    
                    <?php if (isset($resultado['post_response'])): ?>
                    <div class="mt-3">
                        <h5>Resposta da Confirmação (POST):</h5>
                        <pre><?php echo json_encode($resultado['post_response'], JSON_PRETTY_PRINT); ?></pre>
                    </div>
                    <?php endif; ?>
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
                                            <span class="badge bg-success">Vinculado</span>
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