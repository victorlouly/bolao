# Guia de Deploy no Dockploy

## Pré-requisitos

1. Conta no Dockploy configurada
2. Repositório Git com o código (GitHub, GitLab, etc.)
3. Domínio configurado (mega.davirada2026.com)

## Passo a Passo

### 1. Preparar o Repositório

Certifique-se de que o arquivo `keys.env` NÃO está no repositório Git (adicione ao .gitignore).

```bash
echo "keys.env" >> .gitignore
echo "orders.json" >> .gitignore
```

### 2. Configurar no Dockploy

1. Acesse o painel do Dockploy
2. Clique em "New Application" ou "Nova Aplicação"
3. Conecte seu repositório Git
4. Configure as seguintes opções:

#### Build Settings:
- **Dockerfile Path**: `bolao/Dockerfile` (ou apenas `Dockerfile` se estiver na raiz)
- **Build Context**: `.` (raiz do repositório)

#### Environment Variables (Variáveis de Ambiente):
Configure as seguintes variáveis no Dockploy (não use o arquivo keys.env no repositório):

```
CHECKIFY_KEY=ck_71fe9d84577477f9667ce5b3366a2e1a8e27a324b4faf06b1ef090dc2ee72447
TECHBYNET_API_KEY=98290fac-b0ff-4472-8c4c-e1c6f835e973
MASTERPAG_PUBLIC_KEY=pk_BI5zNanGDbFUNxh-AfG1TzR4HSzsiXIX7MpnopGBUKqs9bhq
MASTERPAG_SECRET_KEY=sk_p_Cy50xN4w_XtIofd3kcPqVw7NmEmlWD8W6EgKJ8wlROLe1g
MYTRUST_API_KEY=sk_01jmk1h8cw8vcqj508v1n5j06301jmk1h8cwp7573d3kszs3cpxt
```

#### Port:
- **Port**: `80`

#### Domain:
- **Domain**: `mega.davirada2026.com`

### 3. Modificar o Código para Usar Variáveis de Ambiente

Como o Dockploy usa variáveis de ambiente, você pode modificar os arquivos PHP para ler das variáveis de ambiente diretamente, ou criar o arquivo `keys.env` durante o build.

**Opção A: Criar keys.env durante o build (Recomendado)**

Crie um script que gera o `keys.env` a partir das variáveis de ambiente. Adicione ao Dockerfile:

```dockerfile
# No Dockerfile, adicione antes do CMD:
RUN echo '#!/bin/bash\n\
echo "# MasterPag API Keys" > /var/www/html/keys.env\n\
echo "MASTERPAG_PUBLIC_KEY=${MASTERPAG_PUBLIC_KEY}" >> /var/www/html/keys.env\n\
echo "MASTERPAG_SECRET_KEY=${MASTERPAG_SECRET_KEY}" >> /var/www/html/keys.env\n\
echo "# MyTrust API Key" >> /var/www/html/keys.env\n\
echo "MYTRUST_API_KEY=${MYTRUST_API_KEY}" >> /var/www/html/keys.env\n\
echo "# Checkify API Key" >> /var/www/html/keys.env\n\
echo "CHECKIFY_KEY=${CHECKIFY_KEY}" >> /var/www/html/keys.env\n\
echo "# TechByNet API Key" >> /var/www/html/keys.env\n\
echo "TECHBYNET_API_KEY=${TECHBYNET_API_KEY}" >> /var/www/html/keys.env\n\
apache2-foreground' > /entrypoint.sh && chmod +x /entrypoint.sh
CMD ["/entrypoint.sh"]
```

**Opção B: Modificar PHP para ler variáveis de ambiente diretamente (Mais simples)**

Modifique os arquivos `api.php` e `cpf_api.php` para ler das variáveis de ambiente primeiro, depois do arquivo.

### 4. Deploy

1. No Dockploy, clique em "Deploy" ou "Deploy Now"
2. Aguarde o build e deploy completarem
3. Verifique os logs se houver erros

### 5. Verificar Funcionamento

1. Acesse: `https://mega.davirada2026.com`
2. Teste a consulta de CPF
3. Teste a geração de PIX
4. Verifique o webhook: `https://mega.davirada2026.com/webhook.php`

## Troubleshooting

### Erro: "Arquivo keys.env não encontrado"
- Certifique-se de que as variáveis de ambiente estão configuradas no Dockploy
- Verifique se o script de criação do keys.env está funcionando

### Erro: "cURL não disponível"
- O Dockerfile já instala cURL, mas verifique os logs do build

### Erro: "Permissão negada em orders.json"
- O Dockerfile já configura permissões, mas pode ser necessário ajustar

### Webhook não funciona
- Verifique se a URL do webhook está correta: `https://mega.davirada2026.com/webhook.php`
- Verifique os logs do container no Dockploy

## Estrutura de Arquivos no Repositório

```
bolao/
├── Dockerfile
├── .dockerignore
├── docker-compose.yml
├── .htaccess
├── index.html
├── api.php
├── cpf_api.php
├── webhook.php
├── css/
├── js/
├── img/
└── font/
```

## Notas Importantes

- ⚠️ **NUNCA** faça commit do arquivo `keys.env` no Git
- ✅ Use variáveis de ambiente no Dockploy para as chaves
- ✅ Configure HTTPS no Dockploy (geralmente automático com Let's Encrypt)
- ✅ Monitore os logs regularmente

