<?php

namespace App\Http\Controllers;

use App\Models\PoSheet;
use App\Models\SizeQuantity;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SizeQuantityController extends Controller
{
    private const SIZE_ORDER = ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '2XL', '3XL', '4XL'];

    public function index(Request $request)
    {
        $skclNo    = trim((string) $request->query('skcl_no', ''));
        $colorName = trim((string) $request->query('color_name', ''));
        $refNo     = trim((string) $request->query('ref_no', ''));

        $base = [
            'skclNo'    => $skclNo,
            'colorName' => $colorName,
            'refNo'     => '',
            'colors'    => [],
            'refs'      => [],
            'found'     => null,
            'meta'      => null,
            'sizes'     => [],
            'rows'      => [],
        ];

        if ($skclNo === '') {
            return Inertia::render('size-quantities/index', $base);
        }

        $query = PoSheet::where('skcl_no', $skclNo);

        $base['colors'] = (clone $query)->distinct()->orderBy('color_name')->pluck('color_name')->all();
        $base['refs']   = $this->refsFor($skclNo);

        if ($colorName !== '') {
            $query->where('color_name', $colorName);
        }

        $source = $query->get();

        if ($source->isEmpty()) {
            return Inertia::render('size-quantities/index', [...$base, 'found' => false]);
        }

        // ref_no দেওয়া থাকলে সেই ref-এর quantity, না থাকলে ফাঁকা ফর্ম
        $saved = collect();
        if ($refNo !== '') {
            $saved = SizeQuantity::where('skcl_no', $skclNo)->where('ref_no', $refNo)->get();
            abort_if($saved->isEmpty(), 404, 'Ref পাওয়া যায়নি।');
            $saved = $saved->keyBy(fn ($r) => $this->key($r->country, $r->item_name, $r->color_name, $r->size));
        }

        $sizes = $source->pluck('size')->unique()->values()
            ->sortBy(function ($s) {
                $i = array_search(strtoupper($s), self::SIZE_ORDER, true);
                return $i === false ? 999 : $i;
            })->values()->all();

        $rows = $source
            ->groupBy(fn ($r) => $r->country . '|' . $r->item_name . '|' . $r->color_name)
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

        $first = $source->first();

        return Inertia::render('size-quantities/index', [
            ...$base,
            'refNo' => $refNo,
            'found' => true,
            'meta'  => [
                'file_no'  => $first->file_no,
                'order_no' => $first->order_no,
                'style_no' => $first->style_no,
            ],
            'sizes' => $sizes,
            'rows'  => $rows,
        ]);
    }

    // প্রতি Save-এ নতুন ref
    public function store(Request $request)
    {
        $data   = $this->validated($request);
        $source = $this->sourceFor($data['skcl_no']);

        $refNo = DB::transaction(function () use ($data, $source) {
            $ref = $this->nextRef();
            $this->writeCells($ref, $data, $source);
            return $ref;
        });

        return redirect()->route('size-quantities.index', [
            'skcl_no' => $data['skcl_no'],
            'ref_no'  => $refNo,
        ]);
    }

    // বিদ্যমান ref আপডেট
    public function update(Request $request, string $ref)
    {
        $data = $this->validated($request);

        abort_unless(
            SizeQuantity::where('ref_no', $ref)->where('skcl_no', $data['skcl_no'])->exists(),
            404,
            'Ref পাওয়া যায়নি।'
        );

        $source = $this->sourceFor($data['skcl_no']);

        DB::transaction(fn () => $this->writeCells($ref, $data, $source));

        return redirect()->route('size-quantities.index', [
            'skcl_no' => $data['skcl_no'],
            'ref_no'  => $ref,
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'skcl_no'             => ['required', 'string'],
            'rows'                => ['required', 'array', 'min:1'],
            'rows.*.country'      => ['required', 'string'],
            'rows.*.item_name'    => ['required', 'string'],
            'rows.*.color_name'   => ['required', 'string'],
            'rows.*.quantities'   => ['required', 'array'],
            'rows.*.quantities.*' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function sourceFor(string $skclNo): Collection
    {
        $source = PoSheet::where('skcl_no', $skclNo)->get()
            ->keyBy(fn ($r) => $this->key($r->country, $r->item_name, $r->color_name, $r->size));

        abort_if($source->isEmpty(), 422, 'SKCL No পাওয়া যায়নি।');

        return $source;
    }

    /**
     * একটি ref-এর ঘরগুলো লেখে। ফাঁকা ঘর থাকলে ওই ref থেকে রো মুছে যায়।
     */
    private function writeCells(string $ref, array $data, Collection $source): void
    {
        $written = 0;

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
                    'file_no'  => $src->file_no,
                    'order_no' => $src->order_no,
                    'style_no' => $src->style_no,
                    'quantity' => (int) $qty,
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

    private function refsFor(string $skclNo): array
    {
        return SizeQuantity::where('skcl_no', $skclNo)
            ->select(
                'ref_no',
                DB::raw('SUM(quantity) as total_qty'),
                DB::raw('MIN(created_at) as first_saved'),
                DB::raw('MAX(updated_at) as last_saved')
            )
            ->groupBy('ref_no')
            ->orderByDesc('first_saved')
            ->get()
            ->map(fn ($r) => [
                'ref_no'     => $r->ref_no,
                'total_qty'  => (int) $r->total_qty,
                'created_at' => \Carbon\Carbon::parse($r->first_saved)->format('d M Y H:i'),
                'updated_at' => \Carbon\Carbon::parse($r->last_saved)->format('d M Y H:i'),
            ])
            ->all();
    }

    private function key(string $country, string $item, string $color, string $size): string
    {
        return implode('|', [$country, $item, $color, $size]);
    }
}
