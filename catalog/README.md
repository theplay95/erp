# Catálogo Online - Mundo da Carne

## Visão Geral

Este é um catálogo online completo que permite aos clientes fazer pedidos diretamente pelo site. O sistema está integrado com o ERP existente e inclui:

## Funcionalidades

### Para Clientes
- **Catálogo de Produtos**: Visualização de todos os produtos disponíveis com preços e estoque
- **Filtros e Busca**: Filtrar por categoria e buscar produtos por nome
- **Carrinho de Compras**: Adicionar/remover produtos, ajustar quantidades
- **Checkout Completo**: Processo de finalização em 4 etapas
- **Dados do Cliente**: Formulário para dados de entrega
- **Formas de Pagamento**: Dinheiro, PIX, Cartão de Crédito/Débito
- **Confirmação**: Resumo completo antes da finalização

### Para a Empresa
- **Integração Total**: Pedidos aparecem automaticamente no sistema ERP
- **Gestão de Estoque**: Stock atualizado automaticamente
- **Clientes**: Cadastro automático de novos clientes
- **Entregas**: Pedidos online criam automaticamente uma ordem de entrega
- **Auditoria**: Todos os pedidos são registrados no sistema de auditoria

## Estrutura de Arquivos

```
catalog/
├── index.php          # Página principal do catálogo
├── checkout.php       # Processo de finalização do pedido
├── submit_order.php   # API para processar pedidos
├── js/
│   ├── catalog.js     # JavaScript do catálogo
│   └── checkout.js    # JavaScript do checkout
└── README.md          # Esta documentação
```

## Fluxo do Pedido

1. **Cliente navega no catálogo** (index.php)
2. **Adiciona produtos ao carrinho** (catalog.js)
3. **Finaliza pedido** (checkout.php)
4. **Preenche dados pessoais**
5. **Escolhe forma de pagamento**
6. **Confirma pedido**
7. **Sistema processa** (submit_order.php)
8. **Pedido aparece no ERP**

## Integração com o ERP

### Tabelas Utilizadas
- `customers`: Clientes são criados/atualizados automaticamente
- `products`: Lista de produtos e controle de estoque
- `sales`: Pedido principal
- `sale_items`: Itens do pedido
- `delivery_orders`: Ordem de entrega criada automaticamente
- `audit_logs`: Log de todas as ações

### Funcionalidades do ERP Utilizadas
- Gestão de produtos e estoque
- Sistema de clientes
- Processamento de vendas
- Módulo de delivery
- Sistema de auditoria

## Como Usar

### Para Configurar
1. O catálogo já está integrado com a base de dados existente
2. Acesse `/catalog/` no navegador
3. Os produtos são puxados automaticamente da tabela `products`

### Para Clientes
1. Acesse o catálogo online
2. Navegue pelos produtos
3. Adicione produtos ao carrinho
4. Clique em "Finalizar Pedido"
5. Preencha seus dados
6. Escolha a forma de pagamento
7. Confirme o pedido

### Para Administradores
1. Os pedidos aparecem automaticamente no módulo de vendas
2. As entregas são criadas automaticamente no módulo de delivery
3. O estoque é atualizado em tempo real
4. Todos os logs ficam no sistema de auditoria

## Recursos Técnicos

### Frontend
- Bootstrap 5 para design responsivo
- JavaScript vanilla para interatividade
- LocalStorage para persistir carrinho
- SPA-like experience com AJAX

### Backend
- PHP para processamento
- PDO para database
- Transações para integridade dos dados
- Validação completa de dados
- Tratamento de erros

### Segurança
- Validação de estoque em tempo real
- Transações de database
- Sanitização de dados
- Prevenção de SQL injection

## Características do Design

- **Responsivo**: Funciona em desktop, tablet e mobile
- **Moderno**: Design limpo e profissional
- **Intuitivo**: Navegação fácil e processo de compra simples
- **Rápido**: Carregamento otimizado e feedback visual

## Taxa de Entrega

- Taxa fixa de R$ 5,00 por pedido
- Configurável no código (submit_order.php)

## Status dos Pedidos

Os pedidos criados pelo catálogo:
- Aparecem no módulo de vendas com todos os detalhes
- Criam automaticamente uma ordem de entrega com status "pendente"
- Podem ser gerenciados normalmente pelo sistema ERP