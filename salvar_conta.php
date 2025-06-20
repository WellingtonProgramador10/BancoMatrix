<?php
// Conexão com o banco
$conn = new mysqli("localhost", "root", "", "matrix");
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Recebe os dados JSON
$data = json_decode(file_get_contents("php://input"), true);

// Validação básica
if (!$data || !isset($data['id']) || empty($data['id'])) {
    echo json_encode(["success" => false, "message" => "ID da conta Asaas não recebido"]);
    exit;
}

// Garante que todas as chaves existem
$data = array_merge([
    'companyType' => null,
    'city' => null,
    'state' => null,
    'country' => null,
    'site' => null,
    'walletId' => null,
    'apiKey' => null,
    'accountNumber' => ['agency' => null, 'account' => null, 'accountDigit' => null],
    'incomeValue' => null,
], $data);

// Prepara a query
$stmt = $conn->prepare("
    INSERT INTO contas (
        id, nome, email, login_email, cpf_cnpj, tipo_pessoa, tipo_empresa, nascimento,
        telefone, celular, endereco, numero_endereco, complemento, bairro, cep,
        cidade_id, estado, pais, site, wallet_id, api_key, agencia, conta_numero,
        digito_conta, renda
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    echo json_encode(["success" => false, "message" => "Erro na preparação: " . $conn->error]);
    exit;
}

// Faz o bind dos dados
$stmt->bind_param(
    "ssssssssssssssssssssssssd",
    $data['id'],
    $data['name'],
    $data['email'],
    $data['loginEmail'],
    $data['cpfCnpj'],
    $data['personType'],
    $data['companyType'],
    $data['birthDate'],
    $data['phone'],
    $data['mobilePhone'],
    $data['address'],
    $data['addressNumber'],
    $data['complement'],
    $data['province'],
    $data['postalCode'],
    $data['city'],
    $data['state'],
    $data['country'],
    $data['site'],
    $data['walletId'],
    $data['apiKey'],
    $data['accountNumber']['agency'],
    $data['accountNumber']['account'],
    $data['accountNumber']['accountDigit'],
    $data['incomeValue']
);

// Executa
if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Conta inserida com sucesso!"]);
} else {
    echo json_encode(["success" => false, "message" => "Erro ao inserir: " . $stmt->error]);
}

$stmt->close();
$conn->close();
