<?php

namespace App\Http\Controllers;

use App\Models\MarkerPlan;
use App\Models\MarkerPlanDetail;
use App\Models\Order;
use App\Models\Table;
use Barryvdh\DomPDF\Facade\Pdf;
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
        $search    = trim((string) $request->query('search', ''));
        $deleted   = trim((string) $request->query('deleted', ''));
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
                'id'         => $p->id,
                'ref_no'     => $p->ref_no,
                'skcl_no'    => $p->order?->skcl_no,
                'buyer'      => $p->order?->buyer?->name,
                'style'      => $p->order?->style,
                'item_name'  => $p->order?->item_name,
                'color_name' => $p->order?->color,
                'table_name' => $p->cuttingTable?->name,
                'fixed_qty'  => $p->fixed_qty,
                'status'     => $p->status,
                'locked'     => $p->isLocked(),
                'total_qty'  => (int) ($p->total_qty ?? 0),
                'created_at' => $p->created_at?->format('d M Y H:i'),
            ]);

        return Inertia::render('marker-plan/index', [
            'plans'     => $plans,
            'statuses'  => $this->statusOptions(),
            'search'    => $search,
            'highlight' => $highlight,
            'deleted'   => $deleted,
            'error'     => session('error'),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  CREATE: SKCL সার্চ + ফাঁকা ফর্ম                                    */
    /* ------------------------------------------------------------------ */
    public function create(Request $request)
    {
        $skclNo    = trim((string) $request->query('skcl_no', ''));
        $colorName = trim((string) $request->query('color_name', ''));

        $base = [
            'skclNo'    => $skclNo,
            'colorName' => $colorName,
            'tables'    => $this->tableOptions(),
            'statuses'  => $this->statusOptions(),
            'colors'    => [],
            'found'     => null,
            'meta'      => null,
            'sizes'     => [],
            'rows'      => [],
        ];

        if ($skclNo === '') {
            return Inertia::render('marker-plan/create', $base);
        }

        $base['colors'] = Order::where('skcl_no', $skclNo)
            ->distinct()->orderBy('color')->pluck('color')->all();

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
        $used  = $this->usedMap($orders->pluck('id'));
        $first = $orders->first();

        return Inertia::render('marker-plan/create', [
            ...$base,
            'found' => true,
            'meta'  => [
                'file_no' => $first->file_no,
                'style'   => $first->style,
                'buyer'   => $first->buyer?->name,
            ],
            'sizes' => $sizes,
            'rows'  => $this->buildRows($orders, $sizes, collect(), $used),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  STORE: quantity আছে এমন প্রতিটি সারির জন্য একটি করে marker plan   */
    /* ------------------------------------------------------------------ */
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

        $order   = $markerPlan->order;
        $details = $markerPlan->details()->get()->keyBy('size');
        $sizes   = $this->sizesFrom($order->details);
        $used    = $this->usedMap([$order->id], $markerPlan->id); // এই plan বাদে

        return Inertia::render('marker-plan/edit', [
            'plan' => [
                'id'         => $markerPlan->id,
                'ref_no'     => $markerPlan->ref_no,
                'skcl_no'    => $order->skcl_no,
                'file_no'    => $order->file_no,
                'buyer'      => $order->buyer?->name,
                'style'      => $order->style,
                'item_name'  => $order->item_name,
                'color_name' => $order->color,
            ],
            'tableNoId' => $markerPlan->table_no_id,
            'status'    => $markerPlan->status,
            'fixedQty'  => $markerPlan->fixed_qty !== null ? (string) $markerPlan->fixed_qty : '',
            'ratios'    => $this->ratiosFor($sizes, $details),
            'tables'    => $this->tableOptions(),
            'statuses'  => $this->statusOptions(),
            'sizes'     => $sizes,
            'rows'      => $this->buildRows(collect([$order]), $sizes, $details, $used),
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
            // order plan থেকেই নেওয়া হয় (বদলানো যায় না)
            $details = $this->buildDetails(
                $data['rows'][0],
                $data['ratios'] ?? [],
                $fixedQty,
                $markerPlan->order
            );

            if (! $details) {
                throw ValidationException::withMessages(['rows' => 'কমপক্ষে একটি quantity দিন।']);
            }

            $markerPlan->update([
                'table_no_id' => (int) $data['table_no_id'],
                'fixed_qty'   => $fixedQty,
                'status'      => $data['status'],
            ]);

            $markerPlan->details()->delete();
            $markerPlan->details()->createMany($details);
        });

        return redirect()->route('marker-plans.index', ['highlight' => $markerPlan->ref_no]);
    }

    /* ------------------------------------------------------------------ */
    /*  PDF: সাইজ ওয়াইজ ratio প্রিন্ট (লক হওয়া plan-ও প্রিন্ট করা যায়)   */
    /* ------------------------------------------------------------------ */
    public function pdf(MarkerPlan $markerPlan)
    {
        $markerPlan->load(['order.buyer', 'cuttingTable']);

        $details = $markerPlan->details()->get()->keyBy('size');
        $sizes   = $this->sizesFrom($details);

        $ratios     = [];
        $totalRatio = 0;
        foreach ($sizes as $size) {
            $ratio         = $details->get($size)?->ratio;
            $ratios[$size] = $ratio;
            $totalRatio   += $ratio ?? 0;
        }

        $pdf = Pdf::loadView('pdf.marker-plan', [
            'plan'        => $markerPlan,
            'order'       => $markerPlan->order,
            'tableName'   => $markerPlan->cuttingTable?->name,
            'statusLabel' => MarkerPlan::STATUSES[$markerPlan->status] ?? $markerPlan->status,
            'sizes'       => $sizes,
            'ratios'      => $ratios,
            'totalRatio'  => $totalRatio,
            'totalQty'    => (int) $details->sum('quantity'),
            'printedAt'   => now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream("marker-plan-{$markerPlan->ref_no}.pdf");
    }

    /* ------------------------------------------------------------------ */
    /*  DELETE (details FK cascade-এ নিজেই মুছে যায়)                       */
    /* ------------------------------------------------------------------ */
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
            'table_no_id'         => ['required', 'integer', 'exists:tables,id'],
            'status'              => ['required', Rule::in(array_keys(MarkerPlan::STATUSES))],
            'fixed_qty'           => ['nullable', 'integer', 'min:0'],
            'ratios'              => ['nullable', 'array'],
            'ratios.*'            => ['nullable', 'integer', 'min:0'],
            'rows'                => ['required', 'array', 'min:1'],
            'rows.*.order_id'     => ['required', 'integer', 'exists:orders,id'],
            'rows.*.quantities'   => ['required', 'array'],
            'rows.*.quantities.*' => ['nullable', 'integer', 'min:0'],
        ], [
            'table_no_id.required' => 'Table সিলেক্ট করুন।',
            'table_no_id.exists'   => 'সঠিক Table সিলেক্ট করুন।',
            'status.in'            => 'সঠিক Status সিলেক্ট করুন।',
        ], [
            'fixed_qty' => 'lay quantity',
        ]);
    }

    private function createPlans(array $data, Collection $orders): array
    {
        $nextRef  = $this->refGenerator();
        $fixedQty = ($data['fixed_qty'] ?? null) === null ? null : (int) $data['fixed_qty'];
        $ratios   = $data['ratios'] ?? [];
        $refs     = [];

        foreach ($data['rows'] as $row) {
            $order = $orders->get($row['order_id']);
            if (! $order) {
                continue;
            }

            $details = $this->buildDetails($row, $ratios, $fixedQty, $order);
            if (! $details) {
                continue; // এই সারিতে quantity নেই, plan হবে না
            }

            $plan = MarkerPlan::create([
                'ref_no'      => $nextRef(),
                'order_id'    => $order->id,
                'table_no_id' => (int) $data['table_no_id'],
                'fixed_qty'   => $fixedQty,
                'status'      => $data['status'],
            ]);

            $plan->details()->createMany($details);
            $refs[] = $plan->ref_no;
        }

        if (! $refs) {
            throw ValidationException::withMessages(['rows' => 'কমপক্ষে একটি quantity দিন।']);
        }

        return $refs;
    }

    /** একটি সারির quantity থেকে details বানায় (শুধু order-এ থাকা size) */
    private function buildDetails(array $row, array $ratios, ?int $fixedQty, Order $order): array
    {
        $details = [];

        foreach ($row['quantities'] as $size => $qty) {
            if ($qty === null || $qty === '') {
                continue;
            }

            $po = $order->details->first(fn($d) => (string) $d->size === (string) $size);
            if (! $po) {
                continue; // order-এ নেই এমন size সেভ হবে না
            }

            $details[] = [
                'size'     => $po->size,
                // ratio শুধু Lay Quantity দেওয়া থাকলে সেভ হয়
                'ratio'    => ($fixedQty !== null && isset($ratios[$size])) ? (int) $ratios[$size] : null,
                'quantity' => (int) $qty,
            ];
        }

        return $details;
    }

    /** সাইজ সাজানো (XS, S, M, L, XL ... বাকিগুলো শেষে) */
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
            $poQty      = [];
            $usedQty    = [];

            foreach ($sizes as $size) {
                $d = $savedBySize->get($size);

                $quantities[$size] = $d ? (string) $d->quantity : '';
                $poQty[$size]      = (int) ($poBySize->get($size)?->quantity ?? 0);
                $usedQty[$size]    = (int) $used->get($order->id . '|' . $size, 0);
            }

            return [
                'order_id'        => $order->id,
                'item_name'       => $order->item_name,
                'color_name'      => $order->color,
                'available'       => $order->details->pluck('size')->all(),
                'quantities'      => $quantities,
                'po_quantities'   => $poQty,
                'used_quantities' => $usedQty,
            ];
        })->values()->all();
    }

    /** Edit পেজের ratio: সেভ করা মান, না থাকলে 1 */
    private function ratiosFor(array $sizes, Collection $details): array
    {
        $out = [];

        foreach ($sizes as $size) {
            $d          = $details->get($size);
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
            "Ref {$plan->ref_no} Approved হয়ে গেছে, তাই এটি আর edit বা delete করা যাবে না।"
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
                'value'  => $value,
                'label'  => $label,
                'locked' => in_array($value, MarkerPlan::LOCKED_STATUSES, true),
            ])
            ->values()
            ->all();
    }
}
