# WooCommerce WhatsApp Notifications

Plugin WordPress/WooCommerce para envio automático de notificações via WhatsApp quando pedidos mudam de status.

## Descrição

Este plugin integra o WooCommerce com uma API de WhatsApp para enviar notificações automáticas aos clientes quando ocorrem eventos relacionados aos pedidos, como mudanças de status.

## Funcionalidades

- ✅ Envio automático de notificações quando pedidos mudam de status
- ✅ Suporte para múltiplos status: processing, on-hold, completed, cancelled, refunded
- ✅ Notificações automáticas de códigos de rastreio (integração com wc-any-shipping-notify)
- ✅ Notificações automáticas de observações para o cliente
- ✅ Mensagens personalizáveis para cada status, rastreio e observações
- ✅ Formatação automática de telefone para padrão brasileiro (55 + DDD + número)
- ✅ Descoberta automática de endpoints da API
- ✅ Suporte para múltiplos tipos de autenticação (Bearer, Token, API Key)
- ✅ Painel de configurações intuitivo no WordPress admin
- ✅ Teste de conexão com a API
- ✅ Envio de mensagens de teste
- ✅ Sistema de logs para debug
- ✅ Tratamento robusto de erros
- ✅ Proteção contra notificações duplicadas
- ✅ Segurança: sanitização, validação, nonces, escape

## Requisitos

- WordPress 5.8+
- WooCommerce 5.0+ (testado até 10.4)
- PHP 7.4+
- Credenciais da API WhatsApp (URL e Token)

## Instalação

1. Faça upload da pasta `woocommerce-whatsapp-notifications` para `/wp-content/plugins/`
2. Ative o plugin através do menu 'Plugins' no WordPress
3. Acesse **WooCommerce > WhatsApp** para configurar

## Configuração

### 1. Configurações da API

1. Acesse **WooCommerce > WhatsApp** no admin do WordPress
2. Na aba "Configurações da API", insira:
   - **URL Base da API**: URL completa da API (ex: `https://apiwhatsapp.cdwchat.com.br/v1/api/external/...`)
   - **Token de Autenticação**: Token JWT da API
3. Clique em "Testar API" para verificar a conexão e descobrir o endpoint correto automaticamente
4. Clique em "Salvar Configurações"

### 2. Status de Pedidos

Na aba "Status de Pedidos", selecione quais status devem gerar notificações:
- Em processamento (processing)
- Aguardando pagamento (on-hold)
- Concluído (completed)
- Cancelado (cancelled)
- Reembolsado (refunded)

### 3. Personalizar Mensagens

Na aba "Mensagens", personalize as mensagens para cada status usando os seguintes placeholders:

**Placeholders disponíveis:**
- `{customer_name}` - Nome do cliente
- `{order_number}` - Número do pedido
- `{order_total}` - Valor total do pedido
- `{order_date}` - Data do pedido
- `{products_list}` - Lista de produtos
- `{status}` - Status do pedido em português
- `{shipping_method}` - Método de entrega (ex: "Correios - PAC", "Transportadora XYZ")
- `{shipping_total}` - Valor do frete/entrega (exibe "Grátis" se não houver custo)
- `{tracking_code}` - Código de rastreio (apenas para mensagens de rastreio)
- `{tracking_url}` - URL de rastreio (apenas para mensagens de rastreio)
- `{shipping_company}` - Nome da transportadora (apenas para mensagens de rastreio)
- `{note_content}` - Conteúdo da observação (apenas para mensagens de observação)

**Exemplo de mensagem:**
```
Olá *{customer_name}*! 👋

Seu pedido *#{order_number}* está sendo processado!

📦 *Produtos:*
{products_list}

💰 *Total:* {order_total}

📅 *Data:* {order_date}
```

**Formatação WhatsApp:**
- `*texto*` - Negrito
- `_texto_` - Itálico
- `~texto~` - Riscado

### 4. Testes

Na aba "Testes", você pode:
- Enviar mensagens de teste para verificar se a integração está funcionando
- Testar diferentes números de telefone
- Verificar se as mensagens estão sendo formatadas corretamente

## Formato de Telefone

O plugin aceita telefones nos seguintes formatos:
- `(44) 99999-9999`
- `44999999999`
- `5544999999999` (com código do país)

O plugin automaticamente formata para o padrão brasileiro: `55 + DDD + número`

## Logs

Os logs são salvos automaticamente pelo WooCommerce. Para visualizar:
1. Acesse **WooCommerce > Status > Logs**
2. Selecione o log `wc-whatsapp-notifications`
3. Visualize os eventos, erros e informações de debug

## Segurança

