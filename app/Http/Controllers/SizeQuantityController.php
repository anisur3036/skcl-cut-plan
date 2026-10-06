<?php

namespace App\Http\Controllers;

use App\Models\PoSheet;
use App\Models\SizeQuantity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SizeQuantityController extends Controller
{
    private const SIZE_ORDER = ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '2XL', '3XL', '4XL'];

    public function index(Request $request)
    {
        $skclNo = trim((string) $request->query('skcl_no', ''));

        if ($skclNo === '') {
            return Inertia::render('size-quantities/index', [
                'skclNo' => '',
                'found' => null,
                'meta' => null,
                'sizes' => [],
                'rows' => [],
            ]);
        }

        $source = PoSheet::where('skcl_no', $skclNo)->get();

        if ($source->isEmpty()) {
            return Inertia::render('size-quantities/index', [
                'skclNo' => $skclNo,
                'found' => false,
                'meta' => null,
                'sizes' => [],
                'rows' => [],
            ]);
        }

        // Dynamic size columns
        $sizes = $source->pluck('size')->unique()->values()
            ->sortBy(function ($s) {
                $i = array_search(strtoupper($s), self::SIZE_ORDER, true);
                return $i === false ? 999 : $i;
            })->values()->all();

        // Previously saved quantities
        $saved = SizeQuantity::where('skcl_no', $skclNo)->get()
            ->keyBy(fn($r) => $this->key($r->country, $r->item_name, $r->color_name, $r->size));

        // Pivot rows: country + item + color
        $rows = $source
            ->groupBy(fn($r) => $r->country . '|' . $r->item_name . '|' . $r->color_name)
            ->map(function ($group) use ($sizes, $saved) {
                $first = $group->first();
                $available = $group->pluck('size')->all();

                $quantities = [];
                foreach ($sizes as $size) {
                    $s = $saved->get($this->key($first->country, $first->item_name, $first->color_name, $size));
                    $quantities[$size] = $s ? (string) $s->quantity : '';
                }

                return [
                    'country'    => $first->country,
                    'item_name'  => $first->item_name,
                    'color_name' => $first->color_name,
                    'available'  => $available,   // এই সারিতে কোন সাইজগুলো valid
                    'quantities' => $quantities,
                ];
            })->values()->all();

        $first = $source->first();

        return Inertia::render('size-quantities/index', [
            'skclNo' => $skclNo,
            'found'  => true,
            'meta'   => [
                'file_no'  => $first->file_no,
                'order_no' => $first->order_no,
                'style_no' => $first->style_no,
            ],
            'sizes' => $sizes,
            'rows'  => $rows,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'skcl_no'                => ['required', 'string'],
            'rows'                   => ['required', 'array', 'min:1'],
            'rows.*.country'         => ['required', 'string'],
            'rows.*.item_name'       => ['required', 'string'],
            'rows.*.color_name'      => ['required', 'string'],
            'rows.*.quantities'      => ['required', 'array'],
            'rows.*.quantities.*'    => ['nullable', 'integer', 'min:0'],
        ]);

        $source = PoSheet::where('skcl_no', $data['skcl_no'])->get()
            ->keyBy(fn($r) => $this->key($r->country, $r->item_name, $r->color_name, $r->size));

        abort_if($source->isEmpty(), 422, 'SKCL No পাওয়া যায়নি।');

        DB::transaction(function () use ($data, $source) {
            foreach ($data['rows'] as $row) {
                foreach ($row['quantities'] as $size => $qty) {
                    if ($qty === null || $qty === '') {
                        continue;
                    }

                    // শুধু po_sheets-এ থাকা valid কম্বিনেশন সেভ হবে
                    $src = $source->get($this->key($row['country'], $row['item_name'], $row['color_name'], $size));
                    if (! $src) {
                        continue;
                    }

                    SizeQuantity::updateOrCreate(
                        [
                            'skcl_no'    => $src->skcl_no,
                            'country'    => $src->country,
                            'item_name'  => $src->item_name,
                            'color_name' => $src->color_name,
                            'size'       => $src->size,
                        ],
                        [
                            'file_no'  => $src->file_no,
                            'order_no' => $src->order_no,
                            'style_no' => $src->style_no,
                            'quantity' => (int) $qty,
                        ]
                    );
                }
            }
        });

        return redirect()
            ->route('size-quantities.index', ['skcl_no' => $data['skcl_no']])
            ->with('success', 'সেভ হয়েছে।');
    }

    private function key(string $country, string $item, string $color, string $size): string
    {
        return implode('|', [$country, $item, $color, $size]);
    }
}
