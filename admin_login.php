<?php
session_start();

// Verificar se já está logado
if (isset($_SESSION['admin_logado']) && $_SESSION['admin_logado'] === true) {
    header('Location: admin.php');
    exit;
}

// Configurações de Conexão MySQL
$servername = "localhost";
$username = "";
$password = "";
$database = "";

// Processar o login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_usuario = $_POST['usuario'];
    $admin_senha = $_POST['senha'];
    
    // Conectar ao banco de dados
    $conn = new mysqli($servername, $username, $password, $database);
    if ($conn->connect_error) {
        $erro = "Falha na conexão com o banco de dados.";
    } else {
        // Verificar no banco de dados (você precisará criar uma tabela de administradores)
        $stmt = $conn->prepare("SELECT id, senha FROM administradores WHERE usuario = ?");
        $stmt->bind_param("s", $admin_usuario);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $admin = $result->fetch_assoc();
            if (password_verify($admin_senha, $admin['senha'])) {
                // Login bem-sucedido
                $_SESSION['admin_logado'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_usuario'] = $admin_usuario;
                
                header('Location: admin.php');
                exit;
            } else {
                $erro = "Senha incorreta.";
            }
        } else {
            $erro = "Usuário não encontrado.";
        }
        
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><meta charset="utf-8">
    
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Administrativo - Banco Matrix</title>
    <style>
        body {
            background-color: #000;
            color: #00ff99;
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .login-container {
            background-color: #111;
            border: 2px solid #00ff99;
            padding: 30px;
            border-radius: 10px;
            width: 350px;
        }

        h1 {
            color: #00ff99;
            text-align: center;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
        }

        input {
            width: 100%;
            padding: 10px;
            box-sizing: border-box;
            background-color: #000;
            color: #00ff99;
            border: 1px solid #00ff99;
            border-radius: 5px;
        }

        button {
            width: 100%;
            padding: 12px;
            background-color: #00ff99;
            color: #000;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
        }

        button:hover {
            background-color: #00cc77;
        }

        .error-message {
            color: #ff3333;
            margin-top: 15px;
            text-align: center;
        }

        .logo {
            text-align: center;
            margin-bottom: 20px;
            font-size: 24px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">BANCO MATRIX</div>
        <h1>Acesso Administrativo</h1>
        
        <?php if (isset($erro)): ?>
            <div class="error-message"><?php echo $erro; ?></div>
        <?php endif; ?>
        
        <form method="post" action="">
            <div class="form-group">
                <label for="usuario">Usuário:</label>
                <input type="text" id="usuario" name="usuario" required>
            </div>
            
            <div class="form-group">
                <label for="senha">Senha:</label>
                <input type="password" id="senha" name="senha" required>
            </div>
            
            <button type="submit">Entrar</button>
        </form>
    </div>
</body>
</html>