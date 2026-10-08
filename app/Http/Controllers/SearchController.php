<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function skcl(Request $request)
    {
        $search = $request->search;

        $q = Order::query()
            ->when($search, function ($q) use ($search) {
                $q->where('skcl_no', 'like', "%{$search}%");
            })
            ->limit(20)
            ->get(['id', 'skcl_no']);

        return response()->json($q);
    }
}
