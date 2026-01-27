# Instruções para Publicar no GitHub

## 1. Criar Repositório no GitHub

1. Acesse https://github.com
2. Clique em "New repository" (ou vá em https://github.com/new)
3. Preencha os dados:
   - **Repository name**: `woocommerce-whatsapp-notifications`
   - **Description**: `Plugin WordPress/WooCommerce para envio automático de notificações via WhatsApp`
   - **Visibility**: Escolha Public ou Private
   - **NÃO marque** "Initialize this repository with a README" (já temos um)
   - **NÃO adicione** .gitignore ou license (já temos)
4. Clique em "Create repository"

## 2. Conectar e Fazer Push

Após criar o repositório, execute os seguintes comandos no terminal:

```bash
cd /home/cloudcaos-sinergia/htdocs/sinergia.cloudcaos.com.br/wp-content/plugins/woocommerce-whatsapp-notifications

# Adicionar o repositório remoto (substitua SEU_USUARIO pelo seu usuário do GitHub)
git remote add origin https://github.com/SEU_USUARIO/woocommerce-whatsapp-notifications.git

# Renomear branch para main (opcional, mas recomendado)
git branch -M main

# Fazer push do código
git push -u origin main
```

## 3. Configurar Autenticação (se necessário)

Se o GitHub pedir autenticação, você pode:

### Opção A: Personal Access Token
1. Vá em GitHub Settings > Developer settings > Personal access tokens > Tokens (classic)
2. Gere um novo token com permissão `repo`
3. Use o token como senha quando fizer push

### Opção B: SSH Key
1. Configure uma chave SSH no GitHub
2. Use a URL SSH: `git@github.com:SEU_USUARIO/woocommerce-whatsapp-notifications.git`

## 4. Verificar

Após o push, acesse o repositório no GitHub e verifique se todos os arquivos foram enviados corretamente.

## Próximos Passos

- Adicionar tags de versão: `git tag v1.0.0 && git push origin v1.0.0`
- Configurar GitHub Actions para CI/CD (opcional)
- Adicionar Issues e Projects (opcional)
- Configurar GitHub Pages para documentação (opcional)

