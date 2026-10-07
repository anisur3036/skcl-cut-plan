<?php

namespace App\Imports;

use App\Models\PoSheet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;

class PoSheetImport implements ToCollection
{
    public const INFO_COLUMNS = [
        'file_no',
        'skcl_no',
        'order_no',
        'style_no',
        'country',
        'item_name',
        'color_name',
    ];

    public int $created   = 0;
    public int $updated   = 0;
    public int $unchanged = 0;

    /** @var string[] */
    public array $errors = [];

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            $this->errors[] = 'File empty';
            return;
        }

        /* ---------------- Header (১ম সারি) ---------------- */
        $infoCols = []; // column key => cell index
        $sizeCols = []; // cell index => size label

        foreach ($rows->first() as $idx => $cell) {
            $text = $this->text($cell);
            if ($text === '') {
                continue;
            }

            $key = strtolower(str_replace([' ', '-'], '_', $text));

            if (in_array($key, self::INFO_COLUMNS, true)) {
                $infoCols[$key] = $idx;
            } else {
                $sizeCols[$idx] = strtoupper($text); // বাকি সব হেডার = সাইজ
            }
        }

        $missing = array_diff(self::INFO_COLUMNS, array_keys($infoCols));
        if ($missing) {
            $this->errors[] = 'Not found these colomns' . implode(', ', $missing);
        }
        if (! $sizeCols) {
            $this->errors[] = 'Not found any size column';
        }
        $dupSizes = array_keys(array_filter(array_count_values($sizeCols), fn($c) => $c > 1));
        if ($dupSizes) {
            $this->errors[] = 'একই size কলাম একাধিকবার আছে: ' . implode(', ', $dupSizes);
        }
        if ($this->errors) {
            return;
        }

        /* ---------------- Data rows ---------------- */
        $records = [];
        $seen    = []; // duplicate ধরার জন্য: key => Excel row

        foreach ($rows->slice(1) as $i => $row) {
            $excelRow = $i + 1; // header = Row 1

            if ($row->filter(fn($c) => $this->text($c) !== '')->isEmpty()) {
                continue; // পুরো ফাঁকা সারি
            }

            $rowOk = true;
            $info  = [];

            foreach (self::INFO_COLUMNS as $col) {
                $val = $this->text($row->get($infoCols[$col]));
                if ($val === '') {
                    $this->errors[] = "Row {$excelRow}: {$col} empty";
                    $rowOk = false;
                }
                $info[$col] = $val;
            }

            $rowRecords = [];

            foreach ($sizeCols as $idx => $size) {
                $txt = $this->text($row->get($idx));
                if ($txt === '') {
                    continue;
                }

                if (! is_numeric($txt) || (float) $txt < 0 || floor((float) $txt) != (float) $txt) {
                    $this->errors[] = "Row {$excelRow}, size {$size}: '{$txt}' not right";
                    $rowOk = false;
                    continue;
                }

                $qty = (int) $txt;
                if ($qty === 0) {
                    continue; // 0 বাদ
                }

                $rowRecords[] = [...$info, 'size' => $size, 'quantity' => $qty];
            }

            if (! $rowOk) {
                continue;
            }

            if (! $rowRecords) {
                $this->errors[] = "Row {$excelRow}: size wise quantity not found";
                continue;
            }

            // একই সারি-সংমিশ্রণ ফাইলে দুইবার থাকলে ভুল
            $first = $rowRecords[0];
            $key   = mb_strtolower(implode('|', [
                $first['skcl_no'],
                $first['country'],
                $first['item_name'],
                $first['color_name'],
            ]));

            if (isset($seen[$key])) {
                $this->errors[] = "Row {$excelRow}: {$first['skcl_no']} / {$first['country']} / "
                    . "{$first['item_name']} / {$first['color_name']} আগেই Row {$seen[$key]}-এ আছে (ডুপ্লিকেট)।";
                continue;
            }

            $seen[$key] = $excelRow;
            array_push($records, ...$rowRecords);
        }

        if (! $this->errors && ! $records) {
            $this->errors[] = 'ইমপোর্ট করার মতো কোনো ডাটা পাওয়া যায়নি।';
        }

        if ($this->errors) {
            return;
        }

        /* ---------------- Save (সব সফল হলে তবেই) ---------------- */
        DB::transaction(function () use ($records) {
            foreach ($records as $rec) {
                $model = PoSheet::updateOrCreate(
                    [
                        'skcl_no'    => $rec['skcl_no'],
                        'country'    => $rec['country'],
                        'item_name'  => $rec['item_name'],
                        'color_name' => $rec['color_name'],
                        'size'       => $rec['size'],
                    ],
                    [
                        'file_no'  => $rec['file_no'],
                        'order_no' => $rec['order_no'],
                        'style_no' => $rec['style_no'],
                        'quantity' => $rec['quantity'],
                    ]
                );

                if ($model->wasRecentlyCreated) {
                    $this->created++;
                } elseif ($model->wasChanged()) {
                    $this->updated++;
                } else {
                    $this->unchanged++;
                }
            }
        });
    }

    public function summary(): array
    {
        return [
            'ok'          => $this->errors === [],
            'created'     => $this->created,
            'updated'     => $this->updated,
            'unchanged'   => $this->unchanged,
            'error_count' => count($this->errors),
            'errors'      => array_slice($this->errors, 0, 50),
        ];
    }

    /** সেলের মান পরিষ্কার স্ট্রিং বানায় (22222.0 -> "22222") */
    private function text(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_float($v) && floor($v) == $v) {
            return (string) (int) $v;
        }

        return trim((string) $v);
    }
}
