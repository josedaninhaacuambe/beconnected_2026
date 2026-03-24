# Beconnect - Mercado Virtual de Moçambique

## Visão Geral

Marketplace multi-loja para Moçambique com:
- Pesquisa de produtos em todas as lojas (sem preço na pesquisa global)
- Pagamento via eMola e M-Pesa
- Entrega em casa com rastreamento
- Painel para donos de loja (stock, pedidos, visibilidade)
- PWA instalável como app Android

---

## Pré-requisitos

- PHP 8.3+ (Laragon)
- MySQL 8.0+ (Laragon)
- Node.js 18+
- Composer 2.x

---

## Instalação

### 1. Configurar base de dados

No MySQL (via Laragon ou HeidiSQL), criar a base de dados:
```sql
CREATE DATABASE beconnect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Configurar .env

Editar `c:\laragon\www\Beconnect\.env`:
```env
DB_DATABASE=beconnect
DB_USERNAME=root
DB_PASSWORD=          # senha do MySQL (vazia por padrão no Laragon)
```

### 3. Instalar dependências PHP

```bash
composer install
php artisan key:generate
php artisan storage:link
```

### 4. Executar migrations e seeders

```bash
php artisan migrate --seed
```

Isto irá criar:
- Todas as 11 províncias de Moçambique
- Cidades e bairros principais
- Categorias de lojas (20 categorias)
- Planos de visibilidade
- Admin padrão: admin@beconnect.co.mz / Beconnect@2025

### 5. Instalar dependências JavaScript

```bash
npm install
```

### 6. Compilar assets

```bash
# Desenvolvimento
npm run dev

# Produção
npm run build
```

### 7. Configurar Virtual Host no Laragon

No Laragon, o virtual host `beconnect.test` é criado automaticamente.
Ou aceder via: `http://localhost/Beconnect/public`

---

## Configuração de Pagamentos

### M-Pesa (Vodacom Moçambique)
Registar em: https://developer.mpesa.vodacom.co.mz/

```env
MPESA_API_URL=https://api.mpesa.co.mz/ipg/v1x
MPESA_PUBLIC_KEY=<chave_publica_rsa>
MPESA_API_KEY=<api_key>
MPESA_SERVICE_PROVIDER_CODE=<codigo_empresa>
```

### eMola (Movitel Moçambique)
Contactar: developer@emola.co.mz

```env
EMOLA_API_URL=https://api.emola.co.mz/v1
EMOLA_MERCHANT_ID=<merchant_id>
EMOLA_API_KEY=<api_key>
EMOLA_API_SECRET=<api_secret>
```

---

## Estrutura da Aplicação

```
Beconnect/
├── app/
│   ├── Http/
│   │   ├── Controllers/API/   # Todos os controllers da API
│   │   └── Middleware/        # CheckRole middleware
│   ├── Models/                # 20+ models com relationships
│   ├── Policies/              # Autorização (Store, Product, Order)
│   └── Services/              # EmolaService, MpesaService, PaymentService, DeliveryService
├── database/
│   ├── migrations/            # 9 ficheiros de migration
│   └── seeders/               # Moçambique locations, categorias, planos
├── resources/
│   ├── css/app.css            # Tema africano (Tailwind CSS)
│   └── js/
│       ├── App.vue            # Root component
│       ├── app.js             # Bootstrap Vue + Pinia + Router
│       ├── router/            # Vue Router (SPA)
│       ├── stores/            # Pinia (auth, cart)
│       ├── views/             # Páginas
│       │   ├── Home.vue       # Página inicial
│       │   ├── Search.vue     # Pesquisa (sem preço)
│       │   ├── StoreDetail.vue # Loja (com preço)
│       │   ├── TrackDelivery.vue
│       │   ├── auth/          # Login, Register
│       │   ├── customer/      # Cart, Checkout, Orders
│       │   └── store/         # Dashboard, Products, Stock, Visibility
│       └── components/        # Layout (AppLayout, StoreLayout)
└── routes/
    ├── api.php                # API REST completa
    └── web.php                # SPA catch-all
```

---

## API Endpoints Principais

### Público
| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | /api/auth/register | Registar utilizador |
| POST | /api/auth/login | Login |
| GET | /api/products/search | Pesquisa global (SEM preço) |
| GET | /api/stores | Listar lojas (com filtros) |
| GET | /api/stores/{slug} | Detalhe da loja |
| GET | /api/stores/{slug}/products | Produtos da loja (COM preço) |
| GET | /api/delivery/track/{code} | Rastrear entrega |

### Autenticado (cliente)
| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | /api/cart | Ver carrinho |
| POST | /api/cart/items | Adicionar produto |
| POST | /api/orders/checkout | Finalizar compra |
| GET | /api/orders | Meus pedidos |

### Dono de Loja (role: store_owner)
| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | /api/store/dashboard | Dashboard com estatísticas |
| GET | /api/store/products | Produtos da minha loja |
| POST | /api/store/products | Criar produto |
| POST | /api/store/products/{id}/stock | Gerir stock |
| GET | /api/store/orders | Pedidos da loja |
| POST | /api/store/visibility/purchase | Comprar destaque |

---

## Funcionalidades por Perfil

### Cliente
- ✅ Pesquisar produtos sem ver preço
- ✅ Ver preço apenas dentro da loja
- ✅ Adicionar de várias lojas ao mesmo carrinho
- ✅ Pagar com eMola ou M-Pesa
- ✅ Solicitar entrega em casa
- ✅ Rastrear encomenda em tempo real
- ✅ Filtrar por província, cidade, bairro

### Dono de Loja
- ✅ Criar e gerir loja virtual
- ✅ Adicionar/editar produtos
- ✅ Gestão de stock com alertas
- ✅ Ver e processar pedidos
- ✅ Comprar planos de visibilidade/destaque
- ✅ Análise de receitas e vendas

### Admin
- ✅ Aprovar/rejeitar/suspender lojas
- ✅ Gerir utilizadores
- ✅ Aprovar estafetas

---

## App Android (PWA)

A aplicação é uma Progressive Web App (PWA). O utilizador pode:
1. Aceder ao site em Chrome/Edge no Android
2. Aparecerá automaticamente o banner "Instalar App"
3. Clicar em "Instalar" para adicionar ao ecrã inicial
4. Funciona como app nativa com ícone, ecrã completo e modo offline básico

---

## Suporte
📞 +258 84 000 0000
✉ suporte@beconnect.co.mz
