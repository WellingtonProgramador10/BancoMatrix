<?php
// --- Verificação de login de administrador ---
session_start();
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    echo json_encode(["success" => false, "message" => "Acesso não autorizado"]);
    exit;
}

// --- Configurações de Conexão MySQL ---
$servername = "localhost";
$username = "";
$password = "";
$database = "";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Falha na conexão com o banco de dados: " . $conn->connect_error]);
    exit;
}

// --- Consulta todas as contas ---
$sql = "SELECT 
            id, nome, email, login_email, cpf_cnpj, tipo_pessoa, agencia, 
            conta_numero, digito_conta, data_cadastro 
        FROM contas 
        ORDER BY data_cadastro DESC";

$result = $conn->query($sql);

if ($result === false) {
    echo json_encode(["success" => false, "message" => "Erro na consulta: " . $conn->error]);
    exit;
}

$contas = [];
while ($row = $result->fetch_assoc()) {
    $contas[] = $row;
}

echo json_encode([
    "success" => true, 
    "contas" => $contas,
    "total" => count($contas)
]);

$conn->close();
?>