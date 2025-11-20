#!/bin/bash

# Script para publicar o plugin no GitHub
# Uso: ./publish-to-github.sh SEU_USUARIO

if [ -z "$1" ]; then
    echo "❌ Erro: Você precisa fornecer seu usuário do GitHub"
    echo ""
    echo "Uso: ./publish-to-github.sh SEU_USUARIO"
    echo ""
    echo "Exemplo: ./publish-to-github.sh jhoucarvalho"
    exit 1
fi

GITHUB_USER=$1
REPO_NAME="woocommerce-whatsapp-notifications"
REPO_URL="https://github.com/${GITHUB_USER}/${REPO_NAME}.git"

echo "🚀 Publicando plugin no GitHub..."
echo ""
echo "Repositório: ${REPO_URL}"
echo ""

# Verificar se já existe remote
if git remote get-url origin 2>/dev/null; then
    echo "⚠️  Já existe um repositório remoto configurado."
    read -p "Deseja substituir? (s/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Ss]$ ]]; then
        git remote remove origin
    else
        echo "❌ Operação cancelada."
        exit 1
    fi
fi

# Adicionar remote
echo "📡 Adicionando repositório remoto..."
git remote add origin "${REPO_URL}"

# Verificar se o repositório existe no GitHub
echo "🔍 Verificando se o repositório existe no GitHub..."
if ! git ls-remote --exit-code "${REPO_URL}" &>/dev/null; then
    echo ""
    echo "❌ O repositório não foi encontrado no GitHub!"
    echo ""
    echo "Por favor, crie o repositório primeiro:"
    echo "1. Acesse: https://github.com/new"
    echo "2. Nome do repositório: ${REPO_NAME}"
    echo "3. Descrição: Plugin WordPress/WooCommerce para envio automático de notificações via WhatsApp"
    echo "4. NÃO marque 'Initialize this repository with a README'"
    echo "5. Clique em 'Create repository'"
    echo ""
    read -p "Após criar o repositório, pressione Enter para continuar..."
    
    # Verificar novamente
    if ! git ls-remote --exit-code "${REPO_URL}" &>/dev/null; then
        echo "❌ O repositório ainda não foi encontrado. Verifique a URL e tente novamente."
        exit 1
    fi
fi

# Fazer push
echo ""
echo "📤 Enviando código para o GitHub..."
git push -u origin main

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Sucesso! O plugin foi publicado no GitHub!"
    echo ""
    echo "🔗 Acesse: ${REPO_URL}"
    echo ""
    echo "📝 Próximos passos:"
    echo "   - Adicionar tags: git tag v1.0.0 && git push origin v1.0.0"
    echo "   - Configurar descrição e tópicos no GitHub"
    echo "   - Adicionar badges ao README (opcional)"
else
    echo ""
    echo "❌ Erro ao fazer push. Verifique:"
    echo "   - Se você tem permissão para escrever no repositório"
    echo "   - Se suas credenciais estão configuradas corretamente"
    echo "   - Se o repositório existe no GitHub"
    exit 1
fi

