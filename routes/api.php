<?php

use App\Http\Controllers\API\AdminController;
use App\Http\Controllers\API\AdminProductVisibilityController;
use App\Http\Controllers\API\AdminStaffController;
use App\Http\Controllers\API\AdminStoreManagementController;
use App\Http\Controllers\API\AdminUserManagementController;
use App\Http\Controllers\API\AdminVisibilityController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CartController;
use App\Http\Controllers\API\DeliveryController;
use App\Http\Controllers\API\FlashSaleController;
use App\Http\Controllers\API\GoogleAuthController;
use App\Http\Controllers\API\LocationController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\StockImportController;
use App\Http\Controllers\API\StoreController;
use App\Http\Controllers\API\FeedbackController;
use App\Http\Controllers\API\PosController;
use App\Http\Controllers\API\ReviewController;
use App\Http\Controllers\API\StoreSectionController;
use App\Http\Controllers\API\VisibilityController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Beconnect API Routes
|--------------------------------------------------------------------------
*/

// =============================================
// AUTENTICAÇÃO
// =============================================
Route::prefix('auth')->middleware('throttle:auth-strict')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('register-with-store', [AuthController::class, 'registerWithStore']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('resend-otp', [AuthController::class, 'resendOtp']);

    // Google OAuth
    Route::get('google/redirect-url', [GoogleAuthController::class, 'redirectUrl']);
    Route::post('google/register-with-store', [GoogleAuthController::class, 'registerWithStore']);
    Route::get('google/callback', [GoogleAuthController::class, 'callbackRedirect']);
    Route::post('google/token', [GoogleAuthController::class, 'callback']);
});

// =============================================
// ROTAS PÚBLICAS
// =============================================

// Localização (Moçambique) — cache longa, rate limit normal
Route::prefix('locations')->middleware('throttle:api-public')->group(function () {
    Route::get('provinces', [LocationController::class, 'provinces']);
    Route::get('cities', [LocationController::class, 'cities']);
    Route::get('neighborhoods', [LocationController::class, 'neighborhoods']);
});

// Categorias e marcas
Route::middleware('throttle:api-public')->group(function () {
    Route::get('store-categories', [StoreController::class, 'categories']);
    Route::get('product-categories', [ProductController::class, 'categories']);
    Route::get('brands', [ProductController::class, 'brands']);
});

// Pesquisa global de produtos (SEM PREÇO)
Route::get('products/search', [ProductController::class, 'search'])->middleware('throttle:api-public');
Route::get('products/all', [ProductController::class, 'allProducts'])->middleware('throttle:api-public');

// ─── Viral hooks (públicos, sem autenticação) ─────────────────────────────
Route::middleware('throttle:api-public')->group(function () {
    Route::get('products/flash',     [ProductController::class, 'flashDeals']);
    Route::get('products/trending',  [ProductController::class, 'trending']);
    Route::get('products/discounts', [ProductController::class, 'discounts']);
});

// Lojas (listagem pública)
Route::middleware('throttle:api-public')->group(function () {
    Route::get('stores', [StoreController::class, 'index']);
    Route::get('stores/{slug}', [StoreController::class, 'show']);
});

// ─── Scan & Go ────────────────────────────────────────────────────────────
Route::get('stores/{slug}/scan', [StoreController::class, 'scanBarcode']);
Route::middleware('auth:sanctum')->post('stores/{slug}/in-store-checkout', [StoreController::class, 'inStoreCheckout']);

// Produtos de uma loja (COM PREÇO - só visível dentro da loja)
Route::get('stores/{storeSlug}/products', [ProductController::class, 'storeProducts']);
Route::get('stores/{storeSlug}/products/{productSlug}', [ProductController::class, 'show']);
Route::get('stores/{storeSlug}/sections', [StoreSectionController::class, 'publicIndex']);

// Avaliações de lojas (leitura pública, escrita autenticada)
Route::get('stores/{slug}/reviews', [ReviewController::class, 'storeReviews']);
Route::middleware('auth:sanctum')->post('stores/{slug}/reviews', [ReviewController::class, 'submitStoreReview']);

