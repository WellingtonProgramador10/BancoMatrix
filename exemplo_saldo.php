<?php
// Incluir o arquivo de consulta de saldo
require_once 'consulta_saldo_dinamico.php';

// Exemplo de como verificar e exibir o saldo
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta de Saldo</title>
    <style>
        .saldo-container {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        .saldo-valor {
            font-size: 24px;
            font-weight: bold;
            color: #28a745;
            margin: 10px 0;
        }
        .saldo-erro {
            color: #dc3545;
            font-weight: bold;
        }
        .btn-atualizar {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
        }
        .btn-atualizar:hover {
            background: #0056b3;
        }
        .loading {
            display: none;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="saldo-container">
        <h3>Meu Saldo</h3>
        
        <?php if (verificarLogin()): ?>
            <div id="saldo-display">
                <div class="saldo-valor">
                    <?php echo exibirSaldoFormatado(); ?>
                </div>
            </div>
            
            <button class="btn-atualizar" onclick="atualizarSaldo()">
                Atualizar Saldo
            </button>
            
            <div class="loading" id="loading">
                Consultando saldo...
            </div>
            
        <?php else: ?>
            <div class="saldo-erro">
                Você precisa estar logado para ver o saldo.
            </div>
        <?php endif; ?>
    </div>

    <script>
        function atualizarSaldo() {
            const loadingDiv = document.getElementById('loading');
            const saldoDiv = document.getElementById('saldo-display');
            const btnAtualizar = document.querySelector('.btn-atualizar');
            
            // Mostrar loading
            loadingDiv.style.display = 'block';
            btnAtualizar.disabled = true;
            btnAtualizar.textContent = 'Atualizando...';
            
            // Fazer requisição AJAX
            fetch('consulta_saldo_dinamico.php?acao=obter_saldo')
                .then(response => response.json())
                .then(data => {
                    if (data.erro) {
                        saldoDiv.innerHTML = '<div class="saldo-erro">Erro: ' + data.mensagem + '</div>';
                    } else {
                        const saldoFormatado = 'R$ ' + data.saldo.toLocaleString('pt-BR', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                        saldoDiv.innerHTML = '<div class="saldo-valor">' + saldoFormatado + '</div>';
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    saldoDiv.innerHTML = '<div class="saldo-erro">Erro ao consultar saldo</div>';
                })
                .finally(() => {
                    // Esconder loading
                    loadingDiv.style.display = 'none';
                    btnAtualizar.disabled = false;
                    btnAtualizar.textContent = 'Atualizar Saldo';
                });
        }
        
        // Atualizar saldo automaticamente a cada 30 segundos
        setInterval(atualizarSaldo, 30000);
    </script>
</body>
</html>