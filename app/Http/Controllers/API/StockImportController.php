<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ExternalStockApi;
use App\Models\Store;
use App\Models\StockImport;
use App\Models\User;
use App\Services\StockImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class StockImportController extends Controller
{
    public function __construct(private StockImportService $importService) {}

    /**
     * Pré-visualizar ficheiro antes de importar (mostra colunas e sugestões)
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $path = $request->file('file')->store('imports/temp', 'public');
        $preview = $this->importService->previewFile($path);

        return response()->json([
            'file_path' => $path,
            'headers' => $preview['headers'],
            'sample_rows' => $preview['sample'],
            'suggested_mapping' => $preview['suggested_mapping'],
        ]);
    }

    /**
     * Importar de Excel/CSV
     */
    public function importFile(Request $request): JsonResponse
    {
        $store = Store::where('user_id', $request->user()->id)->firstOrFail();

        $request->validate([
            'file' => 'required_without:file_path|file|mimes:xlsx,xls,csv|max:10240',
            'file_path' => 'required_without:file|string',
            'column_mapping' => 'nullable|array',
        ]);

        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('imports', 'public');
            $source = str_ends_with($filePath, '.csv') ? 'csv' : 'excel';
        } else {
            $filePath = $request->file_path;
            $source = 'excel';
        }

        $import = $this->importService->importFromFile(
            $store->id,
            $request->user()->id,
            $filePath,
            $source,
            $request->column_mapping ?? []
        );

        return response()->json([
            'message' => "Importação concluída: {$import->imported_rows} criados, {$import->updated_rows} actualizados, {$import->failed_rows} falhas.",
            'import' => $import,
        ]);
    }

    /**
     * Importar via JSON (de qualquer sistema externo)
     */
    public function importJson(Request $request): JsonResponse
    {
        $store = Store::where('user_id', $request->user()->id)->firstOrFail();

        $request->validate([
            'products' => 'required|array|min:1',
            'field_mapping' => 'nullable|array',
        ]);

        $import = $this->importService->importFromJson(
            $store->id,
            $request->user()->id,
            $request->products,
            $request->field_mapping ?? []
        );

        return response()->json([
            'message' => "Importação JSON concluída: {$import->imported_rows} criados, {$import->updated_rows} actualizados.",
            'import' => $import,
        ]);
    }

    /**
     * Configurar API externa para sincronização automática
     */
    public function configureExternalApi(Request $request): JsonResponse
    {
        $store = Store::where('user_id', $request->user()->id)->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'endpoint_url' => 'required|url',
            'method' => 'in:GET,POST',
            'headers' => 'nullable|array',
            'body_params' => 'nullable|array',
            'data_path' => 'nullable|string',
            'field_mapping' => 'nullable|array',
            'auto_sync' => 'boolean',
            'sync_interval_minutes' => 'nullable|integer|min:5',
        ]);

        $api = ExternalStockApi::updateOrCreate(
            ['store_id' => $store->id, 'name' => $validated['name']],
            [...$validated, 'store_id' => $store->id, 'is_active' => true]
        );

        return response()->json([
            'message' => 'API externa configurada com sucesso.',
            'api' => $api,
        ]);
    }

    /**
     * Testar conexão com API externa antes de guardar
     */
    public function testExternalApi(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint_url' => 'required|url',
            'method' => 'in:GET,POST',
            'headers' => 'nullable|array',
            'body_params' => 'nullable|array',
            'data_path' => 'nullable|string',
        ]);

        try {
            $http = Http::withHeaders($request->headers ?? [])->timeout(10);

            $response = $request->method === 'POST'
                ? $http->post($request->endpoint_url, $request->body_params ?? [])
                : $http->get($request->endpoint_url, $request->body_params ?? []);

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => "API retornou erro {$response->status()}",
                    'status_code' => $response->status(),
                ]);
            }

            $data = $response->json();
            $products = $data;

            if ($request->data_path) {
                foreach (explode('.', $request->data_path) as $key) {
                    $products = $products[$key] ?? null;
                }
            }

            $count = is_array($products) ? count($products) : 0;
            $sample = is_array($products) ? array_slice($products, 0, 2) : [];

            return response()->json([
                'success' => true,
                'message' => "Conexão bem-sucedida. {$count} produto(s) encontrado(s).",
                'products_found' => $count,
                'sample' => $sample,
                'detected_fields' => is_array($products[0] ?? null) ? array_keys($products[0]) : [],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de conexão: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Sincronizar agora com API externa configurada
     */
    public function syncNow(Request $request, ExternalStockApi $api): JsonResponse
    {
        $store = Store::where('user_id', $request->user()->id)->firstOrFail();

        if ($api->store_id !== $store->id) {
            return response()->json(['message' => 'Não autorizado.'], 403);
        }

        $import = $this->importService->syncFromExternalApi($api);

        return response()->json([
            'message' => "Sincronização concluída: {$import->imported_rows} criados, {$import->updated_rows} actualizados.",
            'import' => $import,
            'synced_at' => now()->toISOString(),
        ]);
    }

    /**
     * WEBHOOK PÚBLICO — sistemas externos enviam stock aqui
     * Ex: POST /api/store/{token}/stock/webhook
     * Qualquer sistema (PHP, Python, Java, etc.) pode usar isto
     */
    public function webhook(Request $request, string $storeToken): JsonResponse
    {
        // Token é o slug da loja como chave simples de acesso
        $store = Store::where('slug', $storeToken)
            ->orWhere('id', $storeToken)
            ->where('status', 'active')
            ->first();

        if (!$store) {
            return response()->json(['error' => 'Loja não encontrada ou inactiva.'], 404);
        }

        // Verificar API key no header (opcional mas recomendado)
        $apiKey = $request->header('X-Beconnect-Key') ?? $request->query('api_key');
        // Por agora aceitamos sem validação; em produção validar contra chave guardada na loja

        $payload = $request->all();

        if (empty($payload)) {
            return response()->json(['error' => 'Payload vazio.'], 422);
        }

        $result = $this->importService->processWebhook($store->id, $payload);

        return response()->json($result);
    }

    /**
     * Listar APIs externas configuradas
     */
    public function listExternalApis(Request $request): JsonResponse
    {
        $store = Store::where('user_id', $request->user()->id)->firstOrFail();
        return response()->json(ExternalStockApi::where('store_id', $store->id)->get());
    }

    /**
     * Histórico de importações
     */
    public function importHistory(Request $request): JsonResponse
    {
        $store = Store::where('user_id', $request->user()->id)->firstOrFail();

        $imports = StockImport::where('store_id', $store->id)
            ->latest()
            ->paginate(20);

        return response()->json($imports);
    }

    /**
     * Funcionários — o dono da loja gere a sua equipa
     */
    public function addEmployee(Request $request): JsonResponse
    {
        $store = Store::where('user_id', $request->user()->id)->firstOrFail();

        $validated = $request->validate([
            'name'  => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'role'  => 'required|in:manager,cashier,stock_keeper,viewer',
        ]);

        $isNew = false;
        $tempPassword = null;

        // Criar utilizador se não existir
        $user = User::where('email', $validated['email'])->first();
        if (!$user) {
            $tempPassword = Str::random(10) . '!1';
            $user = User::create([
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'password' => Hash::make($tempPassword),
                'role'     => 'customer',
            ]);
            $isNew = true;
        } else {
            // Actualizar nome se fornecido e diferente
            if ($user->name !== $validated['name']) {
                $user->update(['name' => $validated['name']]);
            }
        }

        $storeEmployee = \App\Models\StoreEmployee::updateOrCreate(
            ['store_id' => $store->id, 'user_id' => $user->id],
            [
                'role'        => $validated['role'],
                'permissions' => \App\Models\StoreEmployee::defaultPermissions($validated['role']),
                'is_active'   => true,
                'added_by'    => $request->user()->id,
            ]
        );

        $message = $isNew
            ? "Conta criada e '{$user->name}' adicionado(a) como {$validated['role']}."
            : "'{$user->name}' adicionado(a) como {$validated['role']}.";

        return response()->json([
            'message'       => $message,
            'employee'      => $storeEmployee->load('user'),
            'is_new_user'   => $isNew,
            'temp_password' => $tempPassword,
        ], 201);
    }

    public function listEmployees(Request $request): JsonResponse
    {
        $store = Store::where('user_id', $request->user()->id)->firstOrFail();

        return response()->json(
            \App\Models\StoreEmployee::with('user')
                ->where('store_id', $store->id)
                ->where('is_active', true)
                ->get()
        );
    }

    public function removeEmployee(Request $request, \App\Models\StoreEmployee $employee): JsonResponse
    {
        $store = Store::where('user_id', $request->user()->id)->firstOrFail();

        if ($employee->store_id !== $store->id) {
            return response()->json(['message' => 'Não autorizado.'], 403);
        }

        $employee->update(['is_active' => false]);
        return response()->json(['message' => 'Funcionário removido.']);
    }
}
