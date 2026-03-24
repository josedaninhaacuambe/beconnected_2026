<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreSection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreSectionController extends Controller
{
    // Listagem pública das secções de uma loja
    public function publicIndex(string $storeSlug): JsonResponse
    {
        $store = Store::where('slug', $storeSlug)->where('status', 'active')->firstOrFail();

        $sections = StoreSection::where('store_id', $store->id)
            ->where('is_active', true)
            ->withCount('products')
            ->orderBy('sort_order')
            ->get();

        return response()->json($sections);
    }

    // Listar secções da minha loja (dono autenticado)
    public function index(Request $request): JsonResponse
    {
        $store = Store::where('user_id', $request->user()->id)->firstOrFail();

        $sections = StoreSection::where('store_id', $store->id)
            ->withCount('products')
            ->orderBy('sort_order')
            ->get();

        return response()->json($sections);
    }

    // Criar secção
    public function store(Request $request): JsonResponse
    {
        $storeModel = Store::where('user_id', $request->user()->id)->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'icon' => 'nullable|string|max:10',
            'sort_order' => 'nullable|integer',
        ]);

        $section = StoreSection::create([
            'store_id' => $storeModel->id,
            'name' => $validated['name'],
            'icon' => $validated['icon'] ?? '📦',
            'sort_order' => $validated['sort_order'] ?? StoreSection::where('store_id', $storeModel->id)->max('sort_order') + 1,
        ]);

        return response()->json($section->loadCount('products'), 201);
    }

    // Actualizar secção
    public function update(Request $request, StoreSection $section): JsonResponse
    {
        $store = Store::where('user_id', $request->user()->id)->firstOrFail();
        abort_if($section->store_id !== $store->id, 403);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'icon' => 'nullable|string|max:10',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $section->update($validated);

        return response()->json($section->loadCount('products'));
    }

    // Eliminar secção
    public function destroy(Request $request, StoreSection $section): JsonResponse
    {
        $store = Store::where('user_id', $request->user()->id)->firstOrFail();
        abort_if($section->store_id !== $store->id, 403);

        // Desassociar produtos desta secção
        $section->products()->update(['store_section_id' => null]);
        $section->delete();

        return response()->json(['message' => 'Secção eliminada.']);
    }

    // Reordenar secções
    public function reorder(Request $request): JsonResponse
    {
        $request->validate(['order' => 'required|array', 'order.*.id' => 'required|integer', 'order.*.sort_order' => 'required|integer']);
        $store = Store::where('user_id', $request->user()->id)->firstOrFail();

        foreach ($request->order as $item) {
            StoreSection::where('id', $item['id'])->where('store_id', $store->id)
                ->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['message' => 'Ordem actualizada.']);
    }
}
