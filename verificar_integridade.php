<?php
    header("Content-Type: application/json");
    
    // Iniciar sessão
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Verificar se é uma requisição POST
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode(["valido" => false, "erro" => "Método não permitido"]);
        exit;
    }
    
    // Obter dados da requisição
    $data = json_decode(file_get_contents("php://input"), true);
    
    // Verificar token da página
    if (!isset($data["token"]) || !isset($_SESSION["token_pagina"]) || $data["token"] !== $_SESSION["token_pagina"]) {
        echo json_encode(["valido" => false, "erro" => "Token inválido"]);
        exit;
    }
    
    // Verificar URL permitida
    $url_base = (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === "on" ? "https" : "http") . "://" . $_SERVER["HTTP_HOST"];
    $urls_permitidas = [
        $url_base . "/cliente.php",
        $url_base . "/pix.php",
        // Adicionar outras URLs permitidas aqui
    ];
    
    if (!isset($data["url"]) || !in_array(parse_url($data["url"], PHP_URL_SCHEME) . "://" . parse_url($data["url"], PHP_URL_HOST) . parse_url($data["url"], PHP_URL_PATH), $urls_permitidas)) {
        echo json_encode(["valido" => false, "erro" => "URL não permitida"]);
        exit;
    }
    
    // Tudo OK
    echo json_encode(["valido" => true]);
    ?>