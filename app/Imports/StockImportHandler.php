<?php

namespace App\Imports;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductStock;
use App\Models\StockImport;
use App\Models\StockMovement;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Collection;

class StockImportHandler implements ToCollection, WithHeadingRow
{
    private StockImport $import;
    private int $imported = 0;
    private int $updated = 0;
    private int $failed = 0;
    private array $errors = [];

    // Mapeamento padrão de colunas (nome no ficheiro => campo interno)
    private array $defaultMapping = [
        'nome' => 'name',
        'name' => 'name',
        'produto' => 'name',
        'product' => 'name',
        'descricao' => 'description',
        'description' => 'description',
        'preco' => 'price',
        'price' => 'price',
        'preco_venda' => 'price',
        'valor' => 'price',
        'quantidade' => 'stock_quantity',
        'quantity' => 'stock_quantity',
        'stock' => 'stock_quantity',
        'qty' => 'stock_quantity',
        'unidade' => 'unit',
        'unit' => 'unit',
        'sku' => 'sku',
        'codigo' => 'sku',
        'code' => 'sku',
        'codigo_barras' => 'barcode',
        'barcode' => 'barcode',
        'marca' => 'brand',
        'brand' => 'brand',
        'modelo' => 'model',
        'model' => 'model',
        'categoria' => 'category',
        'category' => 'category',
        'preco_comparacao' => 'compare_price',
        'preco_original' => 'compare_price',
        'compare_price' => 'compare_price',
    ];

    public function __construct(StockImport $import)
    {
        $this->import = $import;
    }

    public function collection(Collection $rows): void
    {
        $mapping = $this->import->column_mapping ?? $this->defaultMapping;
        $storeId = $this->import->store_id;
        $userId = $this->import->user_id;

        $this->import->update([
            'status' => 'processing',
            'total_rows' => $rows->count(),
        ]);

        foreach ($rows as $index => $row) {
            try {
                $data = $this->mapRow($row->toArray(), $mapping);

                if (empty($data['name'])) {
                    $this->errors[] = "Linha " . ($index + 2) . ": Nome do produto em falta.";
                    $this->failed++;
                    continue;
                }

                $this->upsertProduct($data, $storeId, $userId);

            } catch (\Exception $e) {
                $this->errors[] = "Linha " . ($index + 2) . ": " . $e->getMessage();
                $this->failed++;
            }
        }

        $this->import->update([
            'status' => 'completed',
            'imported_rows' => $this->imported,
            'updated_rows' => $this->updated,
            'failed_rows' => $this->failed,
            'errors' => $this->errors,
            'completed_at' => now(),
        ]);
    }

    private function mapRow(array $row, array $mapping): array
    {
        $result = [];
        foreach ($row as $column => $value) {
            $normalizedCol = strtolower(trim(str_replace([' ', '-', '/'], '_', $column)));
            $field = $mapping[$normalizedCol] ?? $this->defaultMapping[$normalizedCol] ?? null;
            if ($field) {
                $result[$field] = $value;
            }
        }
        return $result;
    }

    private function upsertProduct(array $data, int $storeId, int $userId): void
    {
        // Procurar produto existente por SKU ou nome
        $product = null;
        if (!empty($data['sku'])) {
            $product = Product::where('store_id', $storeId)->where('sku', $data['sku'])->first();
        }
        if (!$product && !empty($data['barcode'])) {
            $product = Product::where('store_id', $storeId)->where('barcode', $data['barcode'])->first();
        }
        if (!$product) {
            $product = Product::where('store_id', $storeId)->where('name', $data['name'])->first();
        }

        // Resolver categoria
        $categoryId = $this->resolveCategory($data['category'] ?? null);

        // Resolver marca
        $brandId = $this->resolveBrand($data['brand'] ?? null);

        $productData = [
            'store_id' => $storeId,
            'product_category_id' => $categoryId,
            'brand_id' => $brandId,
            'name' => $data['name'],
            'price' => $this->parsePrice($data['price'] ?? 0),
            'compare_price' => !empty($data['compare_price']) ? $this->parsePrice($data['compare_price']) : null,
            'sku' => $data['sku'] ?? null,
            'barcode' => $data['barcode'] ?? null,
            'model' => $data['model'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active' => true,
        ];

        if ($product) {
            // Actualizar produto existente
            $product->update($productData);
            $this->updated++;
        } else {
            // Criar novo produto
            $productData['slug'] = Str::slug($data['name']) . '-' . Str::random(6);
            $product = Product::create($productData);

            // Criar stock inicial
            ProductStock::create([
                'product_id' => $product->id,
                'quantity' => 0,
                'unit' => $data['unit'] ?? 'unidade',
            ]);

            $this->imported++;
        }

        // Actualizar stock se fornecido
        if (isset($data['stock_quantity']) && $data['stock_quantity'] !== null && $data['stock_quantity'] !== '') {
            $newQty = max(0, (int) $data['stock_quantity']);
            $stock = $product->stock ?? ProductStock::firstOrCreate(
                ['product_id' => $product->id],
                ['quantity' => 0, 'unit' => $data['unit'] ?? 'unidade']
            );

            $oldQty = $stock->quantity;
            $stock->update(['quantity' => $newQty]);

            StockMovement::create([
                'product_id' => $product->id,
                'type' => 'adjustment',
                'quantity' => abs($newQty - $oldQty),
                'quantity_before' => $oldQty,
                'quantity_after' => $newQty,
                'reason' => 'Importação de stock - ' . $this->import->source,
                'user_id' => $userId,
            ]);
        }
    }

    private function resolveCategory(?string $name): int
    {
        if (!$name) {
            return ProductCategory::firstOrCreate(
                ['slug' => 'geral'],
                ['name' => 'Geral', 'is_active' => true]
            )->id;
        }

        return ProductCategory::firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name, 'is_active' => true]
        )->id;
    }

    private function resolveBrand(?string $name): ?int
    {
        if (!$name) return null;

        return Brand::firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name]
        )->id;
    }

    private function parsePrice(mixed $value): float
    {
        if (is_numeric($value)) return (float) $value;
        // Remover símbolos de moeda e espaços: "1.500,00 MT" → 1500.00
        $value = preg_replace('/[^\d,.]/', '', (string) $value);
        $value = str_replace(',', '.', $value);
        return (float) $value;
    }
}
