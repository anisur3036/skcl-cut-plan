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
        $skclNo    = trim((string) $request->query('skcl_no', ''));
        $colorName = trim((string) $request->query('color_name', ''));

        $base = [
            'skclNo'    => $skclNo,
            'colorName' => $colorName,
            'colors'    => [],
            'found'     => null,
            'meta'      => null,
            'sizes'     => [],
            'rows'      => [],
        ];

        if ($skclNo === '') {
            return Inertia::render('size-quantities/index', $base);
        }

        $query = PoSheet::where('skcl_no', $skclNo);

        // Color dropdown suggestions (always all colors of this SKCL)
        $base['colors'] = (clone $query)->distinct()->orderBy('color_name')->pluck('color_name')->all();

        if ($colorName !== '') {
            $query->where('color_name', $colorName);
        }

        $source = $query->get();

        if ($source->isEmpty()) {
            return Inertia::render('size-quantities/index', [...$base, 'found' => false]);
        }

        $sizes = $source->pluck('size')->unique()->values()
            ->sortBy(function ($s) {
                $i = array_search(strtoupper($s), self::SIZE_ORDER, true);
                return $i === false ? 999 : $i;
            })->values()->all();

        $saved = SizeQuantity::where('skcl_no', $skclNo)->get()
            ->keyBy(fn($r) => $this->key($r->country, $r->item_name, $r->color_name, $r->size));

        $rows = $source
            ->groupBy(fn($r) => $r->country . '|' . $r->item_name . '|' . $r->color_name)
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
