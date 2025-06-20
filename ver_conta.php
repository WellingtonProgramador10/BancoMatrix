<?php
include 'saldo_api.php';
$saldoAtual = getSaldoAtual();

$servername = "localhost";
$username = "";
$password = "";
$database = "";
$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Erro ao conectar: " . $conn->connect_error);
}

$identificador = $_POST['identificador'];

$stmt = $conn->prepare("SELECT * FROM contas WHERE cpf_cnpj = ? OR email = ?");
$stmt->bind_param("ss", $identificador, $identificador);
$stmt->execute();
$result = $stmt->get_result();
$conta = $result->fetch_assoc();

if ($conta):
?>

<!DOCTYPE html>
<html lang="pt-br">
<head><meta charset="utf-8">
  
  <title>Minha Conta - Banco Matrix</title>
  <style>
    body {
      background-color: #000;
      color: #00ff99;
      font-family: Arial, sans-serif;
      padding: 30px;
    }

    .container {
      max-width: 600px;
      margin: auto;
      background-color: #111;
      padding: 20px;
      border-radius: 10px;
      border: 1px solid #00ff99;
    }

    h2 {
      color: #00ff99;
    }

    p {
      margin: 10px 0;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>Olá, <?= $conta['nome'] ?>!</h2>
    <p><strong>Agência:</strong> <?= $conta['agencia'] ?? '0001' ?></p>
    <p><strong>Conta:</strong> <?= $conta['numero_conta'] ?? '000000' ?>-<?= $conta['digito_conta'] ?? '0' ?></p>
    <p><strong>CPF/CNPJ:</strong> <?= $conta['cpf_cnpj'] ?></p>
    <p><strong>Email:</strong> <?= $conta['email'] ?></p>
    <p><strong>Tipo:</strong> <?= $conta['tipo_pessoa'] ?></p>
    <p><strong>Celular:</strong> <?= $conta['celular'] ?></p>
    <p><strong>Renda Mensal:</strong> R$ <?= number_format($conta['renda'], 2, ',', '.') ?></p>
    <p><strong>Saldo Atual:</strong> R$ <?= number_format($saldoAtual, 2, ',', '.') ?></p>
  </div>
</body>
</html>

<?php
else:
  echo "<p style='color:red; text-align:center;'>Conta não encontrada.</p>";
endif;

$conn->close();
?>