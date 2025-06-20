
# 💰 Banco Matrix

🚀 **Sistema de Banco Digital — Completo, com área de clientes, painel de gestão, geração de PIX, transferências, consulta de saldo e muito mais.**

---

## 🧠 Sobre o Projeto

O Banco Matrix é um sistema que simula a operação de um banco digital. Inclui:

- ✅ Abertura de conta
- ✅ Área do cliente (com saldo, dados bancários, transações)
- ✅ Área administrativa para controle de usuários e dados
- ✅ Geração de cobranças via PIX (API ASAAS — ambiente sandbox)
- ✅ Transferências entre contas
- ✅ Consulta de saldo
- ✅ Gestão de dados cadastrais

---

## 🚀 Tecnologias Utilizadas

- ✔️ PHP (Backend + Frontend juntos por página)
- ✔️ MySQL (Banco de Dados)
- ✔️ HTML, CSS, JavaScript (Frontend)
- ✔️ API ASAAS (Integração financeira — sandbox)
- ✔️ Git & GitHub (Controle de versão)

---

## 🏗️ Arquitetura

> 🔥 **Modelo Monolítico:**  
Backend (PHP) e Frontend (HTML, CSS, JS) estão juntos em cada página.  

Este é um modelo clássico e muito utilizado em sistemas comerciais, portais e dashboards internos.  

✔️ A versão API separada poderá ser desenvolvida futuramente.

---

## 📄 Banco de Dados

O script do banco está localizado na pasta:  
**`/database/banco_matrix.sql`**  

⚙️ **Inclui:**  
- Tabela de clientes (`contas`)  
- Tabelas de transações (se houver)  
- Campos para gestão de PIX, dados bancários, wallet, saldo, status, etc.

⚠️ **Este banco está pronto para ser importado no MySQL. Contém apenas a estrutura, sem dados sensíveis.**

---

## ⚙️ Como Rodar o Projeto Localmente

### 🔧 Pré-requisitos:

- PHP 7.x ou superior instalado
- MySQL rodando
- Um servidor local (XAMPP, WAMP, Laragon, etc.)
- Navegador

### 🚀 Passos:

1. Clone o repositório:
```bash
git clone https://github.com/SeuUsuario/BancoMatrix.git
```

2. Coloque a pasta dentro do diretório do seu servidor local (ex.: `htdocs` do XAMPP).

3. No MySQL:
- Crie um banco de dados (ex.: `banco_matrix`)
- Importe o arquivo:
```
/database/banco_matrix.sql
```

4. Configure o arquivo de conexão (`conexao.php` ou outro que você usar) com seus dados locais:

```php
<?php
$host = "localhost";
$user = "root";
$pass = "";
$db = "banco_matrix";
?>
```

5. Abra no navegador:
```
http://localhost/BancoMatrix
```

---

## 📫 Contato

- 👨‍💻 **Desenvolvedor:** Wellington Programador  
- 🌐 **Portfólio:** https://wellingtonprogramador10.github.io/Portfolio/ 
- 💼 **LinkedIn:**   
- 📧 **Email:** wellingtonbisposantoss@gmail.com  
- 📱 **WhatsApp:** (11) 950964105  
-   ** www.bancomatrix.store 
---

## 📝 Licença

Este projeto está sob a licença MIT — fique à vontade para usar, estudar, melhorar e modificar, dando os devidos créditos.

---

## ⭐ Se gostou, deixe uma estrela no repositório! Isso me ajuda muito!