// Feedback / Reclamações / Sugestões (envio público ou autenticado)
Route::post('feedback', [FeedbackController::class, 'store']);

// Rastreamento de entrega
Route::get('delivery/track/{trackingCode}', [DeliveryController::class, 'track']);

// Planos de visibilidade
Route::get('visibility-plans', [VisibilityController::class, 'plans']);

// Callbacks de pagamento (gateways)
Route::post('payments/emola/callback', [PaymentController::class, 'emolaCallback'])
    ->name('payment.emola.callback');
Route::post('payments/mpesa/callback', [PaymentController::class, 'mpesaCallback'])
    ->name('payment.mpesa.callback');

// WEBHOOK de stock (sistemas externos enviam stock aqui, sem autenticação por token)
Route::post('stores/{storeToken}/stock/webhook', [StockImportController::class, 'webhook'])
    ->name('stock.webhook');


// =============================================
// ROTAS AUTENTICADAS
// =============================================
Route::middleware(['auth:sanctum', 'throttle:api-auth'])->group(function () {

    // Perfil
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('profile', [AuthController::class, 'updateProfile']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
        Route::post('claim-role', [AuthController::class, 'claimRole']);

        // Notificações do utilizador
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::get('unread-count', [NotificationController::class, 'unreadCount']);
            Route::post('read-all', [NotificationController::class, 'markAllRead']);
            Route::post('{id}/read', [NotificationController::class, 'markRead']);
        });
    });

    // Carrinho
    Route::prefix('cart')->middleware('throttle:cart')->group(function () {
        Route::get('/', [CartController::class, 'index']);
        Route::post('items', [CartController::class, 'addItem']);
        Route::put('items/{cartItem}', [CartController::class, 'updateItem']);
        Route::delete('items/{cartItem}', [CartController::class, 'removeItem']);
        Route::delete('/', [CartController::class, 'clear']);
    });

    // Pedidos (cliente)
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'myOrders']);
        Route::post('checkout', [OrderController::class, 'checkout'])->middleware('throttle:checkout');
        Route::get('checkout-status', [OrderController::class, 'checkoutStatus']);
        Route::get('{order}', [OrderController::class, 'show']);
        Route::post('{order}/cancel', [OrderController::class, 'cancel']);
    });

    // Pagamentos
    Route::prefix('payments')->group(function () {
        Route::get('/', [PaymentController::class, 'myPayments']);
        Route::get('{payment}/status', [PaymentController::class, 'checkStatus']);
    });

    // Entrega
    Route::prefix('delivery')->group(function () {
        Route::post('estimate', [DeliveryController::class, 'estimate']);
        Route::post('nearby-drivers', [DeliveryController::class, 'nearbyDrivers']);
        Route::post('register-driver', [DeliveryController::class, 'registerAsDriver']);
        Route::post('availability', [DeliveryController::class, 'updateAvailability']);
        Route::post('{delivery}/accept', [DeliveryController::class, 'acceptDelivery']);
        Route::post('{delivery}/status', [DeliveryController::class, 'updateDeliveryStatus']);
        Route::post('{delivery}/confirm-receipt', [DeliveryController::class, 'confirmReceipt']);
    });

    // =============================================
    // ROTAS DO DONO DE LOJA e POS EMPREGADOS
    // =============================================
    Route::prefix('store')->group(function () {
        // Produtos visíveis ao dono da loja e aos funcionários do POS
        Route::get('products', [ProductController::class, 'myProducts']);

        Route::middleware('role:store_owner,admin')->group(function () {
            Route::get('/', [StoreController::class, 'myStore']);
            Route::post('/', [StoreController::class, 'store']);
            Route::post('update', [StoreController::class, 'updateMyStore']);
            Route::get('dashboard', [StoreController::class, 'dashboard']);

            // Produtos
            Route::post('products', [ProductController::class, 'storeProduct']);
            Route::put('products/{product}', [ProductController::class, 'updateProduct']);
            Route::delete('products/{product}', [ProductController::class, 'destroyProduct']);
            Route::post('products/{product}/stock', [ProductController::class, 'updateStock']);
            Route::get('products/{product}/stock/movements', [ProductController::class, 'stockMovements']);
            Route::post('products/fetch-image', [ProductController::class, 'fetchAutoImage']);

            // Pedidos da loja
            Route::get('orders', [OrderController::class, 'storeOrders']);
            Route::put('orders/{storeOrder}/status', [OrderController::class, 'updateStoreOrderStatus']);

            // Carrinhos activos (potenciais vendas)
            Route::get('active-carts', function (Illuminate\Http\Request $req) {
                $store = \App\Models\Store::where('user_id', $req->user()->id)->firstOrFail();
                $carts = \App\Models\CartItem::with(['cart.user', 'product'])
                    ->whereHas('product', fn($q) => $q->where('store_id', $store->id))
                    ->whereHas('cart', fn($q) => $q->whereNotNull('user_id'))
                    ->get()
                    ->groupBy('cart_id')
                    ->map(function ($items) {
                        $cart = $items->first()->cart;
                        return [
                            'user' => $cart->user->only(['id', 'name', 'phone']),
                            'items' => $items->map(fn($i) => [
                                'product_name' => $i->product->name,
                                'quantity' => $i->quantity,
                            ]),
                            'total_value' => $items->sum(fn($i) => $i->unit_price * $i->quantity),
                            'added_at' => $items->max('created_at'),
                        ];
                    })
                    ->values();
                return response()->json($carts);
            });

            // Visibilidade/Posicionamento
            Route::post('visibility/purchase', [VisibilityController::class, 'purchase']);
        });

        // ─── IMPORTAÇÃO DE STOCK ─────────────────────────────────────────
        Route::prefix('stock')->group(function () {
            // Pré-visualizar ficheiro (mostra colunas e sugestão de mapeamento)
            Route::post('preview', [StockImportController::class, 'preview']);

            // Importar de Excel/CSV
            Route::post('import-file', [StockImportController::class, 'importFile']);

            // Importar via JSON (de qualquer sistema externo)
            Route::post('import-json', [StockImportController::class, 'importJson']);

            // Histórico de importações
            Route::get('history', [StockImportController::class, 'importHistory']);

            // APIs externas
            Route::get('external-apis', [StockImportController::class, 'listExternalApis']);
            Route::post('external-apis', [StockImportController::class, 'configureExternalApi']);
            Route::post('external-apis/test', [StockImportController::class, 'testExternalApi']);
            Route::post('external-apis/{api}/sync', [StockImportController::class, 'syncNow']);
        });

        // ─── QUEIMA DE STOCK ─────────────────────────────────────────────
        Route::prefix('flash-sales')->group(function () {
            Route::get('/', [FlashSaleController::class, 'index']);
            Route::get('eligible', [FlashSaleController::class, 'eligibleProducts']);
            Route::post('launch', [FlashSaleController::class, 'launch']);
            Route::delete('{product}', [FlashSaleController::class, 'cancel']);
        });

        // ─── SECÇÕES / CATEGORIAS DA LOJA ───────────────────────────────
        Route::prefix('sections')->group(function () {
            Route::get('/', [StoreSectionController::class, 'index']);
            Route::post('/', [StoreSectionController::class, 'store']);
            Route::post('/reorder', [StoreSectionController::class, 'reorder']);
            Route::put('/{section}', [StoreSectionController::class, 'update']);
            Route::delete('/{section}', [StoreSectionController::class, 'destroy']);
        });

        // ─── GESTÃO DE FUNCIONÁRIOS ──────────────────────────────────────
        Route::prefix('employees')->group(function () {
            Route::get('/', [StockImportController::class, 'listEmployees']);
            Route::post('/', [StockImportController::class, 'addEmployee']);
            Route::delete('{employee}', [StockImportController::class, 'removeEmployee']);
        });
    });

    // =============================================
    // ROTAS POS (Point of Sale)
    // Acessível a owners e funcionários activos
    // =============================================
    Route::prefix('pos')->group(function () {
        Route::get('products',        [PosController::class, 'products']);
        Route::post('sync',           [PosController::class, 'sync'])->middleware('throttle:pos-sync');
        Route::get('stock',           [PosController::class, 'stock']);
        Route::post('stock/movement', [PosController::class, 'stockMovement']);
        Route::get('stock/history',   [PosController::class, 'stockHistory']);
        Route::get('reports',         [PosController::class, 'reports']);
        Route::post('sync-products',  [PosController::class, 'syncProducts']);
        Route::get('employees',                              [PosController::class, 'employees']);
        Route::post('employees',                             [PosController::class, 'addEmployee']);
        Route::post('employees/create-account',             [PosController::class, 'createEmployeeAccount']);
        Route::put('employees/{employee}',                   [PosController::class, 'updateEmployee']);
        Route::put('employees/{employee}/reset-password',    [PosController::class, 'resetEmployeePassword']);
        Route::delete('employees/{employee}',                [PosController::class, 'removeEmployee']);
    });

    // =============================================
    // ROTAS DE ADMIN
    // =============================================
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        // Feedbacks / Reclamações / Sugestões
        Route::get('feedbacks', [FeedbackController::class, 'index']);
        Route::put('feedbacks/{feedback}', [FeedbackController::class, 'update']);

        // Visão geral
        Route::get('overview', [AdminController::class, 'overview']);

        // Gestão de utilizadores
        Route::get('users', [AdminController::class, 'users']);
        Route::put('users/{user}/promote-store-owner', [AdminController::class, 'promoteToStoreOwner']);
        Route::put('users/{user}/demote-customer', [AdminController::class, 'demoteToCustomer']);
        Route::put('users/{user}/toggle', [AdminController::class, 'toggleUserStatus']);

        // Gestão de lojas
        Route::get('stores', [AdminController::class, 'stores']);
        Route::put('stores/{store}/approve', [AdminController::class, 'approveStore']);
        Route::put('stores/{store}/reject', [AdminController::class, 'rejectStore']);
        Route::put('stores/{store}/suspend', [AdminController::class, 'suspendStore']);
        Route::put('stores/{store}', [AdminController::class, 'updateStore']);

        // Estafetas
        Route::get('drivers', fn() => \App\Models\DeliveryDriver::with('user')->paginate(20));
        Route::put('drivers/{driver}/approve', function (\App\Models\DeliveryDriver $driver) {
            $driver->update(['status' => 'approved']);
            return response()->json(['message' => 'Estafeta aprovado.']);
        });
        Route::put('drivers/{driver}/suspend', function (\App\Models\DeliveryDriver $driver) {
            $driver->update(['status' => 'suspended', 'is_available' => false]);
            return response()->json(['message' => 'Estafeta suspenso.']);
        });

        // Monitorização de entregas
        Route::get('deliveries', function (Illuminate\Http\Request $req) {
            $q = \App\Models\Delivery::with(['order.user', 'driver.user'])
                ->when($req->status, fn($q) => $q->where('status', $req->status))
                ->latest();
            return response()->json($q->paginate(30));
        });
        Route::get('deliveries/{delivery}', function (\App\Models\Delivery $delivery) {
            return response()->json($delivery->load(['order.user', 'order.storeOrders.items', 'driver.user']));
        });
        Route::post('deliveries/{delivery}/reassign', function (Illuminate\Http\Request $req, \App\Models\Delivery $delivery) {
            $req->validate(['driver_id' => 'required|exists:delivery_drivers,id']);
            $driver = \App\Models\DeliveryDriver::findOrFail($req->driver_id);
            $delivery->update(['driver_id' => $driver->id, 'status' => 'assigned', 'assigned_at' => now()]);
            return response()->json(['message' => 'Estafeta reatribuído.']);
        });

        // ─── COMISSÕES ────────────────────────────────────────────────────
        Route::prefix('commissions')->group(function () {
            Route::get('dashboard', [AdminController::class, 'commissionDashboard']);
            Route::get('/', [AdminController::class, 'commissions']);
            Route::post('payout', [AdminController::class, 'processCommissionPayout']);
            Route::get('payouts', [AdminController::class, 'payoutHistory']);
        });

        // ─── STAFF ────────────────────────────────────────────────────────
        Route::get('staff', [AdminStaffController::class, 'index']);
        Route::post('staff', [AdminStaffController::class, 'store']);
        Route::put('staff/{user}', [AdminStaffController::class, 'update']);
        Route::put('staff/{user}/toggle', [AdminStaffController::class, 'toggleStatus']);
        Route::delete('staff/{user}', [AdminStaffController::class, 'destroy']);

        // ─── VISIBILITY ───────────────────────────────────────────────────
        Route::get('visibility/dashboard', [AdminVisibilityController::class, 'dashboard']);
        Route::get('visibility', [AdminVisibilityController::class, 'index']);
        Route::get('visibility/pending-renewals', [AdminVisibilityController::class, 'pendingRenewals']);
        Route::get('visibility/stores/{store}/history', [AdminVisibilityController::class, 'storeHistory']);
        Route::get('visibility/{purchase}/history', [AdminVisibilityController::class, 'purchaseHistory']);
        Route::post('visibility/stores/{store}/activate', [AdminVisibilityController::class, 'activate']);
        Route::put('visibility/{purchase}/status', [AdminVisibilityController::class, 'updateStatus']);
        Route::delete('visibility/{purchase}', [AdminVisibilityController::class, 'remove']);
        Route::post('visibility/{purchase}/remind', [AdminVisibilityController::class, 'sendPaymentReminder']);

        // ─── STORE MANAGEMENT (Enhanced) ───────────────────────────────────
        Route::get('stores-list', [AdminStoreManagementController::class, 'index']);
        Route::get('stores-list/{store}', [AdminStoreManagementController::class, 'show']);
        Route::put('stores-list/{store}/availability', [AdminStoreManagementController::class, 'updateAvailability']);
        Route::put('stores-list/{store}/visibility', [AdminStoreManagementController::class, 'toggleVisibility']);
        Route::put('stores-list/{store}/suspend', [AdminStoreManagementController::class, 'suspend']);
        Route::put('stores-list/{store}/reactivate', [AdminStoreManagementController::class, 'reactivate']);

        // ─── USER MANAGEMENT (Enhanced) ───────────────────────────────────
        Route::get('users-list', [AdminUserManagementController::class, 'index']);
        Route::get('users-list/{user}', [AdminUserManagementController::class, 'show']);
        Route::put('users-list/{user}/deactivate', [AdminUserManagementController::class, 'deactivate']);
        Route::put('users-list/{user}/reactivate', [AdminUserManagementController::class, 'reactivate']);
        Route::get('users-list/statistics/overall', [AdminUserManagementController::class, 'statistics']);

        // ─── PRODUCT VISIBILITY MANAGEMENT ────────────────────────────────
        Route::get('products/pending-approvals', [AdminProductVisibilityController::class, 'pendingApprovals']);
        Route::get('stores/{store}/products-list', [AdminProductVisibilityController::class, 'storeProducts']);
        Route::post('products/{product}/approve', [AdminProductVisibilityController::class, 'approveProduct']);
        Route::post('products/{product}/reject', [AdminProductVisibilityController::class, 'rejectProduct']);
        Route::put('products/{product}/revoke-approval', [AdminProductVisibilityController::class, 'revokeApproval']);
        Route::post('stores/{store}/products/approve-all', [AdminProductVisibilityController::class, 'approveStoreProducts']);

        // ─── ORDERS (enhanced) ────────────────────────────────────────────
        Route::get('orders', [AdminController::class, 'allOrders']);
        Route::put('orders/{order}/resolve', [AdminController::class, 'resolveOrder']);

        // ─── ALERTS ───────────────────────────────────────────────────────
        Route::get('alerts/count', [AdminController::class, 'alertsCount']);
    });
});
