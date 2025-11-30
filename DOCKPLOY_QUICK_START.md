# 🚀 Deploy Rápido no Dockploy

## Passos Rápidos

### 1. No Dockploy - Criar Nova Aplicação

1. Acesse seu painel Dockploy
2. Clique em **"New Application"** ou **"Nova Aplicação"**
3. Conecte seu repositório Git (GitHub/GitLab)

### 2. Configurações de Build

- **Dockerfile Path**: `bolao/Dockerfile` (ou `Dockerfile` se estiver na raiz)
- **Build Context**: `.`
- **Port**: `80`

### 3. Variáveis de Ambiente (IMPORTANTE!)

Adicione estas variáveis no Dockploy:

```
CHECKIFY_KEY=ck_71fe9d84577477f9667ce5b3366a2e1a8e27a324b4faf06b1ef090dc2ee72447
TECHBYNET_API_KEY=98290fac-b0ff-4472-8c4c-e1c6f835e973
MASTERPAG_PUBLIC_KEY=pk_BI5zNanGDbFUNxh-AfG1TzR4HSzsiXIX7MpnopGBUKqs9bhq
MASTERPAG_SECRET_KEY=sk_p_Cy50xN4w_XtIofd3kcPqVw7NmEmlWD8W6EgKJ8wlROLe1g
MYTRUST_API_KEY=sk_01jmk1h8cw8vcqj508v1n5j06301jmk1h8cwp7573d3kszs3cpxt
```

### 4. Domínio

- **Domain**: `mega.davirada2026.com`
- Configure DNS apontando para o IP do Dockploy

### 5. Deploy!

Clique em **"Deploy"** e aguarde o build completar.

## ✅ Verificação

Após o deploy, teste:

1. **Site**: `https://mega.davirada2026.com`
2. **API CPF**: `https://mega.davirada2026.com/cpf_api.php?cpf=12345678901`
3. **Webhook**: `https://mega.davirada2026.com/webhook.php`

## 📝 Notas

- O Dockerfile cria automaticamente o `keys.env` a partir das variáveis de ambiente
- O arquivo `orders.json` é criado automaticamente
- HTTPS é configurado automaticamente pelo Dockploy

## 🐛 Problemas?

Verifique os logs no Dockploy e consulte `DEPLOY.md` para mais detalhes.

