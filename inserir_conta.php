<?php
// --- Proteção contra ferramentas de cópia offline (HTTrack, Wget, etc) ---
header("X-Robots-Tag: noindex, nofollow");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none';");
header("Set-Cookie: secure; HttpOnly");

// --- Bloqueio simples por User-Agent (HTTrack, Wget, etc) ---
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (preg_match('/HTTrack|wget|curl|scrapy|python|libwww|httpclient/i', $user_agent)) {
    http_response_code(403);
    exit("Acesso negado.");
}

// --- Configurações de Conexão MySQL ---
$servername = "localhost";
$username = "";
$password = "";
$database = "";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "Falha na conexão: " . $conn->connect_error]));
}

// --- Recebe dados do formulário ---
$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    echo json_encode(["success" => false, "message" => "Nenhum dado recebido"]);
    exit;
}

// --- Criptografar a senha ---
$senha_hash = password_hash($data["senha"], PASSWORD_DEFAULT);

// --- Configurações do Asaas ---
$token = 'TOKEN AQUI';

// --- Chamada para criar conta na API Asaas ---
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => "https://api-sandbox.asaas.com/v3/accounts",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "accept: application/json",
        "access_token: $token",
        "User-Agent: MatrixApp/1.0"
    ],
    CURLOPT_POSTFIELDS => json_encode([
        "name"         => $data["name"],
        "email"        => $data["email"],
        "loginEmail"   => $data["loginEmail"] ?? $data["email"],
        "cpfCnpj"      => $data["cpfCnpj"],
        "birthDate"    => $data["birthDate"] ?? null,
        "phone"        => $data["phone"] ?? null,
        "mobilePhone"  => $data["mobilePhone"] ?? null,
        "address"      => $data["address"],
        "addressNumber"=> $data["addressNumber"],
        "complement"   => $data["complement"] ?? null,
        "province"     => $data["province"],
        "postalCode"   => $data["postalCode"],
        "personType"   => $data["personType"],
        "companyType"  => $data["companyType"] ?? null,
        "incomeValue"  => floatval($data["incomeValue"]),
        "site"         => $data["site"] ?? null
    ])
]);

$response = curl_exec($curl);
if (curl_errno($curl)) {
    echo json_encode(["success" => false, "message" => "Erro cURL: " . curl_error($curl)]);
    exit;
}
curl_close($curl);

$apiResponse = json_decode($response, true);

if (!isset($apiResponse["id"])) {
    echo json_encode(["success" => false, "message" => "Erro Asaas: " . ($apiResponse["errors"][0]["description"] ?? $response)]);
    exit;
}

// --- Agora insere no banco de dados usando resposta da API ---
$stmt = $conn->prepare("
    INSERT INTO contas (
        id, nome, email, login_email, cpf_cnpj, tipo_pessoa, tipo_empresa, nascimento,
        telefone, celular, endereco, numero_endereco, complemento, bairro, cep,
        cidade_id, estado, pais, site, wallet_id, api_key, agencia, conta_numero,
        digito_conta, renda, senha
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "ssssssssssssssssssssssssss",
    $apiResponse["id"],
    $apiResponse["name"],
    $apiResponse["email"],
    $apiResponse["loginEmail"],
    $apiResponse["cpfCnpj"],
    $apiResponse["personType"],
    $apiResponse["companyType"],
    $apiResponse["birthDate"],
    $apiResponse["phone"],
    $apiResponse["mobilePhone"],
    $apiResponse["address"],
    $apiResponse["addressNumber"],
    $apiResponse["complement"],
    $apiResponse["province"],
    $apiResponse["postalCode"],
    $apiResponse["city"],
    $apiResponse["state"],
    $apiResponse["country"],
    $apiResponse["site"],
    $apiResponse["walletId"],
    $apiResponse["apiKey"],
    $apiResponse["accountNumber"]["agency"],
    $apiResponse["accountNumber"]["account"],
    $apiResponse["accountNumber"]["accountDigit"],
    $apiResponse["incomeValue"],
    $senha_hash
);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Conta criada no Asaas e salva no Matrix com sucesso!"]);
} else {
    echo json_encode(["success" => false, "message" => "Erro ao salvar no banco: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>