O plugin implementa as melhores práticas de segurança do WordPress:
- ✅ Sanitização de todos os inputs
- ✅ Validação de dados
- ✅ Nonces para formulários
- ✅ Escape de todos os outputs
- ✅ Verificação de permissões
- ✅ Proteção contra CSRF

## Estrutura do Plugin

```
woocommerce-whatsapp-notifications/
├── woocommerce-whatsapp-notifications.php  # Arquivo principal
├── includes/
│   ├── class-wc-whatsapp-api.php          # Classe de comunicação com API
│   ├── class-wc-whatsapp-logger.php       # Classe de logs
│   ├── class-wc-whatsapp-admin.php        # Classe do painel admin
│   └── class-wc-whatsapp-handler.php      # Classe de eventos de pedidos
├── templates/
│   └── admin-settings.php                 # Template da página de configurações
├── assets/
│   ├── css/
│   │   └── admin.css                      # Estilos do admin
│   └── js/
│       └── admin.js                       # Scripts do admin
└── README.md                              # Este arquivo
```

## Troubleshooting

### A API não está respondendo

1. Verifique se a URL e o Token estão corretos
2. Use o botão "Testar API" para verificar a conexão
3. Verifique os logs em **WooCommerce > Status > Logs**

### Mensagens não estão sendo enviadas

1. Verifique se o status está ativado nas configurações
2. Verifique se o cliente tem telefone cadastrado no pedido
3. Verifique se o telefone está no formato correto
4. Consulte os logs para mais detalhes

### Endpoint não encontrado

O plugin tenta automaticamente descobrir o endpoint correto. Se falhar:
1. Verifique se a URL base está correta
2. Verifique se o token está válido
3. Consulte a documentação da API para o endpoint correto

## Suporte

Para suporte, entre em contato através do site: https://cdwtech.com.br ou email: comercial@cdwtech.com.br

## Changelog

### 1.3.0
- **Nova Funcionalidade**: Adicionado suporte para envio de imagens junto com notificações
- **Nova Funcionalidade**: Upload de imagens na página de configurações (aba "Imagens")
- **Nova Funcionalidade**: Imagens convertidas para base64 e enviadas diretamente na mensagem
- **Nova Funcionalidade**: Configuração de imagem por status/template (processing, on-hold, completed, cancelled, refunded, tracking, customer_note)
- **Melhoria**: Suporte para formatos JPG, PNG, WEBP
- **Melhoria**: Integração com WordPress Media Library para upload de imagens

### 1.2.0
- **Compatibilidade HPOS**: Adicionada declaração de compatibilidade com High Performance Order Storage (HPOS)
- **Compatibilidade**: Atualizado para WooCommerce 10.4+
- **Correção**: Substituído método depreciado `get_customer_order_notes()` por `wc_get_order_notes()`
- **Melhoria**: Compatibilidade com estrutura de notas do WooCommerce 10.0+ (usa `content` em vez de `comment_content`)
- **Melhoria**: Substituído `get_post_meta()`/`update_post_meta()` por métodos do WC_Order (`get_meta()`/`update_meta_data()`) para compatibilidade total com HPOS
- **Atualização**: Versão testada atualizada de 8.0 para 10.4
- **Atualização**: WordPress mínimo atualizado para 5.8

### 1.1.4
- **Refatoração**: Remove integração direta com Correios do plugin
- Agora depende exclusivamente do plugin `wc-any-shipping-notify` para gerenciar transportadoras
- Remove detecção automática de códigos dos Correios
- Remove geração automática de URL dos Correios
- Remove fallback específico para Correios
- Melhora compatibilidade e evita conflitos com `wc-any-shipping-notify`

### 1.1.3
- **Correção**: Corrige erro "Call to undefined method WP_Post::get_status()" ao salvar pedidos no admin
- Melhora tratamento de tipos de objetos em hooks do WooCommerce
- Adiciona validação de tipo WC_Order antes de usar métodos do WooCommerce

### 1.1.0
- Adiciona campos de entrega nas mensagens (`{shipping_method}` e `{shipping_total}`)
- Melhora templates padrão com informações de entrega
- Atualiza documentação com novos placeholders

### 1.0.0
- Versão inicial
- Integração com API WhatsApp
- Suporte para múltiplos status de pedidos
- Notificações automáticas de códigos de rastreio
- Notificações automáticas de observações para cliente
- Integração com plugin wc-any-shipping-notify
- Descoberta automática de endpoints da API
- Suporte para múltiplos tipos de autenticação
- Painel de configurações completo
- Sistema de logs integrado
- Testes de conexão e envio
- Proteção contra notificações duplicadas
- Templates personalizáveis para todos os tipos de notificação

## Licença

Este plugin é desenvolvido por Jhou de Carvalho - CDW Tech (https://cdwtech.com.br).

