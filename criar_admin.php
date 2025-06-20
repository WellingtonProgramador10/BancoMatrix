<?php
// Este script deve ser executado apenas uma vez para criar o primeiro administrador
// Após o uso, recomendo remover este arquivo do servidor por motivos de segurança

// --- Configurações de Conexão MySQL ---
$servername = "localhost";
$username = "";
$password = "";
$database = "";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// --- Verificar se a tabela administradores existe ---
$sql = "SHOW TABLES LIKE 'administradores'";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    // Criar tabela de administradores
    $sql = "CREATE TABLE administradores (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        usuario VARCHAR(50) NOT NULL UNIQUE,
        senha VARCHAR(255) NOT NULL,
        nome VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($sql)) {
        die("Erro ao criar tabela: " . $conn->error);
    }
    echo "Tabela de administradores criada com sucesso.<br>";
}

// --- Definir dados do administrador ---
$admin_usuario = "admin"; // Altere para o nome de usuário desejado
$admin_senha = "admin123"; // Altere para uma senha forte
$admin_nome = "Administrador";
$admin_email = "admin@bancomatrix.com";

// --- Criptografar a senha ---
$senha_hash = password_hash($admin_senha, PASSWORD_DEFAULT);

// --- Inserir ou atualizar o administrador ---
$stmt = $conn->prepare("INSERT INTO administradores (usuario, senha, nome, email) 
                       VALUES (?, ?, ?, ?) 
                       ON DUPLICATE KEY UPDATE 
                       senha = ?, nome = ?, email = ?");

$stmt->bind_param("sssssss", 
    $admin_usuario, $senha_hash, $admin_nome, $admin_email,
    $senha_hash, $admin_nome, $admin_email
);

if ($stmt->execute()) {
    echo "Administrador criado/atualizado com sucesso!<br>";
    echo "Usuário: " . $admin_usuario . "<br>";
    echo "Senha: " . $admin_senha . "<br>";
    echo "<p style='color:red;'>IMPORTANTE: Altere a senha após o primeiro login e REMOVA este arquivo do servidor!</p>";
} else {
    echo "Erro ao criar administrador: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>