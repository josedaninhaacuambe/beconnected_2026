<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\StoreOrder;
use App\Models\StoreEmployee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PosController extends Controller
{
    // Middleware: verifica se o utilizador pertence à loja (owner ou employee activo)
    private function resolveStore(Request $request)
    {
        $user  = $request->user();
        $store = $user->store; // store_owner

        if (!$store) {
            // pode ser funcionário
            $emp = StoreEmployee::where('user_id', $user->id)
                ->where('is_active', true)
                ->with('store')
                ->first();
            $store = $emp?->store;
        }

        abort_if(!$store, 403, 'Sem acesso a nenhuma loja.');
        return $store;
    }

    // ─── Produtos para o terminal POS ─────────────────────────────────────────
    public function products(Request $request): JsonResponse
    {
        $store = $this->resolveStore($request);

        $products = $store->products()
            ->with('stock')
            ->where('is_active', true)
            ->when($request->search, fn($q) => $q->where(function ($q2) use ($request) {
                $q2->where('name', 'like', '%' . $request->search . '%')
                   ->orWhere('sku', 'like', '%' . $request->search . '%')
                   ->orWhere('barcode', 'like', '%' . $request->search . '%');
            }))
            ->orderBy('name')
            ->get(['id', 'name', 'price', 'sku', 'barcode', 'image']);

        return response()->json($products);
    }

    // ─── Registar venda (single ou batch sync offline) ─────────────────────────
    public function sync(Request $request): JsonResponse
    {
        $store = $this->resolveStore($request);

        $request->validate([
            'sales'                     => 'required|array|min:1',
            'sales.*.local_id'          => 'required|string',
            'sales.*.total'             => 'required|numeric|min:0',
            'sales.*.payment_method'    => 'required|in:cash,mpesa,emola,credit',
            'sales.*.sale_at'           => 'required|date',
            'sales.*.items'             => 'required|array|min:1',
            'sales.*.items.*.product_id'   => 'nullable|integer',
            'sales.*.items.*.product_name' => 'required|string',
            'sales.*.items.*.unit_price'   => 'required|numeric|min:0',
            'sales.*.items.*.quantity'     => 'required|integer|min:1',
            'sales.*.items.*.subtotal'     => 'required|numeric|min:0',
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
                    'subtotal'       => $saleData['subtotal']        ?? $saleData['total'],
                    'discount'       => $saleData['discount']        ?? 0,
                    'total'          => $saleData['total'],
                    'payment_method' => $saleData['payment_method'],
                    'customer_name'  => $saleData['customer_name']   ?? null,
                    'customer_phone' => $saleData['customer_phone']  ?? null,
                    'notes'          => $saleData['notes']           ?? null,
                    'synced'         => true,
                    'sale_at'        => $saleData['sale_at'],
                ]);

                foreach ($saleData['items'] as $item) {
                    PosSaleItem::create([
                        'pos_sale_id'  => $sale->id,
                        'product_id'   => $item['product_id']   ?? null,
                        'product_name' => $item['product_name'],
                        'product_sku'  => $item['product_sku']  ?? null,
                        'unit_price'   => $item['unit_price'],
                        'quantity'     => $item['quantity'],
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
        }

        return response()->json(['synced' => $created, 'skipped' => $skipped]);
    }

    // ─── Movimento de stock (entrada / saída / ajuste) ─────────────────────────
    public function stockMovement(Request $request): JsonResponse
    {
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

        return response()->json(['message' => 'Movimento registado.', 'stock' => $stock->fresh()]);
    }

    // ─── Relatórios unificados POS + Online ───────────────────────────────────
    public function reports(Request $request): JsonResponse
    {
        $store = $this->resolveStore($request);

        $from = $request->from ? now()->parse($request->from)->startOfDay() : now()->startOfMonth();
        $to   = $request->to   ? now()->parse($request->to)->endOfDay()     : now()->endOfDay();

        // ── Vendas POS ────────────────────────────────────────────────────────
        $posSales = PosSale::where('store_id', $store->id)
            ->whereBetween('sale_at', [$from, $to])
            ->with(['items', 'user:id,name'])
            ->orderByDesc('sale_at')
            ->get();

        // ── Vendas Online (store_orders desta loja) ────────────────────────────
        $onlineOrders = StoreOrder::where('store_id', $store->id)
            ->whereNotIn('status', ['cancelled'])
            ->whereBetween('created_at', [$from, $to])
            ->with(['items.product:id,name', 'order:id,payment_method,created_at'])
            ->get();

        // ── Totais unificados ─────────────────────────────────────────────────
        $posRevenue    = $posSales->sum('total');
        $onlineRevenue = $onlineOrders->sum('subtotal');
        $totalRevenue  = $posRevenue + $onlineRevenue;
        $totalSales    = $posSales->count() + $onlineOrders->count();
        $avgTicket     = $totalSales > 0 ? $totalRevenue / $totalSales : 0;

        // ── Por dia (POS + Online juntos) ──────────────────────────────────────
        $dayMap = [];
        foreach ($posSales as $s) {
            $d = $s->sale_at->format('Y-m-d');
            $dayMap[$d] = ($dayMap[$d] ?? ['date' => $s->sale_at->format('d/m'), 'total' => 0, 'count' => 0, 'pos' => 0, 'online' => 0]);
            $dayMap[$d]['total'] += $s->total;
            $dayMap[$d]['pos']   += $s->total;
            $dayMap[$d]['count']++;
        }
        foreach ($onlineOrders as $o) {
            $d = $o->created_at->format('Y-m-d');
            $dayMap[$d] = ($dayMap[$d] ?? ['date' => $o->created_at->format('d/m'), 'total' => 0, 'count' => 0, 'pos' => 0, 'online' => 0]);
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
        $prodMap = [];
        $posItems = PosSaleItem::whereIn('pos_sale_id', $posSales->pluck('id'))
            ->selectRaw('product_name, SUM(quantity) as qty, SUM(subtotal) as revenue')
            ->groupBy('product_name')->get();
        foreach ($posItems as $p) {
            $prodMap[$p->product_name] = ['product_name' => $p->product_name, 'qty' => $p->qty, 'revenue' => $p->revenue];
        }
        foreach ($onlineOrders as $o) {
            foreach ($o->items as $item) {
                $name = $item->product?->name ?? $item->product_name ?? 'Produto';
                $prodMap[$name] = ($prodMap[$name] ?? ['product_name' => $name, 'qty' => 0, 'revenue' => 0]);
                $prodMap[$name]['qty']     += $item->quantity;
                $prodMap[$name]['revenue'] += $item->subtotal;
            }
        }
        usort($prodMap, fn($a, $b) => $b['revenue'] <=> $a['revenue']);
        $topProducts = array_slice($prodMap, 0, 10);

        // ── Por vendedor (POS) ─────────────────────────────────────────────────
        $bySeller = $posSales->groupBy('user_id')
            ->map(fn($g) => ['name' => $g->first()->user?->name ?? 'Desconhecido', 'total' => $g->sum('total'), 'count' => $g->count()])
            ->values();

        return response()->json([
            'summary'      => compact('totalRevenue', 'totalSales', 'avgTicket', 'posRevenue', 'onlineRevenue'),
            'by_day'       => $byDay,
            'by_payment'   => $byPayment,
            'top_products' => $topProducts,
            'by_seller'    => $bySeller,
            'pos_sales'    => $posSales->take(50),
            'online_orders'=> $onlineOrders->take(50),
        ]);
    }

    // ─── Listar stock da loja ──────────────────────────────────────────────────
    public function stock(Request $request): JsonResponse
    {
        $store = $this->resolveStore($request);

        $products = $store->products()
            ->with('stock')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'barcode', 'price', 'image']);

        return response()->json($products);
    }

    // ─── Histórico de movimentos ───────────────────────────────────────────────
    public function stockHistory(Request $request): JsonResponse
    {
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
            'email' => 'required|email|exists:users,email',
            'role'  => 'required|in:manager,cashier,stock_keeper,viewer',
        ]);

        $employee = \App\Models\User::where('email', $validated['email'])->first();

        $emp = StoreEmployee::firstOrCreate(
            ['store_id' => $store->id, 'user_id' => $employee->id],
            [
                'role'        => $validated['role'],
                'permissions' => StoreEmployee::defaultPermissions($validated['role']),
                'is_active'   => true,
                'added_by'    => $user->id,
            ]
        );

        $emp->update(['role' => $validated['role'], 'is_active' => true]);

        return response()->json(['message' => 'Funcionário adicionado.', 'employee' => $emp->load('user:id,name,email')]);
    }

    public function removeEmployee(Request $request, StoreEmployee $employee): JsonResponse
    {
        $store = $request->user()->store;
        abort_if(!$store || $employee->store_id !== $store->id, 403);

        $employee->update(['is_active' => false]);
        return response()->json(['message' => 'Acesso removido.']);
    }
}
