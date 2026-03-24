<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Neighborhood;
use App\Models\Province;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function provinces(): JsonResponse
    {
        return response()->json(Province::orderBy('name')->get());
    }

    public function cities(Request $request): JsonResponse
    {
        $query = City::with('province')->orderBy('name');

        if ($request->province_id) {
            $query->where('province_id', $request->province_id);
        }

        return response()->json($query->get());
    }

    public function neighborhoods(Request $request): JsonResponse
    {
        $query = Neighborhood::with('city')->orderBy('name');

        if ($request->city_id) {
            $query->where('city_id', $request->city_id);
        }

        return response()->json($query->get());
    }
}
