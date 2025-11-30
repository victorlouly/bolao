Instruções de deploy (PHP)
--------------------------

1. Faça upload de todos os arquivos para a raiz do seu site (ou pasta de sua escolha).

2. Crie um arquivo chamado 'keys.env' na mesma pasta e cole:
   MASTERPAG_PUBLIC_KEY=your_public_key_here
   MASTERPAG_SECRET_KEY=your_secret_key_here
   MYTRUST_API_KEY=your_mytrust_key_here

3. Verifique se o servidor permite requisições cURL (ext-curl).

4. Aponte seu domínio para essa pasta e acesse a página.

Arquivos da aplicação:
- index.html - Página principal
- api.php - API de pagamento MasterPag
- cpf_api.php - API de consulta de CPF (MyTrust)
- js/app.js - JavaScript frontend
- keys.env - Chaves de API (NÃO compartilhe!)

Segurança:
- Não compartilhe suas chaves.
- Em produção use HTTPS.
- Adicione keys.env ao .gitignore se usar Git.
- NUNCA faça commit do arquivo keys.env em repositórios públicos.

Estrutura de pastas necessária:
seu-projeto/
├── index.html
├── api.php
├── cpf_api.php
├── keys.env
├── README.txt
├── css/
│   └── style.css (seu arquivo CSS existente)
├── js/
│   └── app.js
└── img/
    └── (suas imagens)