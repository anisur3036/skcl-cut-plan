<?php

namespace App\Http\Controllers;

use App\Models\MarkerPlan;
use App\Models\MarkerPlanDetail;
use App\Models\PoSheet;
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
            ->with('cuttingTable:id,name')
            ->withSum('details as total_qty', 'quantity')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('ref_no', 'like', "%{$search}%")
                        ->orWhere('skcl_no', 'like', "%{$search}%")
                        ->orWhere('country', 'like', "%{$search}%")
                        ->orWhere('item_name', 'like', "%{$search}%")
                        ->orWhere('color_name', 'like', "%{$search}%")
                        ->orWhereHas('cuttingTable', fn($t) => $t->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn(MarkerPlan $p) => [
                'id'         => $p->id,
                'ref_no'     => $p->ref_no,
                'skcl_no'    => $p->skcl_no,
                'order_no'   => $p->order_no,
                'style_no'   => $p->style_no,
                'country'    => $p->country,
                'item_name'  => $p->item_name,
                'color_name' => $p->color_name,
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
    /*  CREATE: সার্চ + ফাঁকা ফর্ম                                         */
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

        $query = PoSheet::where('skcl_no', $skclNo);

        $base['colors'] = (clone $query)->distinct()->orderBy('color_name')->pluck('color_name')->all();

        if ($colorName !== '') {
            $query->where('color_name', $colorName);
        }

        $source = $query->get();

        if ($source->isEmpty()) {
            return Inertia::render('marker-plan/create', [...$base, 'found' => false]);
        }

        $sizes = $this->sizesFrom($source);
        $used  = $this->usedMap($skclNo);

        return Inertia::render('marker-plan/create', [
            ...$base,
            'found' => true,
            'meta'  => $this->metaFrom($source->first()),
            'sizes' => $sizes,
            'rows'  => $this->buildRows($source, $sizes, collect(), $used),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  STORE: quantity আছে এমন প্রতিটি সারির জন্য একটি করে marker plan   */
    /* ------------------------------------------------------------------ */
    public function store(Request $request)
    {
        $data   = $this->validated($request);
        $source = $this->sourceFor($data['skcl_no']);

        $refs = $this->retryOnRefClash(
            fn() => DB::transaction(fn() => $this->createPlans($data, $source))
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
        $details = $markerPlan->details()->get()->keyBy('size');

        $source = PoSheet::where('skcl_no', $markerPlan->skcl_no)
            ->where('country', $markerPlan->country)
            ->where('item_name', $markerPlan->item_name)
            ->where('color_name', $markerPlan->color_name)
            ->get();

        abort_if($source->isEmpty(), 404, 'Data not found of the PO');

        $sizes = $this->sizesFrom($source);
        $used  = $this->usedMap($markerPlan->skcl_no, $markerPlan->id); // এই plan বাদে

        return Inertia::render('marker-plan/edit', [
            'plan' => [
                'id'         => $markerPlan->id,
                'ref_no'     => $markerPlan->ref_no,
                'skcl_no'    => $markerPlan->skcl_no,
                'file_no'    => $markerPlan->file_no,
                'order_no'   => $markerPlan->order_no,
                'style_no'   => $markerPlan->style_no,
                'country'    => $markerPlan->country,
                'item_name'  => $markerPlan->item_name,
                'color_name' => $markerPlan->color_name,
            ],
            'tableNoId' => $markerPlan->table_no_id,
            'status'    => $markerPlan->status,
            'fixedQty'  => $markerPlan->fixed_qty !== null ? (string) $markerPlan->fixed_qty : '',
            'ratios'    => $this->ratiosFor($sizes, $details),
            'tables'    => $this->tableOptions(),
            'statuses'  => $this->statusOptions(),
            'sizes'     => $sizes,
            'rows'      => $this->buildRows($source, $sizes, $details, $used),
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
        $data   = $this->validated($request);
        $source = $this->sourceFor($markerPlan->skcl_no);

        // country/item/color plan থেকেই নেওয়া হয় (বদলানো যায় না)
        $row = array_merge($data['rows'][0], [
            'country'    => $markerPlan->country,
            'item_name'  => $markerPlan->item_name,
            'color_name' => $markerPlan->color_name,
        ]);

        $fixedQty = ($data['fixed_qty'] ?? null) === null ? null : (int) $data['fixed_qty'];

        DB::transaction(function () use ($markerPlan, $data, $row, $fixedQty, $source) {
            [, $details] = $this->buildDetails($row, $data['ratios'] ?? [], $fixedQty, $source);

            if (! $details) {
                throw ValidationException::withMessages(['rows' => 'Please a at least 1 qty.']);
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
    /*  PDF: সাইজ ওয়াইজ ratio প্রিন্ট                                      */
    /* ------------------------------------------------------------------ */
    public function pdf(MarkerPlan $markerPlan)
    {
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

    private function lockedResponse(MarkerPlan $plan)
    {
        return redirect()->route('marker-plans.index')->with(
            'error',
            "Ref {$plan->ref_no} Approved হয়ে গেছে, তাই এটি আর edit বা delete করা যাবে না।"
        );
    }

    /* ================================================================== */
    /*  Helpers                                                             */
    /* ================================================================== */
    private function validated(Request $request): array
    {
        return $request->validate([
            'skcl_no'             => ['required', 'string'],
            'table_no_id'         => ['required', 'integer', 'exists:tables,id'],
            'status'              => ['required', Rule::in(array_keys(MarkerPlan::STATUSES))],
            'fixed_qty'           => ['nullable', 'integer', 'min:0'],
            'ratios'              => ['nullable', 'array'],
            'ratios.*'            => ['nullable', 'integer', 'min:0'],
            'rows'                => ['required', 'array', 'min:1'],
            'rows.*.country'      => ['required', 'string'],
            'rows.*.item_name'    => ['required', 'string'],
            'rows.*.color_name'   => ['required', 'string'],
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

    /** quantity আছে এমন প্রতিটি সারির জন্য একটি করে plan + details */
    private function createPlans(array $data, Collection $source): array
    {
        $nextRef  = $this->refGenerator();
        $fixedQty = ($data['fixed_qty'] ?? null) === null ? null : (int) $data['fixed_qty'];
        $ratios   = $data['ratios'] ?? [];
        $refs     = [];

        foreach ($data['rows'] as $row) {
            [$src, $details] = $this->buildDetails($row, $ratios, $fixedQty, $source);

            if (! $src || ! $details) {
                continue; // এই সারিতে quantity নেই, plan হবে না
            }

            $plan = MarkerPlan::create([
                'ref_no'      => $nextRef(),
                'skcl_no'     => $src->skcl_no,
                'file_no'     => $src->file_no,
                'order_no'    => $src->order_no,
                'style_no'    => $src->style_no,
                'country'     => $src->country,
                'item_name'   => $src->item_name,
                'color_name'  => $src->color_name,
                'table_no_id' => (int) $data['table_no_id'],
                'fixed_qty'   => $fixedQty,
                'status'      => $data['status'],
            ]);

            $plan->details()->createMany($details);
            $refs[] = $plan->ref_no;
        }

        if (! $refs) {
            throw ValidationException::withMessages(['rows' => 'At least give 1 qty']);
        }

        return $refs;
    }

    /**
     * একটি সারির quantity থেকে details বানায়।
     * ফেরত: [po_sheets-এর প্রথম মিলে যাওয়া রো, details অ্যারে]
     */
    private function buildDetails(array $row, array $ratios, ?int $fixedQty, Collection $source): array
    {
        $src     = null;
        $details = [];

        foreach ($row['quantities'] as $size => $qty) {
            if ($qty === null || $qty === '') {
                continue;
            }

            $s = $source->get($this->key($row['country'], $row['item_name'], $row['color_name'], $size));
            if (! $s) {
                continue; // po_sheets-এ নেই এমন size সেভ হবে না
            }

            $src ??= $s;

            $details[] = [
                'size'     => $s->size,
                // ratio শুধু Lay Quantity দেওয়া থাকলে সেভ হয়
                'ratio'    => ($fixedQty !== null && isset($ratios[$size])) ? (int) $ratios[$size] : null,
                'quantity' => (int) $qty,
            ];
        }

        return [$src, $details];
    }

    private function sourceFor(string $skclNo): Collection
    {
        $source = PoSheet::where('skcl_no', $skclNo)->get()
            ->keyBy(fn($r) => $this->key($r->country, $r->item_name, $r->color_name, $r->size));

        abort_if($source->isEmpty(), 422, 'SKCL not found');

        return $source;
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

    private function metaFrom($first): array
    {
        return [
            'file_no'  => $first->file_no,
            'order_no' => $first->order_no,
            'style_no' => $first->style_no,
        ];
    }

    private function buildRows(Collection $source, array $sizes, Collection $savedBySize, Collection $used): array
    {
        return $source
            ->groupBy(fn($r) => $this->groupKey($r->country, $r->item_name, $r->color_name))
            ->map(function ($group) use ($sizes, $savedBySize, $used) {
                $first = $group->first();

                $poBySize = $group->groupBy('size')
                    ->map(fn($g) => (int) $g->sum(fn($r) => (int) $r->quantity));

                $quantities = [];
                $poQty      = [];
                $usedQty    = [];

                foreach ($sizes as $size) {
                    $d = $savedBySize->get($size);

                    $quantities[$size] = $d ? (string) $d->quantity : '';
                    $poQty[$size]      = (int) ($poBySize[$size] ?? 0);
                    $usedQty[$size]    = (int) $used->get(
                        $this->key($first->country, $first->item_name, $first->color_name, $size),
                        0
                    );
                }

                return [
                    'country'         => $first->country,
                    'item_name'       => $first->item_name,
                    'color_name'      => $first->color_name,
                    'available'       => $group->pluck('size')->all(),
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

    private function usedMap(string $skclNo, ?int $exceptPlanId = null): Collection
    {
        return MarkerPlanDetail::query()
            ->join('marker_plans', 'marker_plans.id', '=', 'marker_plan_details.marker_plan_id')
            ->where('marker_plans.skcl_no', $skclNo)
            ->where('marker_plans.status', '!=', MarkerPlan::STATUS_CANCELLED)
            ->when($exceptPlanId !== null, fn($q) => $q->where('marker_plans.id', '!=', $exceptPlanId))
            ->select(
                'marker_plans.country',
                'marker_plans.item_name',
                'marker_plans.color_name',
                'marker_plan_details.size',
                DB::raw('SUM(marker_plan_details.quantity) as used_qty')
            )
            ->groupBy(
                'marker_plans.country',
                'marker_plans.item_name',
                'marker_plans.color_name',
                'marker_plan_details.size'
            )
            ->get()
            ->keyBy(fn($r) => $this->key($r->country, $r->item_name, $r->color_name, $r->size))
            ->map(fn($r) => (int) $r->used_qty);
    }

    /** REF-20261007-0001, 0002 ... (একই Save-এ একাধিক plan হলে ক্রমান্বয়ে) */
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

    private function tableOptions(): array
    {
        return Table::orderBy('name')->get(['id', 'name'])->all();
    }

    private function statusOptions(): array
    {
        return collect(MarkerPlan::STATUSES)
            ->map(fn($label, $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    private function groupKey(string $country, string $item, string $color): string
    {
        return implode('|', [$country, $item, $color]);
    }

    private function key(string $country, string $item, string $color, string $size): string
    {
        return implode('|', [$country, $item, $color, $size]);
    }
}
