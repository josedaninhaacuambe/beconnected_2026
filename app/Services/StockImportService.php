<?php

namespace App\Services;

use App\Imports\StockImportHandler;
use App\Models\ExternalStockApi;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductStock;
use App\Models\StockImport;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class StockImportService
{
    /**
     * Importar stock de ficheiro Excel ou CSV
     */
    public function importFromFile(
        int $storeId,
        int $userId,
        string $filePath,
        string $source = 'excel',
        array $columnMapping = []
    ): StockImport {
        $import = StockImport::create([
            'store_id' => $storeId,
            'user_id' => $userId,
            'source' => $source,
            'file_path' => $filePath,
            'status' => 'pending',
            'column_mapping' => $columnMapping ?: null,
        ]);

        try {
            $handler = new StockImportHandler($import);
            Excel::import($handler, storage_path('app/public/' . $filePath));
        } catch (\Exception $e) {
            $import->update([
                'status' => 'failed',
                'errors' => ['Erro ao processar ficheiro: ' . $e->getMessage()],
            ]);
            Log::error('Stock import failed', ['import_id' => $import->id, 'error' => $e->getMessage()]);
        }

        return $import->fresh();
    }

    /**
     * Importar stock via JSON (array de produtos)
     * Aceita qualquer estrutura JSON com mapeamento de campos
     */
    public function importFromJson(
        int $storeId,
        int $userId,
        array $products,
        array $fieldMapping = [],
        string $source = 'json'
    ): StockImport {
        $import = StockImport::create([
            'store_id' => $storeId,
            'user_id' => $userId,
            'source' => $source,
            'status' => 'processing',
            'total_rows' => count($products),
            'column_mapping' => $fieldMapping ?: null,
        ]);

        $imported = 0;
        $updated = 0;
        $failed = 0;
        $errors = [];

        foreach ($products as $index => $item) {
            try {
                $mapped = $this->applyFieldMapping($item, $fieldMapping);
                $this->upsertProduct($mapped, $storeId, $userId, $import->id, $source);

                // Determinar se foi criado ou actualizado
                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Item {$index}: " . $e->getMessage();
                $failed++;
            }
        }

        $import->update([
            'status' => 'completed',
            'imported_rows' => $imported,
            'failed_rows' => $failed,
            'errors' => $errors,
            'completed_at' => now(),
        ]);

        return $import->fresh();
    }

    /**
     * Sincronizar stock de uma API externa configurada
     */
    public function syncFromExternalApi(ExternalStockApi $api): StockImport
    {
        $import = StockImport::create([
            'store_id' => $api->store_id,
            'user_id' => $api->store->user_id,
            'source' => 'api_pull',
            'api_endpoint' => $api->endpoint_url,
            'api_headers' => $api->headers,
            'status' => 'processing',
            'column_mapping' => $api->field_mapping,
        ]);

        try {
            $httpRequest = Http::withHeaders($api->headers ?? []);

            $response = $api->method === 'POST'
                ? $httpRequest->post($api->endpoint_url, $api->body_params ?? [])
                : $httpRequest->get($api->endpoint_url, $api->body_params ?? []);

            if (!$response->successful()) {
                throw new \Exception("API retornou erro {$response->status()}: " . $response->body());
            }

            $data = $response->json();

            // Extrair os produtos usando o data_path (ex: "data.products" → $data['data']['products'])
            $products = $this->extractByPath($data, $api->data_path);

            if (!is_array($products) || empty($products)) {
                throw new \Exception("Resposta da API não contém produtos válidos no caminho '{$api->data_path}'.");
            }

            $import->update(['total_rows' => count($products)]);

            $result = $this->importFromJson(
                $api->store_id,
                $api->store->user_id,
                $products,
                $api->field_mapping ?? [],
                'api_pull'
            );

            $api->update(['last_synced_at' => now()]);

            $import->update([
                'status' => 'completed',
                'imported_rows' => $result->imported_rows,
                'updated_rows' => $result->updated_rows,
                'failed_rows' => $result->failed_rows,
                'errors' => $result->errors,
                'completed_at' => now(),
            ]);

        } catch (\Exception $e) {
            $import->update([
                'status' => 'failed',
                'errors' => [$e->getMessage()],
            ]);
            Log::error('External API sync failed', ['api_id' => $api->id, 'error' => $e->getMessage()]);
        }

        return $import->fresh();
    }

    /**
     * Webhook: receber dados de stock em tempo real de sistema externo
     * Qualquer sistema pode fazer POST para /api/store/stock/webhook
     */
    public function processWebhook(int $storeId, array $payload, string $format = 'auto'): array
    {
        // Detectar formato automaticamente
        $products = [];

        if (isset($payload['products'])) {
            $products = $payload['products'];
        } elseif (isset($payload['data'])) {
            $products = is_array($payload['data']) ? $payload['data'] : [$payload['data']];
        } elseif (isset($payload['items'])) {
            $products = $payload['items'];
        } elseif (isset($payload['stock'])) {
            $products = $payload['stock'];
        } elseif (array_keys($payload) === range(0, count($payload) - 1)) {
            // Array directo de produtos
            $products = $payload;
        } else {
            // Payload é um único produto
            $products = [$payload];
        }

        $import = $this->importFromJson(
            $storeId,
            $this->getStoreOwnerId($storeId),
            $products,
            [],
            'api_webhook'
        );

        return [
            'received' => true,
            'products_processed' => $import->imported_rows + $import->updated_rows,
            'failed' => $import->failed_rows,
            'import_id' => $import->id,
        ];
    }

    /**
     * Pré-visualizar colunas de um ficheiro antes de importar
     */
    public function previewFile(string $filePath): array
    {
        $data = Excel::toArray([], storage_path('app/public/' . $filePath));
        $sheet = $data[0] ?? [];

        if (empty($sheet)) {
            return ['headers' => [], 'sample' => []];
        }

        $headers = array_values($sheet[0] ?? []);
        $sample = array_slice($sheet, 1, 3); // 3 linhas de exemplo

        return [
            'headers' => $headers,
            'sample' => $sample,
            'suggested_mapping' => $this->suggestColumnMapping($headers),
        ];
    }

    private function suggestColumnMapping(array $headers): array
    {
        $knownColumns = [
            'name' => ['nome', 'name', 'produto', 'product', 'descricao_curta', 'title'],
            'price' => ['preco', 'price', 'valor', 'preco_venda', 'pvp', 'custo'],
            'stock_quantity' => ['quantidade', 'quantity', 'stock', 'qty', 'estoque', 'saldo'],
            'sku' => ['sku', 'codigo', 'code', 'ref', 'referencia', 'cod_produto'],
            'barcode' => ['barcode', 'codigo_barras', 'ean', 'ean13', 'gtin'],
            'brand' => ['marca', 'brand', 'fabricante'],
            'category' => ['categoria', 'category', 'grupo', 'tipo'],
            'description' => ['descricao', 'description', 'obs', 'observacoes'],
            'unit' => ['unidade', 'unit', 'und', 'medida'],
        ];

        $mapping = [];
        foreach ($headers as $header) {
            $norm = strtolower(trim(str_replace([' ', '-', '/'], '_', $header)));
            foreach ($knownColumns as $field => $variants) {
                if (in_array($norm, $variants)) {
                    $mapping[$header] = $field;
                    break;
                }
            }
        }

        return $mapping;
    }

    private function applyFieldMapping(array $item, array $mapping): array
    {
        if (empty($mapping)) return $item;

        $result = [];
        foreach ($item as $key => $value) {
            $mappedKey = $mapping[$key] ?? $key;
            $result[$mappedKey] = $value;
        }
        return $result;
    }

    private function extractByPath(array $data, ?string $path): array
    {
        if (!$path) return $data;

        $keys = explode('.', $path);
        foreach ($keys as $key) {
            if (!isset($data[$key])) return [];
            $data = $data[$key];
        }

        return is_array($data) ? $data : [];
    }

    private function upsertProduct(array $data, int $storeId, int $userId, int $importId, string $source): void
    {
        $name = $data['name'] ?? $data['nome'] ?? $data['produto'] ?? null;
        if (!$name) throw new \Exception("Nome do produto em falta.");

        $product = null;
        if (!empty($data['sku'])) {
            $product = Product::where('store_id', $storeId)->where('sku', $data['sku'])->first();
        }
        if (!$product && !empty($data['barcode'])) {
            $product = Product::where('store_id', $storeId)->where('barcode', $data['barcode'])->first();
        }
        if (!$product) {
            $product = Product::where('store_id', $storeId)->where('name', $name)->first();
        }

        $categoryId = ProductCategory::firstOrCreate(
            ['slug' => Str::slug($data['category'] ?? 'geral')],
            ['name' => $data['category'] ?? 'Geral', 'is_active' => true]
        )->id;

        $productData = [
            'store_id' => $storeId,
            'product_category_id' => $categoryId,
            'name' => $name,
            'price' => $this->parsePrice($data['price'] ?? $data['preco'] ?? 0),
            'compare_price' => !empty($data['compare_price']) ? $this->parsePrice($data['compare_price']) : null,
            'sku' => $data['sku'] ?? $data['codigo'] ?? null,
            'barcode' => $data['barcode'] ?? $data['codigo_barras'] ?? null,
            'model' => $data['model'] ?? $data['modelo'] ?? null,
            'description' => $data['description'] ?? $data['descricao'] ?? null,
            'is_active' => true,
        ];

        if ($product) {
            $product->update($productData);
        } else {
            $productData['slug'] = Str::slug($name) . '-' . Str::random(6);
            $product = Product::create($productData);
            ProductStock::create(['product_id' => $product->id, 'quantity' => 0, 'unit' => $data['unit'] ?? 'unidade']);
        }

        $qty = $data['stock_quantity'] ?? $data['quantidade'] ?? $data['stock'] ?? $data['qty'] ?? null;
        if ($qty !== null && $qty !== '') {
            $newQty = max(0, (int) $qty);
            $stock = $product->stock ?? ProductStock::firstOrCreate(
                ['product_id' => $product->id],
                ['quantity' => 0, 'unit' => 'unidade']
            );
            $oldQty = $stock->quantity;
            $stock->update(['quantity' => $newQty]);

            StockMovement::create([
                'product_id' => $product->id,
                'type' => 'adjustment',
                'quantity' => abs($newQty - $oldQty),
                'quantity_before' => $oldQty,
                'quantity_after' => $newQty,
                'reason' => "Importação {$source} - Import #{$importId}",
                'user_id' => $userId,
            ]);
        }
    }

    private function parsePrice(mixed $value): float
    {
        if (is_numeric($value)) return (float) $value;
        $value = preg_replace('/[^\d,.]/', '', (string) $value);
        $value = str_replace(',', '.', $value);
        return (float) $value;
    }

    private function getStoreOwnerId(int $storeId): int
    {
        return \App\Models\Store::find($storeId)?->user_id ?? 1;
    }
}
