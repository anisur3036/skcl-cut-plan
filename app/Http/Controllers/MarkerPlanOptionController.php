<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarkerPlanOptionController extends Controller
{
    /**
     * GET /marker-plan-options/skcl?limit=20&search=abc
     * Distinct SKCL numbers, most recent order first.
     */
    public function skcl(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $limit  = min(max((int) $request->query('limit', 20), 1), 100);

        $options = Order::query()
            ->when($search !== '', fn ($q) => $q->where('skcl_no', 'like', "%{$search}%"))
            ->groupBy('skcl_no')
            ->orderByDesc(DB::raw('MAX(id)'))
            ->limit($limit)
            ->pluck('skcl_no')
            ->map(fn ($skcl) => ['id' => $skcl, 'name' => $skcl])
            ->values();

        return response()->json($options);
    }

    /**
     * GET /marker-plan-options/skcl/{skcl}
     * Single lookup used by the component when the selected value is not in the first page of options.
     */
    public function skclShow(string $skcl): JsonResponse
    {
        abort_unless(Order::where('skcl_no', $skcl)->exists(), 404);

        return response()->json(['id' => $skcl, 'name' => $skcl]);
    }

    /**
     * GET /marker-plan-options/colors/{skcl}?search=abc
     * All colors of one SKCL. The list is small, so the "limit" parameter is intentionally ignored.
     */
    public function colors(Request $request, string $skcl): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $options = Order::where('skcl_no', $skcl)
            ->when($search !== '', fn ($q) => $q->where('color', 'like', "%{$search}%"))
            ->distinct()
            ->orderBy('color')
            ->pluck('color')
            ->map(fn ($color) => ['id' => $color, 'name' => $color])
            ->values();

        return response()->json($options);
    }
}
