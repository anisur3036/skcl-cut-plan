<?php

namespace App\Http\Controllers;

use App\Models\MarkerPlan;
use App\Models\MarkerPlanDetail;
use App\Models\Order;
use App\Models\Table;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class MarkerPlanController extends Controller
{
    private const SIZE_ORDER = ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '2XL', '3XL', '4XL'];

    /* ------------------------------------------------------------------ */
    /*  LIST: নতুন আগে (DESC)                                              */
    /* ------------------------------------------------------------------ */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $deleted = trim((string) $request->query('deleted', ''));
        $highlight = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $request->query('highlight', ''))
        )));

        $plans = MarkerPlan::query()
            ->with([
                'cuttingTable:id,name',
                'order:id,skcl_no,style,item_name,color,buyer_id',
                'order.buyer:id,name',
            ])
            ->withSum('details as total_qty', 'quantity')
            ->when($search !== '', function ($q) use ($search) {
                $like = "%{$search}%";

                $q->where(function ($w) use ($like) {
                    $w->where('ref_no', 'like', $like)
                        ->orWhereHas('order', function ($o) use ($like) {
                            $o->where('skcl_no', 'like', $like)
                                ->orWhere('style', 'like', $like)
                                ->orWhere('item_name', 'like', $like)
                                ->orWhere('color', 'like', $like)
                                ->orWhereHas('buyer', fn($b) => $b->where('name', 'like', $like));
                        })
                        ->orWhereHas('cuttingTable', fn($t) => $t->where('name', 'like', $like));
                });
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn(MarkerPlan $p) => [
                'id' => $p->id,
                'ref_no' => $p->ref_no,
                'skcl_no' => $p->order?->skcl_no,
                'buyer' => $p->order?->buyer?->name,
                'style' => $p->order?->style,
                'item_name' => $p->order?->item_name,
                'color_name' => $p->order?->color,
                'table_name' => $p->cuttingTable?->name,
                'fixed_qty' => $p->fixed_qty,
                'status' => $p->status,
                'locked' => $p->isLocked(),
                'total_qty' => (int) ($p->total_qty ?? 0),
                'created_at' => $p->created_at?->format('d M Y H:i'),
            ]);

        return Inertia::render('marker-plan/index', [
            'plans' => $plans,
            'statuses' => $this->statusOptions(),
            'search' => $search,
            'highlight' => $highlight,
            'deleted' => $deleted,
            'error' => session('error'),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  CREATE: SKCL সার্চ + ফাঁকা ফর্ম                                    */
    /* ------------------------------------------------------------------ */
    public function create(Request $request)
    {
        $skclNo = trim((string) $request->query('skcl_no', ''));
        $colorName = trim((string) $request->query('color_name', ''));

        $base = [
            'skclNo' => $skclNo,
            'colorName' => $colorName,
            'tables' => $this->tableOptions(),
            'statuses' => $this->statusOptions(),
            'found' => null,
            'meta' => null,
            'sizes' => [],
            'rows' => [],
        ];

        if ($skclNo === '') {
            return Inertia::render('marker-plan/create', $base);
        }


        $orders = Order::with(['details', 'buyer:id,name'])
            ->where('skcl_no', $skclNo)
            ->when($colorName !== '', fn($q) => $q->where('color', $colorName))
            ->orderBy('item_name')
            ->orderBy('color')
            ->get();

        if ($orders->isEmpty()) {
            return Inertia::render('marker-plan/create', [...$base, 'found' => false]);
        }

        $sizes = $this->sizesFrom($orders->flatMap(fn($o) => $o->details));
        $used = $this->usedMap($orders->pluck('id'));
        $first = $orders->first();

        return Inertia::render('marker-plan/create', [
            ...$base,
            'found' => true,
            'meta' => [
                'file_no' => $first->file_no,
                'style' => $first->style,
                'buyer' => $first->buyer?->name,
            ],
            'sizes' => $sizes,
            'rows' => $this->buildRows($orders, $sizes, collect(), $used),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $orders = Order::with('details')
            ->whereIn('id', collect($data['rows'])->pluck('order_id'))
            ->get()
            ->keyBy('id');

        $refs = $this->retryOnRefClash(
            fn() => DB::transaction(fn() => $this->createPlans($data, $orders))
        );

        return redirect()->route('marker-plans.index', ['highlight' => implode(',', $refs)]);
    }

    /* ------------------------------------------------------------------ */
    /*  EDIT                                                                */
    /* ------------------------------------------------------------------ */
    public function edit(MarkerPlan $markerPlan)
    {
        if ($markerPlan->isLocked()) {
            return $this->lockedResponse($markerPlan);
        }

        $markerPlan->load(['order.details', 'order.buyer']);

        $order = $markerPlan->order;
        $details = $markerPlan->details()->get()->keyBy('size');
        $sizes = $this->sizesFrom($order->details);
        $used = $this->usedMap([$order->id], $markerPlan->id); // এই plan বাদে

        return Inertia::render('marker-plan/edit', [
            'plan' => [
                'id' => $markerPlan->id,
                'ref_no' => $markerPlan->ref_no,
                'skcl_no' => $order->skcl_no,
                'file_no' => $order->file_no,
                'buyer' => $order->buyer?->name,
                'style' => $order->style,
                'item_name' => $order->item_name,
                'color_name' => $order->color,
            ],
            'tableNoId' => $markerPlan->table_no_id,
            'status' => $markerPlan->status,
            'fixedQty' => $markerPlan->fixed_qty !== null ? (string) $markerPlan->fixed_qty : '',
            'ratios' => $this->ratiosFor($sizes, $details),
            'tables' => $this->tableOptions(),
            'statuses' => $this->statusOptions(),
            'sizes' => $sizes,
            'rows' => $this->buildRows(collect([$order]), $sizes, $details, $used),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  UPDATE                                                              */
    /* ------------------------------------------------------------------ */
    public function update(Request $request, MarkerPlan $markerPlan)
    {
        if ($markerPlan->isLocked()) {
            return $this->lockedResponse($markerPlan);
        }

        $data = $this->validated($request);
        $markerPlan->load('order.details');

        $fixedQty = ($data['fixed_qty'] ?? null) === null ? null : (int) $data['fixed_qty'];

        DB::transaction(function () use ($markerPlan, $data, $fixedQty) {
            $details = $this->buildDetails(
                $data['rows'][0],
                $data['ratios'] ?? [],
                $fixedQty,
                $markerPlan->order
            );

            if (!$details) {
                throw ValidationException::withMessages(['rows' => 'কমপক্ষে একটি quantity দিন।']);
            }

            $markerPlan->update([
                'table_no_id' => (int) $data['table_no_id'],
                'fixed_qty' => $fixedQty,
                'status' => $data['status'],
            ]);

            $markerPlan->details()->delete();
            $markerPlan->details()->createMany($details);
        });

        return redirect()->route('marker-plans.index', ['highlight' => $markerPlan->ref_no]);
    }

    /* ------------------------------------------------------------------ */
    /*  SUMMARY: read-only view of created marker plans                    */
    /* ------------------------------------------------------------------ */
    public function summary(Request $request)
    {
        $skclNo    = trim((string) $request->query('skcl_no', ''));
        $colorName = trim((string) $request->query('color_name', ''));

        $base = [
            'skclNo'    => $skclNo,
            'colorName' => $colorName,
            'found'     => null,
            'info'      => null,
            'sizes'     => [],
            'rows'      => [],
            'totals'    => null,
        ];

        if ($skclNo === '') {
            return Inertia::render('marker-plan/summary', $base);
        }

        $orders = Order::with('buyer:id,name')
            ->where('skcl_no', $skclNo)
            ->when($colorName !== '', fn($q) => $q->where('color', $colorName))
            ->orderBy('item_name')
            ->orderBy('color')
            ->get();

        if ($orders->isEmpty()) {
            return Inertia::render('marker-plan/summary', [...$base, 'found' => false]);
        }

        // Only approved marker plans are pulled
        $plans = MarkerPlan::whereIn('order_id', $orders->pluck('id'))
            ->where('status', MarkerPlan::STATUS_APPROVED)
            ->get(['id', 'order_id', 'status']);

        // Approved quantity per order and size
        $agg = MarkerPlanDetail::query()
            ->join('marker_plans', 'marker_plans.id', '=', 'marker_plan_details.marker_plan_id')
            ->whereIn('marker_plans.order_id', $orders->pluck('id'))
            ->where('marker_plans.status', MarkerPlan::STATUS_APPROVED)
            ->select(
                'marker_plans.order_id',
                'marker_plan_details.size',
                DB::raw('SUM(marker_plan_details.quantity) as qty')
            )
            ->groupBy('marker_plans.order_id', 'marker_plan_details.size')
            ->get();

        $sizes      = $this->sizesFrom($agg);
        $cells      = $agg->keyBy(fn($r) => $r->order_id . '|' . $r->size)->map(fn($r) => (int) $r->qty);
        $planCounts = $plans->groupBy('order_id')->map->count();

        $rows = $orders->map(function (Order $o) use ($sizes, $cells, $planCounts) {
            $quantities = [];
            $total      = 0;

            foreach ($sizes as $size) {
                $q                 = (int) $cells->get($o->id . '|' . $size, 0);
                $quantities[$size] = $q;
                $total            += $q;
            }

            return [
                'order_id'   => $o->id,
                'item_name'  => $o->item_name,
                'color_name' => $o->color,
                'plans'      => (int) ($planCounts[$o->id] ?? 0),
                'quantities' => $quantities,
                'total'      => $total,
            ];
        })->values()->all();

        $bySize = [];
        foreach ($sizes as $size) {
            $bySize[$size] = (int) collect($rows)->sum(fn($r) => $r['quantities'][$size]);
        }
        $grand = array_sum($bySize);

        $join = fn($values) => $values->filter()->unique()->implode(', ');

        return Inertia::render('marker-plan/summary', [
            ...$base,
            'found'  => true,
            'info'   => [
                'skcl_no'    => $skclNo,
                'file_no'    => $join($orders->pluck('file_no')),
                'buyer'      => $join($orders->pluck('buyer.name')) ?: null,
                'style'      => $join($orders->pluck('style')),
                'shipment'   => $join($orders->pluck('shipment_date')->filter()->map(fn($d) => $d->format('d M Y'))) ?: null,
                'plan_count' => $plans->count(),
                'total_qty'  => $grand,
            ],
            'sizes'  => $sizes,
            'rows'   => $rows,
            'totals' => ['by_size' => $bySize, 'grand' => $grand],
        ]);
    }
    public function pdf(MarkerPlan $markerPlan)
    {
        $markerPlan->load(['order.buyer', 'cuttingTable']);

        $details = $markerPlan->details()->get()->keyBy('size');
        $sizes = $this->sizesFrom($details);

        $ratios = [];
        $totalRatio = 0;
        foreach ($sizes as $size) {
            $ratio = $details->get($size)?->ratio;
            $ratios[$size] = $ratio;
            $totalRatio += $ratio ?? 0;
        }

        $pdf = Pdf::loadView('pdf.marker-plan', [
            'plan' => $markerPlan,
            'order' => $markerPlan->order,
            'tableName' => $markerPlan->cuttingTable?->name,
            'statusLabel' => MarkerPlan::STATUSES[$markerPlan->status] ?? $markerPlan->status,
            'sizes' => $sizes,
            'ratios' => $ratios,
            'totalRatio' => $totalRatio,
            'totalQty' => (int) $details->sum('quantity'),
            'printedAt' => now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream("marker-plan-{$markerPlan->ref_no}.pdf");
    }

    public function destroy(MarkerPlan $markerPlan)
    {
        if ($markerPlan->isLocked()) {
            return $this->lockedResponse($markerPlan);
        }

        $ref = $markerPlan->ref_no;

        $markerPlan->delete();

        return redirect()->route('marker-plans.index', ['deleted' => $ref]);
    }

    /* ================================================================== */
    /*  Helpers                                                             */
    /* ================================================================== */
    private function validated(Request $request): array
    {
        return $request->validate([
            'table_no_id' => ['required', 'integer', 'exists:tables,id'],
            'status' => ['required', Rule::in(array_keys(MarkerPlan::STATUSES))],
            'fixed_qty' => ['nullable', 'integer', 'min:0'],
            'ratios' => ['nullable', 'array'],
            'ratios.*' => ['nullable', 'integer', 'min:0'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.order_id' => ['required', 'integer', 'exists:orders,id'],
            'rows.*.quantities' => ['required', 'array'],
            'rows.*.quantities.*' => ['nullable', 'integer', 'min:0'],
        ], [
            'table_no_id.required' => 'Select a Table',
            'table_no_id.exists' => 'Select a right Table',
            'status.in' => 'Select a right status',
        ], [
            'fixed_qty' => 'lay quantity',
        ]);
    }

    private function createPlans(array $data, Collection $orders): array
    {
        $nextRef = $this->refGenerator();
        $fixedQty = ($data['fixed_qty'] ?? null) === null ? null : (int) $data['fixed_qty'];
        $ratios = $data['ratios'] ?? [];
        $refs = [];

        foreach ($data['rows'] as $row) {
            $order = $orders->get($row['order_id']);
            if (!$order) {
                continue;
            }

            $details = $this->buildDetails($row, $ratios, $fixedQty, $order);
            if (!$details) {
                continue;
            }

            $plan = MarkerPlan::create([
                'ref_no' => $nextRef(),
                'order_id' => $order->id,
                'table_no_id' => (int) $data['table_no_id'],
                'fixed_qty' => $fixedQty,
                'status' => $data['status'],
            ]);

            $plan->details()->createMany($details);
            $refs[] = $plan->ref_no;
        }

        if (!$refs) {
            throw ValidationException::withMessages(['rows' => 'At least give one quantity']);
        }

        return $refs;
    }

    private function buildDetails(array $row, array $ratios, ?int $fixedQty, Order $order): array
    {
        $details = [];

        foreach ($row['quantities'] as $size => $qty) {
            if ($qty === null || $qty === '') {
                continue;
            }

            $po = $order->details->first(fn($d) => (string) $d->size === (string) $size);
            if (!$po) {
                continue; // not save if order not exists
            }

            $details[] = [
                'size' => $po->size,
                'ratio' => ($fixedQty !== null && isset($ratios[$size])) ? (int) $ratios[$size] : null,
                'quantity' => (int) $qty,
            ];
        }

        return $details;
    }

    /** Size asc will be XS, S, M, L other are in last*/
    private function sizesFrom(Collection $rows): array
    {
        return $rows->pluck('size')->unique()->values()
            ->sortBy(function ($s) {
                $i = array_search(strtoupper((string) $s), self::SIZE_ORDER, true);
                return $i === false ? 999 : $i;
            })->values()->all();
    }

    private function buildRows(Collection $orders, array $sizes, Collection $savedBySize, Collection $used): array
    {
        return $orders->map(function (Order $order) use ($sizes, $savedBySize, $used) {
            $poBySize = $order->details->keyBy('size');

            $quantities = [];
            $poQty = [];
            $usedQty = [];

            foreach ($sizes as $size) {
                $d = $savedBySize->get($size);

                $quantities[$size] = $d ? (string) $d->quantity : '';
                $poQty[$size] = (int) ($poBySize->get($size)?->quantity ?? 0);
                $usedQty[$size] = (int) $used->get($order->id . '|' . $size, 0);
            }

            return [
                'order_id' => $order->id,
                'item_name' => $order->item_name,
                'color_name' => $order->color,
                'available' => $order->details->pluck('size')->all(),
                'quantities' => $quantities,
                'po_quantities' => $poQty,
                'used_quantities' => $usedQty,
            ];
        })->values()->all();
    }

    private function ratiosFor(array $sizes, Collection $details): array
    {
        $out = [];

        foreach ($sizes as $size) {
            $d = $details->get($size);
            $out[$size] = ($d && $d->ratio !== null) ? (string) $d->ratio : '1';
        }

        return $out;
    }

    private function usedMap(Collection|array $orderIds, ?int $exceptPlanId = null): Collection
    {
        return MarkerPlanDetail::query()
            ->join('marker_plans', 'marker_plans.id', '=', 'marker_plan_details.marker_plan_id')
            ->whereIn('marker_plans.order_id', $orderIds)
            ->where('marker_plans.status', '!=', MarkerPlan::STATUS_CANCELLED)
            ->when($exceptPlanId !== null, fn($q) => $q->where('marker_plans.id', '!=', $exceptPlanId))
            ->select(
                'marker_plans.order_id',
                'marker_plan_details.size',
                DB::raw('SUM(marker_plan_details.quantity) as used_qty')
            )
            ->groupBy('marker_plans.order_id', 'marker_plan_details.size')
            ->get()
            ->keyBy(fn($r) => $r->order_id . '|' . $r->size)
            ->map(fn($r) => (int) $r->used_qty);
    }

    private function refGenerator(): Closure
    {
        $prefix = 'REF-' . now()->format('Ymd') . '-';

        $last = MarkerPlan::where('ref_no', 'like', $prefix . '%')
            ->orderByDesc('ref_no')
            ->value('ref_no');

        $n = $last ? (int) substr($last, -4) : 0;

        return function () use ($prefix, &$n) {
            return $prefix . str_pad((string) ++$n, 4, '0', STR_PAD_LEFT);
        };
    }

    private function retryOnRefClash(Closure $callback): mixed
    {
        for ($attempt = 1;; $attempt++) {
            try {
                return $callback();
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= 3) {
                    throw $e;
                }
            }
        }
    }

    private function lockedResponse(MarkerPlan $plan)
    {
        return redirect()->route('marker-plans.index')->with(
            'error',
            "Ref {$plan->ref_no} Approved, so will not delete or edit"
        );
    }

    private function tableOptions(): array
    {
        return Table::orderBy('name')->get(['id', 'name'])->all();
    }

    private function statusOptions(): array
    {
        return collect(MarkerPlan::STATUSES)
            ->map(fn($label, $value) => [
                'value' => $value,
                'label' => $label,
                'locked' => in_array($value, MarkerPlan::LOCKED_STATUSES, true),
            ])
            ->values()
            ->all();
    }
}
