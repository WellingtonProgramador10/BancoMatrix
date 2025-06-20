<?php
session_start();

// Exibir erros (para debug na hospedagem)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verifica se conexao.php existe e conecta corretamente
if (!file_exists('conexao.php')) {
    die("Arquivo de conexão não encontrado.");
}

require 'conexao.php';

// Cabeçalhos de segurança
header("X-Robots-Tag: noindex, nofollow", true);
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Proteção contra HTTrack
if (isset($_SERVER['HTTP_USER_AGENT']) && stripos($_SERVER['HTTP_USER_AGENT'], 'HTTrack') !== false) {
    die("Acesso negado.");
}

// Proteção: token CSRF
if (!isset($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        die("Erro ao gerar token de segurança: " . $e->getMessage());
    }
}

// Proteção: checagem de origem + login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token inválido. Atualize a página e tente novamente.");
    }

    if (!isset($_SERVER['HTTP_REFERER']) || stripos($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST']) === false) {
        die("Requisição inválida.");
    }

    $email = $_POST['login_email'] ?? '';
    $senha = $_POST['senha'] ?? '';

    if (empty($email) || empty($senha)) {
        $erro = "Preencha todos os campos.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM contas WHERE login_email = ?");
        if (!$stmt) {
            die("Erro na preparação da consulta: " . $conn->error);
        }

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows > 0) {
            $usuario = $resultado->fetch_assoc();

            if (password_verify($senha, $usuario['senha'])) {
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_nome'] = $usuario['nome'];
                header("Location: cliente.php");
                exit;
            } else {
                $erro = "Senha incorreta!";
            }
        } else {
            $erro = "Usuário não encontrado!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>Login - Banco Matrix</title>
    <style>
        body {
            background-color: #000;
            color: #00ff00;
            font-family: 'Courier New', Courier, monospace;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }

        h2 {
            margin-bottom: 20px;
            text-shadow: 0 0 5px #00ff00;
        }

        form {
            background-color: rgba(0, 0, 0, 0.8);
            border: 2px solid #00ff00;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px #00ff00;
            width: 300px;
        }

        label {
            display: block;
            margin-top: 10px;
            margin-bottom: 5px;
        }

        input {
            width: 100%;
            padding: 8px;
            background-color: #111;
            border: 1px solid #00ff00;
            color: #00ff00;
        }

        button {
            margin-top: 15px;
            width: 100%;
            padding: 10px;
            background-color: #00ff00;
            color: #000;
            font-weight: bold;
            border: none;
            cursor: pointer;
            transition: 0.3s;
        }

        button:hover {
            background-color: #00cc00;
        }

        a {
            color: #00ff00;
            text-decoration: none;
            display: block;
            margin-top: 15px;
            text-align: center;
        }

        .erro {
            color: red;
            margin-bottom: 15px;
        }
    </style>

    <script>
        document.addEventListener('contextmenu', e => e.preventDefault());
        document.addEventListener('copy', e => e.preventDefault());
        document.addEventListener('cut', e => e.preventDefault());
        document.addEventListener('paste', e => e.preventDefault());
        document.addEventListener('selectstart', e => e.preventDefault());
        document.addEventListener('dragstart', e => e.preventDefault());

        document.onkeydown = function(e) {
            if (
                e.keyCode === 123 ||
                (e.ctrlKey && e.shiftKey && [73, 67, 74].includes(e.keyCode)) ||
                (e.ctrlKey && [85, 83, 65, 67].includes(e.keyCode))
            ) {
                return false;
            }
        };
    </script>
</head>
<body>
    <h2>ACESSO AO BANCO MATRIX</h2>

    <?php if (isset($erro)) echo "<div class='erro'>$erro</div>"; ?>

    <form method="POST">
        <label>Email:</label>
        <input type="email" name="login_email" required>

        <label>Senha:</label>
        <input type="password" name="senha" required>

        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

        <button type="submit">ENTRAR</button>
        <a href="formulario_conta.html">Criar nova conta</a>
    </form>
</body>
</html>