<?php
// Configurações do Banco de Dados
d$servername = "localhost";
$username = "";
$password = "";
$database = "";

// Configurações da API ASAAS
define('ASAAS_API_KEY', 'seu_token_aqui');
define('ASAAS_API_URL', 'https://sandbox.asaas.com/api/v3');

// Configurações Gerais
define('SITE_URL', 'http://localhost/BANCO MATRI/');
define('UPLOAD_DIR', __DIR__ . '/uploads');

// Conexão com o Banco de Dados
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8")
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}

// Funções Helpers
function sanitize($data) {
    return htmlspecialchars(trim($data));
}

function redirect($url) {
    header("Location: " . SITE_URL . $url);
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Função para integração com ASAAS
function asaasRequest($endpoint, $method = 'GET', $data = null) {
    $curl = curl_init();
    
    $url = ASAAS_API_URL . $endpoint;
    
    $headers = [
        'Content-Type: application/json',
        'access_token: ' . ASAAS_API_KEY
    ];

    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers
    ]);

    if ($data && ($method == 'POST' || $method == 'PUT')) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        throw new Exception("Erro na requisição ASAAS: " . $err);
    }

    return json_decode($response, true);
}