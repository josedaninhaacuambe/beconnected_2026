<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreOrder;
use App\Models\StoreEmployee;
use App\Traits\ResolvesOwnerStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PosController extends Controller
{
    use ResolvesOwnerStore;

    // Resolve a loja do utilizador (dono ou funcionário activo)
    // Donos de loja com múltiplas lojas: usa X-Store-Id header para seleccionar.
    private function resolveStore(Request $request)
    {
        $user = $request->user();

        // 1) Se for dono de loja, respeita o header X-Store-Id (multi-loja)
        if (in_array($user->role, ['store_owner', 'admin'])) {
            $storeId = $request->header('X-Store-Id') ?? $request->input('store_id');
            if ($storeId) {
                if ($user->role === 'admin') {
                    return Store::findOrFail($storeId);
                }
                return Store::where('id', $storeId)
                    ->where('user_id', $user->id)
                    ->firstOrFail();
            }
            // Sem header: usa a primeira loja
            $store = $user->stores()->first() ?? $user->store;
            abort_if(!$store, 403, 'Sem acesso a nenhuma loja.');
            return $store;
        }

        // 2) Se for funcionário POS, usa a loja do employee activo.
        if ($user->pos_employee?->store) {
            return $user->pos_employee->store;
        }

        $employee = StoreEmployee::where('user_id', $user->id)
            ->where('is_active', true)
            ->with('store')
            ->orderByDesc('id')
            ->first();

        if ($employee?->store) {
            return $employee->store;
        }

        abort(403, 'Sem acesso a nenhuma loja.');
    }

    // Verifica se o utilizador tem uma permissão POS específica
    // Donos e admins têm sempre acesso total
    private function requirePosPermission(Request $request, string $permission): void
    {
        $user = $request->user();
        if (in_array($user->role, ['store_owner', 'admin'])) return;

        $emp = StoreEmployee::where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        abort_if(!$emp, 403, 'Sem acesso ao POS.');

        $permissions = $emp->permissions ?? StoreEmployee::defaultPermissions($emp->role);
        abort_unless(in_array($permission, $permissions), 403, "Sem permissão: {$permission}.");
    }

    // ─── Produtos para o terminal POS ─────────────────────────────────────────
    public function products(Request $request): JsonResponse
    {
        $this->requirePosPermission($request, 'fazer_vendas');
        $store = $this->resolveStore($request);

        // Cache por 5 minutos — invalidado ao registar venda/movimento de stock
        $products = Cache::remember("pos_products_{$store->id}", 300, function () use ($store) {
            $fields = ['id', 'name', 'price', 'cost_price', 'sku', 'barcode', 'images', 'is_weighable', 'weight_unit', 'product_category_id'];
            if (Product::hasAvailabilityColumn()) {
                $fields[] = 'availability';
            }

            return $store->products()
                ->with('stock')
                ->where('is_active', true)
                ->forPos()
                ->orderBy('name')
                ->get($fields)
                ->map(function ($p) {
                    $images = $p->images ?? [];
                    if (is_string($images)) $images = json_decode($images, true) ?? [];
                    $p->image = count($images) > 0 ? $images[0] : null;
                    $p->category_id = $p->product_category_id ?? null;
                    unset($p->images);
                    return $p;
                });
        });

        return response()->json($products);
    }

    // ─── Registar venda (single ou batch sync offline) ─────────────────────────
    public function sync(Request $request): JsonResponse
    {
        $this->requirePosPermission($request, 'fazer_vendas');
        $store = $this->resolveStore($request);

        $request->validate([
            'sales'                          => 'required|array|min:1',
            'sales.*.local_id'               => 'required|string',
            'sales.*.total'                  => 'required|numeric|min:0',
            'sales.*.payment_method'         => 'required|in:cash,mpesa,emola,credit',
            'sales.*.sale_at'                => 'required|date',
            'sales.*.apply_vat'              => 'nullable|boolean',
            'sales.*.vat_rate'               => 'nullable|numeric|min:0|max:100',
            'sales.*.vat_amount'             => 'nullable|numeric|min:0',
            'sales.*.items'                  => 'required|array|min:1',
            'sales.*.items.*.product_id'     => 'nullable|integer',
            'sales.*.items.*.product_name'   => 'required|string',
            'sales.*.items.*.unit_price'     => 'required|numeric|min:0',
            'sales.*.items.*.cost_price'     => 'nullable|numeric|min:0',
            'sales.*.items.*.quantity'       => 'required|integer|min:1',
            'sales.*.items.*.weight_amount'  => 'nullable|numeric|min:0',
            'sales.*.items.*.weight_unit'    => 'nullable|string|max:5',
            'sales.*.items.*.subtotal'       => 'required|numeric|min:0',
        ]);

        $created = 0;
        $skipped = 0;

        $productIdsToInvalidate = [];

        DB::transaction(function () use ($request, $store, &$created, &$skipped, &$productIdsToInvalidate) {
            foreach ($request->sales as $saleData) {
                // Evitar duplicados por local_id
                if (PosSale::where('store_id', $store->id)->where('local_id', $saleData['local_id'])->exists()) {
                    $skipped++;
                    continue;
                }

                $sale = PosSale::create([
                    'store_id'       => $store->id,
                    'user_id'        => $request->user()->id,
                    'local_id'       => $saleData['local_id'],
                    'subtotal'       => $saleData['subtotal']     ?? $saleData['total'],
                    'discount'       => $saleData['discount']     ?? 0,
                    'apply_vat'      => $saleData['apply_vat']    ?? false,
                    'vat_rate'       => $saleData['vat_rate']     ?? 17.00,
                    'vat_amount'     => $saleData['vat_amount']   ?? 0,
                    'total'          => $saleData['total'],
                    'payment_method' => $saleData['payment_method'],
                    'customer_name'  => $saleData['customer_name']  ?? null,
                    'customer_phone' => $saleData['customer_phone'] ?? null,
                    'notes'          => $saleData['notes']          ?? null,
                    'synced'         => true,
                    'sale_at'        => $saleData['sale_at'],
                ]);

                foreach ($saleData['items'] as $item) {
                    PosSaleItem::create([
                        'pos_sale_id'  => $sale->id,
                        'product_id'   => $item['product_id']    ?? null,
                        'product_name' => $item['product_name'],
                        'product_sku'  => $item['product_sku']   ?? null,
                        'unit_price'   => $item['unit_price'],
                        'cost_price'   => $item['cost_price']    ?? 0,
                        'quantity'     => $item['quantity'],
                        'weight_amount'=> $item['weight_amount'] ?? null,
                        'weight_unit'  => $item['weight_unit']   ?? null,
                        'subtotal'     => $item['subtotal'],
                    ]);

                    // Baixar stock automaticamente + sincronizar com loja online
                    if (!empty($item['product_id'])) {
                        $stock = ProductStock::where('product_id', $item['product_id'])->first();
                        if ($stock) {
                            $before = $stock->quantity;
                            $stock->decrement('quantity', $item['quantity']);
                            StockMovement::create([
                                'product_id'      => $item['product_id'],
                                'type'            => 'out',
                                'quantity'        => $item['quantity'],
                                'quantity_before' => $before,
                                'quantity_after'  => $before - $item['quantity'],
                                'reason'          => 'Venda POS #' . $sale->id,
                                'user_id'         => $request->user()->id,
                            ]);
                            // Sincronizar total_sold e marcar produto para invalidar cache
                            Product::where('id', $item['product_id'])
                                ->increment('total_sold', $item['quantity']);
                            $productIdsToInvalidate[] = $item['product_id'];
                        }
                    }
                }
                $created++;
            }
        });

        // Invalidar caches da loja online após transacção concluída
        foreach (array_unique($productIdsToInvalidate) as $pid) {
            Cache::forget("cart_product_{$pid}");
        }
        if (!empty($productIdsToInvalidate)) {
            Cache::forget('products_flash');
            Cache::forget('products_trending');
            Cache::forget("pos_products_{$store->id}");
        }

        return response()->json(['synced' => $created, 'skipped' => $skipped]);
    }

    // ─── Movimento de stock (entrada / saída / ajuste) ─────────────────────────
    public function stockMovement(Request $request): JsonResponse
    {
        $this->requirePosPermission($request, 'gerir_stock');
        $store = $this->resolveStore($request);

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'type'       => 'required|in:in,out,adjustment',
            'quantity'   => 'required|integer|min:1',
            'reason'     => 'nullable|string|max:200',
        ]);

        $product = $store->products()->findOrFail($validated['product_id']);
        $stock   = ProductStock::firstOrCreate(['product_id' => $product->id], ['quantity' => 0, 'minimum_stock' => 5, 'unit' => 'un']);

        $before = $stock->quantity;

        if ($validated['type'] === 'in') {
            $stock->increment('quantity', $validated['quantity']);
        } elseif ($validated['type'] === 'out') {
            abort_if($stock->quantity < $validated['quantity'], 422, 'Stock insuficiente.');
            $stock->decrement('quantity', $validated['quantity']);
        } else {
            $stock->update(['quantity' => $validated['quantity']]);
        }

        StockMovement::create([
            'product_id'      => $product->id,
            'type'            => $validated['type'],
            'quantity'        => $validated['quantity'],
            'quantity_before' => $before,
            'quantity_after'  => $stock->fresh()->quantity,
            'reason'          => $validated['reason'] ?? ucfirst($validated['type']) . ' manual',
            'user_id'         => $request->user()->id,
        ]);

        Cache::forget("pos_products_{$store->id}");

        return response()->json(['message' => 'Movimento registado.', 'stock' => $stock->fresh()]);
    }

    // ─── Relatórios unificados POS + Online ───────────────────────────────────
    public function reports(Request $request): JsonResponse
    {
        $this->requirePosPermission($request, 'ver_relatorios');
        $store = $this->resolveStore($request);

        $from = $request->from ? now()->parse($request->from)->startOfDay() : now()->startOfMonth();
        $to   = $request->to   ? now()->parse($request->to)->endOfDay()     : now()->endOfDay();

        // ── Vendas POS no período seleccionado ────────────────────────────────
        $posSales = PosSale::where('store_id', $store->id)
            ->whereBetween('sale_at', [$from, $to])
            ->with(['items', 'user:id,name'])
            ->orderByDesc('sale_at')
            ->get();

        // ── Vendas Online no período seleccionado ─────────────────────────────
        $onlineOrders = StoreOrder::where('store_id', $store->id)
            ->whereNotIn('status', ['cancelled'])
            ->whereBetween('created_at', [$from, $to])
            ->with(['items.product:id,name,cost_price', 'order:id,payment_method,created_at'])
            ->get();

        // ── Helper: calcular sumário de um conjunto de POS + Online ───────────
        $buildSummary = function ($sales, $orders) {
            $posRev    = $sales->sum('total');
            $onlineRev = $orders->sum('subtotal');
            $total     = $posRev + $onlineRev;
            $count     = $sales->count() + $orders->count();

            // Lucro bruto POS: revenue - custo
            $posCost = 0;
            foreach ($sales as $s) {
                foreach ($s->items as $item) {
                    $posCost += ($item->cost_price ?? 0) * $item->quantity;
                }
            }
            // Lucro bruto Online
            $onlineCost = 0;
            foreach ($orders as $o) {
                foreach ($o->items as $item) {
                    $onlineCost += ($item->product?->cost_price ?? 0) * $item->quantity;
                }
            }
            $totalCost   = $posCost + $onlineCost;
            $grossProfit = $total - $totalCost;

            // IVA cobrado (POS com IVA activo)
            $vatCollected = $sales->where('apply_vat', true)->sum('vat_amount');

            return [
                'totalRevenue'  => $total,
                'totalSales'    => $count,
                'avgTicket'     => $count > 0 ? $total / $count : 0,
                'posRevenue'    => $posRev,
                'onlineRevenue' => $onlineRev,
                'totalCost'     => $totalCost,
                'grossProfit'   => $grossProfit,
                'vatCollected'  => $vatCollected,
            ];
        };

        // ── Sumários por período ───────────────────────────────────────────────
        $periodSummary = function (string $start, string $end) use ($store, $buildSummary) {
            $f = now()->parse($start)->startOfDay();
            $t = now()->parse($end)->endOfDay();
            $sales  = PosSale::where('store_id', $store->id)->whereBetween('sale_at', [$f, $t])->with('items')->get();
            $orders = StoreOrder::where('store_id', $store->id)->whereNotIn('status', ['cancelled'])
                ->whereBetween('created_at', [$f, $t])->with('items.product:id,cost_price')->get();
            return $buildSummary($sales, $orders);
        };

        $today     = now()->toDateString();
        $weekStart = now()->startOfWeek()->toDateString();
        $monthStart= now()->startOfMonth()->toDateString();
        $yearStart = now()->startOfYear()->toDateString();

        $periods = [
            'today'  => $periodSummary($today,      $today),
            'week'   => $periodSummary($weekStart,   $today),
            'month'  => $periodSummary($monthStart,  $today),
            'year'   => $periodSummary($yearStart,   $today),
            'custom' => $buildSummary($posSales, $onlineOrders),
        ];

        // ── Por dia (POS + Online) ─────────────────────────────────────────────
        $dayMap = [];
        foreach ($posSales as $s) {
            $d = $s->sale_at->format('Y-m-d');
            $dayMap[$d] = ($dayMap[$d] ?? ['date' => $s->sale_at->format('d/m'), 'total' => 0, 'count' => 0, 'pos' => 0, 'online' => 0, 'profit' => 0]);
            $dayMap[$d]['total'] += $s->total;
            $dayMap[$d]['pos']   += $s->total;
            $dayMap[$d]['count']++;
            foreach ($s->items as $item) {
                $dayMap[$d]['profit'] += ($s->total - ($item->cost_price ?? 0) * $item->quantity);
            }
        }
        foreach ($onlineOrders as $o) {
            $d = $o->created_at->format('Y-m-d');
            $dayMap[$d] = ($dayMap[$d] ?? ['date' => $o->created_at->format('d/m'), 'total' => 0, 'count' => 0, 'pos' => 0, 'online' => 0, 'profit' => 0]);
            $dayMap[$d]['total']  += $o->subtotal;
            $dayMap[$d]['online'] += $o->subtotal;
            $dayMap[$d]['count']++;
        }
        ksort($dayMap);
        $byDay = array_values($dayMap);

        // ── Por método de pagamento ────────────────────────────────────────────
        $payMap = [];
        foreach ($posSales as $s) {
            $m = $s->payment_method;
            $payMap[$m] = ($payMap[$m] ?? ['method' => $m, 'total' => 0, 'count' => 0]);
            $payMap[$m]['total'] += $s->total; $payMap[$m]['count']++;
        }
        foreach ($onlineOrders as $o) {
            $m = $o->order?->payment_method ?? 'online';
            $payMap[$m] = ($payMap[$m] ?? ['method' => $m, 'total' => 0, 'count' => 0]);
            $payMap[$m]['total'] += $o->subtotal; $payMap[$m]['count']++;
        }
        $byPayment = array_values($payMap);

        // ── Top produtos (POS + Online) ────────────────────────────────────────
        $prodMap  = [];
        $posItems = PosSaleItem::whereIn('pos_sale_id', $posSales->pluck('id'))
            ->selectRaw('product_name, SUM(quantity) as qty, SUM(subtotal) as revenue, SUM(cost_price * quantity) as cost')
            ->groupBy('product_name')->get();
        foreach ($posItems as $p) {
            $prodMap[$p->product_name] = [
                'product_name' => $p->product_name, 'qty' => $p->qty,
                'revenue' => $p->revenue, 'cost' => $p->cost,
                'profit' => $p->revenue - $p->cost,
            ];
        }
        foreach ($onlineOrders as $o) {
            foreach ($o->items as $item) {
                $name = $item->product?->name ?? $item->product_name ?? 'Produto';
                $cost = ($item->product?->cost_price ?? 0) * $item->quantity;
                $prodMap[$name] = ($prodMap[$name] ?? ['product_name' => $name, 'qty' => 0, 'revenue' => 0, 'cost' => 0, 'profit' => 0]);
                $prodMap[$name]['qty']     += $item->quantity;
                $prodMap[$name]['revenue'] += $item->subtotal;
                $prodMap[$name]['cost']    += $cost;
                $prodMap[$name]['profit']  += $item->subtotal - $cost;
            }
        }
        usort($prodMap, fn($a, $b) => $b['revenue'] <=> $a['revenue']);
        $topProducts = array_slice($prodMap, 0, 10);

        // ── Por vendedor (POS) ─────────────────────────────────────────────────
        $bySeller = $posSales->groupBy('user_id')
            ->map(fn($g) => [
                'name'  => $g->first()->user?->name ?? 'Desconhecido',
                'total' => $g->sum('total'),
                'count' => $g->count(),
            ])->values();

        return response()->json([
            'summary'       => $periods['custom'],  // retrocompat
            'periods'       => $periods,
            'by_day'        => $byDay,
            'by_payment'    => $byPayment,
            'top_products'  => $topProducts,
            'by_seller'     => $bySeller,
            'pos_sales'     => $posSales->take(50),
            'online_orders' => $onlineOrders->take(50),
        ]);
    }

    // ─── Fecho de caixa do dia ─────────────────────────────────────────────────
    public function dailyCash(Request $request): JsonResponse
    {
        $this->requirePosPermission($request, 'fazer_vendas');
        $store = $this->resolveStore($request);
        $user  = $request->user();

        $date = $request->date
            ? now()->parse($request->date)->toDateString()
            : now()->toDateString();

        $from = now()->parse($date)->startOfDay();
        $to   = now()->parse($date)->endOfDay();

        // Donos e gerentes vêem todos os vendedores; vendedores só vêem as suas próprias vendas
        $isOwnerOrManager = in_array($user->role, ['store_owner', 'admin'])
            || StoreEmployee::where('user_id', $user->id)
                ->where('is_active', true)
                ->whereIn('role', ['manager'])
                ->exists();

        $query = PosSale::where('store_id', $store->id)
            ->whereBetween('sale_at', [$from, $to])
            ->with(['items', 'user:id,name'])
            ->orderBy('sale_at');

        if (!$isOwnerOrManager) {
            $query->where('user_id', $user->id);
        }

        // Filtro por vendedor (para gerente/dono)
        if ($request->seller_id && $isOwnerOrManager) {
            $query->where('user_id', $request->seller_id);
        }

        $sales = $query->get();

        // Por método de pagamento
        $byPayment = $sales->groupBy('payment_method')
            ->map(fn($g) => [
                'method' => $g->first()->payment_method,
                'total'  => round($g->sum('total'), 2),
                'count'  => $g->count(),
            ])->values();

        // Por vendedor (apenas para owner/manager)
        $bySeller = [];
        if ($isOwnerOrManager) {
            $bySeller = $sales->groupBy('user_id')
                ->map(fn($g) => [
                    'user_id' => $g->first()->user_id,
                    'name'    => $g->first()->user?->name ?? 'Desconhecido',
                    'total'   => round($g->sum('total'), 2),
                    'sales'   => $g->count(),
                ])->values();
        }

        // Lista de vendedores disponíveis para filtro
        $sellers = [];
        if ($isOwnerOrManager) {
            $sellers = PosSale::where('store_id', $store->id)
                ->whereBetween('sale_at', [$from, $to])
                ->with('user:id,name')
                ->get(['user_id'])
                ->unique('user_id')
                ->map(fn($s) => ['id' => $s->user_id, 'name' => $s->user?->name])
                ->values();
        }

        return response()->json([
            'date'           => $date,
            'is_owner_or_manager' => $isOwnerOrManager,
            'sales'          => $sales,
            'total_sales'    => $sales->count(),
            'total_revenue'  => round($sales->sum('total'), 2),
            'total_discount' => round($sales->sum('discount'), 2),
            'total_vat'      => round($sales->sum('vat_amount'), 2),
            'by_payment'     => $byPayment,
            'by_seller'      => $bySeller,
            'sellers'        => $sellers,
        ]);
    }

    // ─── Listar stock da loja ──────────────────────────────────────────────────
    public function stock(Request $request): JsonResponse
    {
        $this->requirePosPermission($request, 'gerir_stock');
        $store = $this->resolveStore($request);

        $products = $store->products()
            ->with('stock')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'barcode', 'price', 'images']);

        return response()->json($products);
    }

    // ─── Histórico de movimentos ───────────────────────────────────────────────
    public function stockHistory(Request $request): JsonResponse
    {
        $this->requirePosPermission($request, 'gerir_stock');
        $store = $this->resolveStore($request);

        $movements = StockMovement::whereIn('product_id', $store->products()->pluck('id'))
            ->with(['product:id,name,sku', 'user:id,name'])
            ->orderByDesc('created_at')
            ->paginate(30);

        return response()->json($movements);
    }

    // ─── Funcionários (owner only) ─────────────────────────────────────────────
    public function employees(Request $request): JsonResponse
    {
        $user  = $request->user();
        $store = $user->store;
        abort_if(!$store, 403);

        $employees = StoreEmployee::where('store_id', $store->id)
            ->with('user:id,name,email,avatar')
            ->get();

        return response()->json($employees);
    }

    public function addEmployee(Request $request): JsonResponse
    {
        $user  = $request->user();
        $store = $user->store;
        abort_if(!$store, 403);

        $validated = $request->validate([
            'email'       => 'required|email|exists:users,email',
            'role'        => 'required|in:manager,cashier,stock_keeper,viewer',
            'permissions' => 'nullable|array',
            'permissions.*' => 'in:fazer_vendas,gerir_stock,ver_relatorios,gerir_equipa,adicionar_produtos',
        ]);

        $employee = \App\Models\User::where('email', $validated['email'])->first();
        abort_if($employee->id === $user->id, 422, 'Não pode adicionar-se a si próprio.');

        // Permissões: usar as definidas manualmente ou as do role por defeito
        $permissions = !empty($validated['permissions'])
            ? $validated['permissions']
            : StoreEmployee::defaultPermissions($validated['role']);

        // Relatorios e equipa são reservados ao dono — remover se não for o dono a conceder
        // (o dono pode conceder ver_relatorios a qualquer funcionário, mas gerir_equipa não)
        $permissions = array_values(array_diff($permissions, ['gerir_equipa']));

        $emp = StoreEmployee::updateOrCreate(
            ['store_id' => $store->id, 'user_id' => $employee->id],
            [
                'role'        => $validated['role'],
                'permissions' => $permissions,
                'is_active'   => true,
                'added_by'    => $user->id,
            ]
        );

        // Invalidar cache do utilizador adicionado
        Cache::forget("user_me_{$employee->id}");

        return response()->json(['message' => 'Funcionário adicionado.', 'employee' => $emp->load('user:id,name,email')]);
    }

    // ─── Sincronizar produtos criados offline ──────────────────────────────────
    public function syncProducts(Request $request): JsonResponse
    {
        $this->requirePosPermission($request, 'adicionar_produtos');
        $store = $this->resolveStore($request);

        // Verificar se é FormData (com imagem) ou JSON
        $isFormData = $request->hasFile('images');
        $products = $isFormData ? json_decode($request->products, true) : $request->products;

        // Validação básica
        if ($isFormData) {
            $request->validate([
                'products' => 'required|string',
                'images' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // 2MB
            ]);
            $products = json_decode($request->products, true);
        }

        // Validar produtos
        $validator = Validator::make(['products' => $products], [
            'products'                => 'required|array|min:1',
            'products.*.local_id'     => 'required|string',
            'products.*.name'         => 'required|string|max:255',
            'products.*.price'        => 'required|numeric|min:0',
            'products.*.cost_price'   => 'nullable|numeric|min:0',
            'products.*.sku'          => 'nullable|string|max:100',
            'products.*.is_weighable' => 'nullable|boolean',
            'products.*.weight_unit'  => 'nullable|in:g,kg,l,ml,un',
            'products.*.initial_stock'=> 'nullable|integer|min:0',
            'products.*.pos_only'     => 'nullable|boolean',
            'products.*.availability' => 'nullable|in:virtual_store,pos,both',
            'products.*.selling_modes' => 'nullable|array',
            'products.*.selling_modes.*' => 'in:weight,unit',
            'products.*.product_category_id' => 'required|integer|exists:product_categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Dados inválidos.', 'errors' => $validator->errors()], 422);
        }

        $created = 0;
        $map     = []; // local_id → server product_id

        DB::transaction(function () use ($request, $store, $products, $isFormData, &$created, &$map) {
            foreach ($products as $pd) {
                // Evitar duplicados pelo nome + loja
                $productData = [
                    'store_id'    => $store->id,
                    'name'        => $pd['name'],
                    'slug'        => Str::slug($pd['name']) . '-' . uniqid(),
                    'price'       => $pd['price'],
                    'cost_price'  => $pd['cost_price'] ?? 0,
                    'sku'         => $pd['sku'] ?? null,
                    'is_weighable'=> $pd['is_weighable'] ?? false,
                    'weight_unit' => $pd['weight_unit'] ?? 'un',
                    'is_active'   => true,
                    'product_category_id' => $pd['product_category_id'] ?? null,
                ];
                if (Product::hasAvailabilityColumn()) {
                    $productData['availability'] = $pd['availability'] ?? 'both';
                }
                $productData['selling_modes'] = $pd['selling_modes'] ?? ['unit'];

                $product = $store->products()->where('name', $pd['name'])->first()
                    ?? Product::create($productData);

                // Processar imagem se fornecida
                if ($isFormData && $request->hasFile('images')) {
                    $image = $request->file('images');
                    $imageName = 'product_' . $product->id . '_' . time() . '.' . $image->getClientOriginalExtension();
                    $image->storeAs('products', $imageName, 'public');
                    $product->update(['images' => json_encode([$imageName])]);
                }

                // Criar stock inicial
                $stock = \App\Models\ProductStock::firstOrCreate(
                    ['product_id' => $product->id],
                    ['quantity' => 0, 'minimum_stock' => 5, 'unit' => $pd['weight_unit'] ?? 'un']
                );
                if (!empty($pd['initial_stock']) && $pd['initial_stock'] > 0) {
                    $stock->increment('quantity', $pd['initial_stock']);
                    \App\Models\StockMovement::create([
                        'product_id'      => $product->id,
                        'type'            => 'in',
                        'quantity'        => $pd['initial_stock'],
                        'quantity_before' => 0,
                        'quantity_after'  => $pd['initial_stock'],
                        'reason'          => 'Stock inicial (criado no POS offline)',
                        'user_id'         => $request->user()->id,
                    ]);
                }

                $map[$pd['local_id']] = $product->id;
                $created++;
            }
        });

        Cache::forget("pos_products_{$store->id}");

        return response()->json(['created' => $created, 'id_map' => $map]);
    }

    public function removeEmployee(Request $request, StoreEmployee $employee): JsonResponse
    {
        $store = $request->user()->store;
        abort_if(!$store || $employee->store_id !== $store->id, 403);

        $employee->update(['is_active' => false]);
        Cache::forget("user_me_{$employee->user_id}");
        return response()->json(['message' => 'Acesso removido.']);
    }

    // ─── Actualizar role e permissões de um funcionário ───────────────────────
    public function updateEmployee(Request $request, StoreEmployee $employee): JsonResponse
    {
        $store = $request->user()->store;
        abort_if(!$store || $employee->store_id !== $store->id, 403, 'Sem permissão.');

        $validated = $request->validate([
            'role'          => 'required|in:manager,cashier,stock_keeper,viewer',
            'permissions'   => 'required|array',
            'permissions.*' => 'in:fazer_vendas,gerir_stock,ver_relatorios,adicionar_produtos',
        ]);

        $employee->update([
            'role'        => $validated['role'],
            'permissions' => $validated['permissions'],
        ]);

        Cache::forget("user_me_{$employee->user_id}");

        return response()->json([
            'message'  => 'Permissões actualizadas.',
            'employee' => $employee->fresh()->load('user:id,name,email'),
        ]);
    }

    // ─── Criar conta de utilizador + funcionário (sem OTP) ───────────────────
    public function createEmployeeAccount(Request $request): JsonResponse
    {
        $user = $request->user();

        // Verificar se o usuário é store_owner
        if ($user->role !== 'store_owner') {
            return response()->json([
                'message' => 'Apenas proprietários de loja podem criar contas de funcionários.',
            ], 403);
        }

        // Verificar se o usuário tem uma loja associada
        $store = $user->store;
        if (!$store) {
            return response()->json([
                'message' => 'Você precisa ter uma loja cadastrada para adicionar funcionários. Crie sua loja primeiro.',
            ], 403);
        }

        // Verificar se a loja está ativa
        if ($store->status !== 'active') {
            return response()->json([
                'message' => 'Sua loja precisa estar ativa para adicionar funcionários. Aguarde a aprovação da administração.',
            ], 403);
        }

        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|max:255|unique:users,email',
            'password'      => 'required|string|min:6',
            'role'          => 'required|in:manager,cashier,stock_keeper,viewer',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'in:fazer_vendas,gerir_stock,ver_relatorios,gerir_equipa,adicionar_produtos',
        ]);

        $permissions = !empty($validated['permissions'])
            ? $validated['permissions']
            : StoreEmployee::defaultPermissions($validated['role']);
        $permissions = array_values(array_diff($permissions, ['gerir_equipa']));

        // Criar utilizador sem OTP — activo imediatamente
        $newUser = \App\Models\User::create([
            'name'           => $validated['name'],
            'email'          => $validated['email'],
            'password'       => $validated['password'], // hashed pelo cast
            'role'           => 'customer',
            'email_verified' => true,
            'is_active'      => true,
        ]);

        $emp = StoreEmployee::create([
            'store_id'    => $store->id,
            'user_id'     => $newUser->id,
            'role'        => $validated['role'],
            'permissions' => $permissions,
            'is_active'   => true,
            'added_by'    => $user->id,
        ]);

        return response()->json([
            'message'  => 'Conta criada com sucesso.',
            'employee' => $emp->load('user:id,name,email'),
        ], 201);
    }

    // ─── Redefinir senha de um funcionário (pelo dono) ───────────────────────
    public function resetEmployeePassword(Request $request, StoreEmployee $employee): JsonResponse
    {
        $store = $request->user()->store;
        abort_if(!$store || $employee->store_id !== $store->id, 403, 'Sem permissão.');

        $validated = $request->validate([
            'password' => 'required|string|min:6',
        ]);

        $employee->user->update(['password' => $validated['password']]);
        Cache::forget("user_me_{$employee->user_id}");

        return response()->json(['message' => 'Senha redefinida com sucesso.']);
    }

    // ─── Deletar venda (apenas dono da loja) ───────────────────────────────────
    // Reverte stock, decrementa total_sold dos produtos e remove a venda
    public function deleteSale(Request $request, PosSale $sale): JsonResponse
    {
        $store = $this->resolveStore($request);
        $user  = $request->user();

        // Apenas dono da loja ou admin podem deletar vendas
        abort_unless(in_array($user->role, ['store_owner', 'admin']) || ($user->role === 'customer' && $this->userCanDeleteSales($request)), 
            403, 'Apenas o dono da loja pode deletar vendas.');

        // Verificar que a venda pertence à loja do utilizador
        abort_if($sale->store_id !== $store->id, 403, 'Venda não encontrada.');

        try {
            DB::transaction(function () use ($sale) {
                // Reverter stock e total_sold para cada item
                foreach ($sale->items as $item) {
                    if (!empty($item->product_id)) {
                        $stock = ProductStock::where('product_id', $item->product_id)->first();
                        if ($stock) {
                            $before = $stock->quantity;
                            $stock->increment('quantity', $item->quantity);

                            // Registar movimento de stock de reversão
                            StockMovement::create([
                                'product_id'      => $item->product_id,
                                'type'            => 'in',
                                'quantity'        => $item->quantity,
                                'quantity_before' => $before,
                                'quantity_after'  => $before + $item->quantity,
                                'reason'          => 'Reversão - Venda #' . $sale->id . ' deletada',
                                'user_id'         => auth()->id(),
                            ]);
                        }

                        // Decrementar total_sold do produto
                        Product::where('id', $item->product_id)
                            ->decrement('total_sold', $item->quantity);
                    }
                }

                // Deletar itens da venda
                $sale->items()->delete();

                // Deletar a venda
                $sale->delete();
            });

            // Invalidar caches
            Cache::forget("pos_products_{$store->id}");
            Cache::forget('products_flash');
            Cache::forget('products_trending');

            return response()->json([
                'message' => 'Venda deletada com sucesso. Stock revertido.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erro ao deletar venda: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ─── Verificar se utilizador pode deletar vendas ───────────────────────────
    private function userCanDeleteSales(Request $request): bool
    {
        $user = $request->user();
        if (in_array($user->role, ['store_owner', 'admin'])) {
            return true;
        }

        $emp = StoreEmployee::where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (!$emp) {
            return false;
        }

        $permissions = $emp->permissions ?? StoreEmployee::defaultPermissions($emp->role);
        return in_array('deletar_venda', $permissions);
    }

    // ─── Categorias da loja (para filtro na gestão de produtos) ───────────────
    public function categories(Request $request): JsonResponse
    {
        $this->requirePosPermission($request, 'fazer_vendas');
        $store = $this->resolveStore($request);

        $categoryIds = $store->products()
            ->where('is_active', true)
            ->whereNotNull('product_category_id')
            ->pluck('product_category_id')
            ->unique();

        $cats = \App\Models\ProductCategory::whereIn('id', $categoryIds)
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);

        return response()->json($cats);
    }

    // ─── Todos os produtos da loja (activos + inactivos) para gestão ──────────
    public function allStoreProducts(Request $request): JsonResponse
    {
        $this->requirePosPermission($request, 'fazer_vendas');
        $store = $this->resolveStore($request);

        $fields = [
            'id', 'name', 'price', 'cost_price', 'sku', 'barcode', 'images',
            'is_weighable', 'weight_unit', 'product_category_id', 'is_active',
            'description', 'availability', 'selling_modes',
        ];

        $products = $store->products()
            ->with('stock')
            ->orderBy('name')
            ->get($fields)
            ->map(function ($p) {
                $images = $p->images ?? [];
                if (is_string($images)) $images = json_decode($images, true) ?? [];
                $p->images = $images;
                $p->category_id = $p->product_category_id ?? null;
                return $p;
            });

        return response()->json($products);
    }

    // ─── Actualizar produto via POS (owner/manager) ────────────────────────────
    public function updatePosProduct(Request $request, Product $product): JsonResponse
    {
        $this->requirePosPermission($request, 'fazer_vendas');
        $store = $this->resolveStore($request);

        // Garante que o produto pertence à loja
        abort_unless($product->store_id === $store->id, 403, 'Produto não pertence à sua loja.');

        $user = $request->user();
        // Apenas owner/manager podem editar
        if (!in_array($user->role, ['store_owner', 'admin'])) {
            $emp = StoreEmployee::where('user_id', $user->id)->where('is_active', true)->first();
            abort_unless($emp && in_array($emp->role, ['manager']), 403, 'Sem permissão para editar produtos.');
        }

        $data = $request->validate([
            'name'               => 'sometimes|string|max:255',
            'price'              => 'sometimes|numeric|min:0',
            'cost_price'         => 'sometimes|numeric|min:0',
            'sku'                => 'sometimes|nullable|string|max:100',
            'barcode'            => 'sometimes|nullable|string|max:100',
            'description'        => 'sometimes|nullable|string',
            'product_category_id'=> 'sometimes|nullable|integer',
            'is_weighable'       => 'sometimes|boolean',
            'weight_unit'        => 'sometimes|string|in:kg,g,l,ml,un',
            'availability'       => 'sometimes|string|in:pos,virtual_store,both',
            'selling_modes'      => 'sometimes|array',
            'is_active'          => 'sometimes|boolean',
            'reason'             => 'sometimes|nullable|string|max:255',
        ]);

        // Registar histórico de alterações de preço
        foreach (['price', 'cost_price'] as $field) {
            if (isset($data[$field]) && (float)$data[$field] !== (float)$product->$field) {
                \App\Models\PriceHistory::create([
                    'product_id' => $product->id,
                    'changed_by' => $user->id,
                    'field'      => $field,
                    'old_value'  => $product->$field,
                    'new_value'  => $data[$field],
                    'reason'     => $data['reason'] ?? null,
                ]);
            }
        }

        // Registar outras alterações no histórico
        $trackedFields = ['name', 'availability', 'is_active'];
        foreach ($trackedFields as $field) {
            if (isset($data[$field]) && $data[$field] != $product->$field) {
                \App\Models\PriceHistory::create([
                    'product_id' => $product->id,
                    'changed_by' => $user->id,
                    'field'      => $field,
                    'old_value'  => (string)($product->$field ?? ''),
                    'new_value'  => (string)$data[$field],
                    'reason'     => $data['reason'] ?? null,
                ]);
            }
        }

        unset($data['reason']);
        $product->update($data);

        // Invalidar cache de produtos POS
        Cache::forget("pos_products_{$store->id}");

        return response()->json([
            'message' => 'Produto actualizado.',
            'product' => $product->fresh(['stock']),
        ]);
    }

    // ─── Remover produto do POS (owner/manager) — regista no histórico ─────────
    public function deactivatePosProduct(Request $request, Product $product): JsonResponse
    {
        $this->requirePosPermission($request, 'fazer_vendas');
        $store = $this->resolveStore($request);

        abort_unless($product->store_id === $store->id, 403, 'Produto não pertence à sua loja.');

        $user = $request->user();
        if (!in_array($user->role, ['store_owner', 'admin'])) {
            $emp = StoreEmployee::where('user_id', $user->id)->where('is_active', true)->first();
            abort_unless($emp && in_array($emp->role, ['manager']), 403, 'Sem permissão para remover produtos.');
        }

        $reason = $request->input('reason', 'Removido via POS');

        \App\Models\PriceHistory::create([
            'product_id' => $product->id,
            'changed_by' => $user->id,
            'field'      => 'is_active',
            'old_value'  => '1',
            'new_value'  => '0',
            'reason'     => $reason,
        ]);

        $product->update(['is_active' => false]);
        Cache::forget("pos_products_{$store->id}");

        return response()->json(['message' => 'Produto removido do POS.']);
    }

    // ─── Histórico de alterações de um produto (owner/manager) ────────────────
    public function posProductHistory(Request $request, Product $product): JsonResponse
    {
        $this->requirePosPermission($request, 'fazer_vendas');
        $store = $this->resolveStore($request);

        abort_unless($product->store_id === $store->id, 403, 'Produto não pertence à sua loja.');

        $history = \App\Models\PriceHistory::where('product_id', $product->id)
            ->with('changedBy:id,name')
            ->latest()
            ->take(50)
            ->get();

        return response()->json($history);
    }
}
