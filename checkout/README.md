# Página de Checkout - Pagamento PIX

## ⚠️ IMPORTANTE: Requer Servidor Web com PHP

Esta página **NÃO funciona** quando aberta diretamente via `file://` no navegador. É necessário um servidor web com PHP instalado.

## Como executar localmente:

### Opção 1: PHP Built-in Server (Recomendado)
Se você tem PHP instalado, execute no terminal na pasta raiz do projeto:

```bash
cd C:\Users\usar\Desktop\bol
php -S localhost:8000
```

Depois acesse: `http://localhost:8000/checkout/`

### Opção 2: XAMPP
1. Baixe e instale o XAMPP: https://www.apachefriends.org/
2. Copie a pasta `bol` para `C:\xampp\htdocs\`
3. Inicie o Apache no XAMPP Control Panel
4. Acesse: `http://localhost/bol/checkout/`

### Opção 3: WAMP
1. Baixe e instale o WAMP: https://www.wampserver.com/
2. Copie a pasta `bol` para `C:\wamp64\www\`
3. Inicie o WAMP
4. Acesse: `http://localhost/bol/checkout/`

## Arquivos necessários:

- `index.html` - Página principal de checkout
- `gerar_pix.php` - Script que gera o PIX via API
- `check-payment.php` - Script que verifica status do pagamento

## Configuração da API:

A API está configurada em `gerar_pix.php`:
- Endpoint: `https://api-gateway.techbynet.com/api/user/transactions`
- API Key: `98290fac-b0ff-4472-8c4c-e1c6f835e973`

## Fluxo:

1. Usuário preenche formulário em `c/4/index.html`
2. Clica em "Continuar para Pagamento"
3. Redireciona para `checkout/index.html` com dados na URL
4. JavaScript chama `gerar_pix.php` que faz requisição à API
5. QR Code e código PIX são exibidos
6. Verificação automática a cada 5 segundos
7. Quando pago, redireciona para `../upsell1/`

