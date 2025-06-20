fetch('visualizar_conta.php')
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      const tbody = document.querySelector('#tabelaContas tbody');
      data.contas.forEach(conta => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${conta.nome}</td>
          <td>${conta.cpf_cnpj}</td>
          <td>${conta.email}</td>
          <td>${conta.agencia}</td>
          <td>${conta.conta_numero}-${conta.digito_conta}</td>
          <td>${conta.tipo_pessoa}</td>
          <td>${formatarData(conta.data_cadastro)}</td>
        `;
        tbody.appendChild(tr);
      });
    } else {
      console.error('Erro ao carregar dados:', data.message);
    }
  })
  .catch(error => console.error('Erro na requisição:', error));
