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
$apiToken = 'Token AQUI';

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
    // Verificar se contaId é um UUID ou um inteiro
    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $contaId)) {
        // É um UUID, procurar pelo id direto
        $sql = "SELECT id, nome, email, cpf_cnpj, asaas_customer_id, status 
                FROM contas WHERE id = ?";
    } else {
        // Considerar como um inteiro ID local
        $contaId = (int)$contaId;
        $sql = "SELECT id, nome, email, cpf_cnpj, asaas_customer_id, status 
                FROM contas WHERE id = ?";
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

// Função para verificar documentos na ASAAS
function verificarDocumentosAsaas($customerId = null) {
    global $apiUrl, $apiToken;
    
    // Endpoint para verificar documentos pendentes
    $endpoint = "$apiUrl/myAccount/documents";
    
    $headers = [
        'accept: application/json',
        'access_token: ' . $apiToken,
        'User-Agent: MatrixApp/1.0'
    ];
    
    // Inicializar cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Apenas para ambiente de teste
    
    // Executar a requisição
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Registrar a tentativa no log para debug
    error_log("ASAAS API Response (verificar): $httpCode - $response - Error: $curlError");
    
    if ($httpCode == 200) {
        return [
            'success' => true,
            'response' => json_decode($response, true),
            'message' => 'Documentos verificados com sucesso.',
            'http_code' => $httpCode
        ];
    } else {
        return [
            'success' => false,
            'response' => json_decode($response, true),
            'message' => 'Erro ao verificar documentos: HTTP ' . $httpCode,
            'curl_error' => $curlError,
            'http_code' => $httpCode,
            'raw_response' => $response
        ];
    }
}

// Função para enviar confirmação de documentos IDENTIFICATION para a ASAAS
function confirmarDocumentosAsaas($customerId) {
    global $apiUrl, $apiToken;
    
    // Verificar primeiro quais documentos estão disponíveis
    $verificacao = verificarDocumentosAsaas();
    
    if (!$verificacao['success']) {
        return $verificacao; // Retorna o erro da verificação
    }
    
    // Identificar os documentos do tipo IDENTIFICATION
    $documentosIdentificacao = [];
    if (isset($verificacao['response']['data']) && is_array($verificacao['response']['data'])) {
        foreach ($verificacao['response']['data'] as $doc) {
            if ($doc['type'] === 'IDENTIFICATION') {
                $documentosIdentificacao[] = $doc['id'];
            }
        }
    }
    
    if (empty($documentosIdentificacao)) {
        return [
            'success' => false,
            'message' => 'Nenhum documento de identificação (IDENTIFICATION) encontrado para confirmar.'
        ];
    }
    
    // Endpoint para enviar documento - agora usamos a API correta para confirmar os documentos
    $endpoint = "$apiUrl/customers/$customerId/documents";
    
    // Preparamos os dados para enviar - confirmando os documentos IDENTIFICATION
    // Vamos criar um payload para cada documento de identificação encontrado
    $resultados = [];
    
    foreach ($documentosIdentificacao as $docId) {
        $data = [
            'type' => 'IDENTIFICATION',
            'documentId' => $docId
        ];
        
        $headers = [
            'Content-Type: application/json',
            'access_token: ' . $apiToken,
            'User-Agent: MatrixApp/1.0'
        ];
        
        // Inicializar cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Apenas para ambiente de teste
        
        // Executar a requisição
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // Registrar a tentativa no log para debug
        error_log("ASAAS API Confirm Document Response: $httpCode - $response - Error: $curlError");
        
        $resultados[] = [
            'docId' => $docId,
            'success' => ($httpCode >= 200 && $httpCode < 300),
            'http_code' => $httpCode,
            'response' => json_decode($response, true),
            'curl_error' => $curlError
        ];
    }
    
    // Verificar se todas as confirmações foram bem-sucedidas
    $todasSucesso = true;
    foreach ($resultados as $resultado) {
        if (!$resultado['success']) {
            $todasSucesso = false;
            break;
        }
    }
    
    return [
        'success' => $todasSucesso,
        'message' => $todasSucesso ? 
            'Todos os documentos de identificação foram confirmados com sucesso.' : 
            'Houve erro ao confirmar um ou mais documentos.',
        'resultados' => $resultados,
        'documentos_confirmados' => $documentosIdentificacao
    ];
}

// Processamento do formulário para confirmar documentos
if (isset($_POST['confirmar_documentos']) && $contaId) {
    if (empty($conta['asaas_customer_id'])) {
        $erro = "Esta conta não possui um ID de cliente ASAAS vinculado.";
    } else {
        // Confirmar documentos na ASAAS - passando o customerId
        $resultado = confirmarDocumentosAsaas($conta['asaas_customer_id']);
        $respostaApi = $resultado;
        
        if ($resultado['success']) {
            // Atualiza status na base de dados local
            $statusDocumentos = 'enviado_asaas';
            
            $sql = "UPDATE contas SET status_documentos = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $statusDocumentos, $contaId);
            $stmt->execute();
            
            $mensagem = "Documentos de identificação confirmados com sucesso. Status atualizado para: " . $statusDocumentos;
            
            // Recarregar informações da conta após atualização
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
            if (isset($resultado['curl_error']) && !empty($resultado['curl_error'])) {
                $erro .= " (Erro CURL: " . $resultado['curl_error'] . ")";
            }
        }
    }
}

// Processamento do formulário para verificar documentos
if (isset($_POST['verificar_documentos'])) {
    $resultadoVerificacao = verificarDocumentosAsaas();
    if ($resultadoVerificacao['success']) {
        $respostaApi = $resultadoVerificacao['response'];
        $mensagem = "Documentos verificados com sucesso.";
    } else {
        $erro = $resultadoVerificacao['message'];
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
        body {
            background-color: #0d1117;
            color: #e0e0e0;
            font-family: 'Segoe UI', sans-serif;
        }
        .navbar {
            background-color: #0f3d25;
        }
        .navbar-brand {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.5rem;
            color: #00ff88 !important;
        }
        .container {
            margin-top: 90px;
        }
        .card {
            margin-bottom: 20px;
        }
        pre {
            background-color: #1c2128;
            color: #e0e0e0;
            padding: 15px;
            border-radius: 5px;
            white-space: pre-wrap;
            font-size: 0.85rem;
        }
        footer {
            text-align: center;
            margin-top: 40px;
            padding: 10px;
            color: #888;
        }
    </style>
</head>
<body>
<nav class="navbar fixed-top navbar-expand-lg navbar-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">Banco Matrix</a>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="admin.php">Voltar ao Painel</a>
                </li>
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
                    <div class="row">
                        <div class="col-md-6">
                            <form method="post" class="mb-4">
                                <button type="submit" name="verificar_documentos" class="btn btn-info w-100">
                                    1. Verificar Documentos Disponíveis
                                </button>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <form method="post" class="mb-4">
                                <button type="submit" name="confirmar_documentos" class="btn btn-success w-100" <?php echo (!$respostaApi ? 'disabled' : ''); ?>>
                                    2. Confirmar Documentos de Identificação (IDENTIFICATION)
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>Como funciona:</strong><br>
                        1. Primeiro clique em "Verificar Documentos Disponíveis" para listar os documentos na plataforma ASAAS.<br>
                        2. Depois clique em "Confirmar Documentos de Identificação" para enviar a confirmação dos documentos IDENTIFICATION para a ASAAS.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($respostaApi): ?>
            <div class="card bg-dark text-white border-info">
                <div class="card-header bg-info text-white">
                    Resposta da API ASAAS
                </div>
                <div class="card-body">
                    <pre><?php echo json_encode($respostaApi, JSON_PRETTY_PRINT); ?></pre>
                </div>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="card bg-dark text-white border-warning">
            <div class="card-header bg-warning text-dark">
                Selecione uma Conta
            </div>
            <div class="card-body">
                <?php
                // Consulta todas as contas
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
                    <div class="alert alert-info">
                        Nenhuma conta cadastrada encontrada.
                    </div>
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