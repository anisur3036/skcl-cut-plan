<?php

namespace App\Http\Controllers;

use App\Models\PoSheet;
use App\Models\SizeQuantity;
use App\Models\Table;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SizeQuantityController extends Controller
{
    private const SIZE_ORDER = ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '2XL', '3XL', '4XL'];

    /* ------------------------------------------------------------------ */
    /*  LIST: ref অনুযায়ী তালিকা, DESC                                    */
    /* ------------------------------------------------------------------ */
    public function index(Request $request)
    {
        $search    = trim((string) $request->query('search', ''));
        $highlight = trim((string) $request->query('highlight', ''));

        $refs = SizeQuantity::query()
            ->leftJoin('tables', 'tables.id', '=', 'size_quantities.table_no_id')
            ->select(
                'size_quantities.ref_no',
                'size_quantities.skcl_no',
                'size_quantities.file_no',
                'size_quantities.order_no',
                'size_quantities.style_no',
                DB::raw('MAX(tables.name) as table_name'),
                DB::raw('MAX(size_quantities.fixed_qty) as fixed_qty'),
                //GROUP_CONCAT will be replace for sql_server STRING_AGG
                DB::raw('GROUP_CONCAT(DISTINCT size_quantities.color_name ORDER BY size_quantities.color_name SEPARATOR ", ") as colors'),
                DB::raw('SUM(size_quantities.quantity) as total_qty'),
                DB::raw('MIN(size_quantities.created_at) as first_saved'),
                DB::raw('MAX(size_quantities.updated_at) as last_saved')
            )
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('size_quantities.ref_no', 'like', "%{$search}%")
                        ->orWhere('size_quantities.skcl_no', 'like', "%{$search}%")
                        ->orWhere('tables.name', 'like', "%{$search}%");
                });
            })
            ->groupBy(
                'size_quantities.ref_no',
                'size_quantities.skcl_no',
                'size_quantities.file_no',
                'size_quantities.order_no',
                'size_quantities.style_no'
            )
            ->orderByDesc('first_saved')   // নতুন আগে
            ->orderByDesc('size_quantities.ref_no')
            ->paginate(15)
            ->withQueryString()
            ->through(fn($r) => [
                'ref_no'     => $r->ref_no,
                'skcl_no'    => $r->skcl_no,
                'file_no'    => $r->file_no,
                'order_no'   => $r->order_no,
                'style_no'   => $r->style_no,
                'table_name' => $r->table_name,
                'fixed_qty'  => $r->fixed_qty !== null ? (int) $r->fixed_qty : null,
                'colors'     => $r->colors,
                'total_qty'  => (int) $r->total_qty,
                'created_at' => Carbon::parse($r->first_saved)->format('d M Y H:i'),
                'updated_at' => Carbon::parse($r->last_saved)->format('d M Y H:i'),
            ]);

        return Inertia::render('size-quantities/index', [
            'refs'      => $refs,
            'search'    => $search,
            'highlight' => $highlight,
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
            'colors'    => [],
            'found'     => null,
            'meta'      => null,
            'sizes'     => [],
            'rows'      => [],
        ];

        if ($skclNo === '') {
            return Inertia::render('size-quantities/create', $base);
        }

        $query = PoSheet::where('skcl_no', $skclNo);

        $base['colors'] = (clone $query)->distinct()->orderBy('color_name')->pluck('color_name')->all();

        if ($colorName !== '') {
            $query->where('color_name', $colorName);
        }

        $source = $query->get();

        if ($source->isEmpty()) {
            return Inertia::render('size-quantities/create', [...$base, 'found' => false]);
        }

        $sizes = $this->sizesFrom($source);

        return Inertia::render('size-quantities/create', [
            ...$base,
            'found' => true,
            'meta'  => $this->metaFrom($source->first()),
            'sizes' => $sizes,
            'rows'  => $this->buildRows($source, $sizes, collect()),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  STORE: প্রতি Save-এ নতুন ref                                       */
    /* ------------------------------------------------------------------ */
    public function store(Request $request)
    {
        $data   = $this->validated($request);
        $source = $this->sourceFor($data['skcl_no']);

        $refNo = DB::transaction(function () use ($data, $source) {
            $ref = $this->nextRef();
            $this->writeCells($ref, $data, $source);
            return $ref;
        });

        return redirect()->route('size-quantities.index', ['highlight' => $refNo]);
    }

    /* ------------------------------------------------------------------ */
    /*  EDIT: একটি ref-এর ডাটা লোড                                         */
    /* ------------------------------------------------------------------ */
    public function edit(Request $request, string $ref)
    {
        $skclNo = trim((string) $request->query('skcl_no', ''));
        abort_if($skclNo === '', 404);

        $saved = SizeQuantity::where('ref_no', $ref)->where('skcl_no', $skclNo)->get();
        abort_if($saved->isEmpty(), 404, 'Ref পাওয়া যায়নি।');

        // শুধু এই ref-এ থাকা country/item/color সারিগুলো দেখাবে
        $groups = $saved
            ->map(fn($r) => $this->groupKey($r->country, $r->item_name, $r->color_name))
            ->unique()
            ->all();

        $source = PoSheet::where('skcl_no', $skclNo)->get()
            ->filter(fn($r) => in_array($this->groupKey($r->country, $r->item_name, $r->color_name), $groups, true))
            ->values();

        abort_if($source->isEmpty(), 404);

        $savedByKey = $saved->keyBy(fn($r) => $this->key($r->country, $r->item_name, $r->color_name, $r->size));
        $sizes      = $this->sizesFrom($source);
        $firstSaved = $saved->first();

        return Inertia::render('size-quantities/edit', [
            'refNo'     => $ref,
            'skclNo'    => $skclNo,
            'tableNoId' => $firstSaved->table_no_id,
            'fixedQty'  => $firstSaved->fixed_qty !== null ? (string) $firstSaved->fixed_qty : '',
            'tables'    => $this->tableOptions(),
            'meta'      => $this->metaFrom($source->first()),
            'sizes'     => $sizes,
            'rows'      => $this->buildRows($source, $sizes, $savedByKey),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  UPDATE                                                              */
    /* ------------------------------------------------------------------ */
    public function update(Request $request, string $ref)
    {
        $data = $this->validated($request);

        abort_unless(
            SizeQuantity::where('ref_no', $ref)->where('skcl_no', $data['skcl_no'])->exists(),
            404,
            'Ref পাওয়া যায়নি।'
        );

        $source = $this->sourceFor($data['skcl_no']);

        DB::transaction(fn() => $this->writeCells($ref, $data, $source));

        return redirect()->route('size-quantities.index', ['highlight' => $ref]);
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                             */
    /* ------------------------------------------------------------------ */
    private function validated(Request $request): array
    {
        return $request->validate([
            'skcl_no'             => ['required', 'string'],
            'table_no_id'         => ['required', 'integer', 'exists:tables,id'],
            'fixed_qty'           => ['nullable', 'integer', 'min:0'],
            'rows'                => ['required', 'array', 'min:1'],
            'rows.*.country'      => ['required', 'string'],
            'rows.*.item_name'    => ['required', 'string'],
            'rows.*.color_name'   => ['required', 'string'],
            'rows.*.quantities'   => ['required', 'array'],
            'rows.*.quantities.*' => ['nullable', 'integer', 'min:0'],
        ], [
            'table_no_id.required' => 'Table সিলেক্ট করুন।',
            'table_no_id.exists'   => 'সঠিক Table সিলেক্ট করুন।',
        ]);
    }

    private function tableOptions(): array
    {
        return Table::orderBy('name')->get(['id', 'name'])->all();
    }

    private function sourceFor(string $skclNo): Collection
    {
        $source = PoSheet::where('skcl_no', $skclNo)->get()
            ->keyBy(fn($r) => $this->key($r->country, $r->item_name, $r->color_name, $r->size));

        abort_if($source->isEmpty(), 422, 'SKCL No পাওয়া যায়নি।');

        return $source;
    }

    private function sizesFrom(Collection $source): array
    {
        return $source->pluck('size')->unique()->values()
            ->sortBy(function ($s) {
                $i = array_search(strtoupper($s), self::SIZE_ORDER, true);
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

    private function buildRows(Collection $source, array $sizes, Collection $saved): array
    {
        return $source
            ->groupBy(fn($r) => $this->groupKey($r->country, $r->item_name, $r->color_name))
            ->map(function ($group) use ($sizes, $saved) {
                $first = $group->first();

                $quantities = [];
                foreach ($sizes as $size) {
                    $s = $saved->get($this->key($first->country, $first->item_name, $first->color_name, $size));
                    $quantities[$size] = $s ? (string) $s->quantity : '';
                }

                return [
                    'country'    => $first->country,
                    'item_name'  => $first->item_name,
                    'color_name' => $first->color_name,
                    'available'  => $group->pluck('size')->all(),
                    'quantities' => $quantities,
                ];
            })->values()->all();
    }

    /**
     * একটি ref-এর ঘরগুলো লেখে। ফাঁকা ঘর থাকলে ওই ref থেকে রো মুছে যায়।
     */
    private function writeCells(string $ref, array $data, Collection $source): void
    {
        $written  = 0;
        $tableId  = (int) $data['table_no_id'];
        $fixedQty = ($data['fixed_qty'] ?? null) === null ? null : (int) $data['fixed_qty'];

        foreach ($data['rows'] as $row) {
            foreach ($row['quantities'] as $size => $qty) {
                $src = $source->get($this->key($row['country'], $row['item_name'], $row['color_name'], $size));
                if (! $src) {
                    continue; // po_sheets-এ নেই এমন combination সেভ হবে না
                }

                $match = [
                    'ref_no'     => $ref,
                    'skcl_no'    => $src->skcl_no,
                    'country'    => $src->country,
                    'item_name'  => $src->item_name,
                    'color_name' => $src->color_name,
                    'size'       => $src->size,
                ];

                if ($qty === null || $qty === '') {
                    SizeQuantity::where($match)->delete();
                    continue;
                }

                SizeQuantity::updateOrCreate($match, [
                    'table_no_id' => $tableId,
                    'fixed_qty'   => $fixedQty,
                    'file_no'     => $src->file_no,
                    'order_no'    => $src->order_no,
                    'style_no'    => $src->style_no,
                    'quantity'    => (int) $qty,
                ]);
                $written++;
            }
        }

        if ($written === 0) {
            throw ValidationException::withMessages([
                'rows' => 'কমপক্ষে একটি quantity দিন।',
            ]);
        }
    }

    // REF-20261006-0001 ফরম্যাট
    private function nextRef(): string
    {
        $prefix = 'REF-' . now()->format('Ymd') . '-';

        $last = SizeQuantity::where('ref_no', 'like', $prefix . '%')
            ->orderByDesc('ref_no')
            ->value('ref_no');

        $next = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
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
