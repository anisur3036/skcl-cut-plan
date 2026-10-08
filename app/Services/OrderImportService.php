<?php

namespace App\Services;

use App\Models\Buyer;
use App\Models\Order;
use App\Models\OrderDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderImportService
{
    // ---- Orders শিট ----
    public const ORDER_COLUMNS = ['skcl_no', 'file_no', 'buyer', 'style', 'item_name', 'color', 'shipment_date'];
    private const ORDER_REQUIRED = ['skcl_no', 'file_no', 'style', 'item_name', 'color'];
    private const ORDER_ALIASES = [
        'buyer_name' => 'buyer',
        'style_no'   => 'style',
        'color_name' => 'color',
        'shipment'   => 'shipment_date',
    ];
    // পুরনো po_sheets ফাইলের কলাম, size হিসেবে ধরা হবে না
    private const IGNORED = ['country', 'order_no', 'order_qty', 'id', 'created_at', 'updated_at'];

    // ---- Fabrics শিট ----
    public const FABRIC_COLUMNS = ['skcl_no', 'item_name', 'color', 'fabric_color', 'gsm', 'width', 'quantity_kg'];
    private const FABRIC_REQUIRED = ['skcl_no', 'item_name', 'color', 'fabric_color', 'quantity_kg'];
    private const FABRIC_ALIASES = [
        'color_name' => 'color',
        'quantity'   => 'quantity_kg',
        'kg'         => 'quantity_kg',
    ];

    // ডাটাবেস কলামের সর্বোচ্চ দৈর্ঘ্য
    private const LIMITS = [
        'skcl_no'      => 50,
        'file_no'      => 50,
        'buyer'        => 100,
        'style'        => 100,
        'item_name'    => 100,
        'color'        => 60,
        'fabric_color' => 60,
    ];

    private int $buyersCreated = 0;
    private int $ordersCreated = 0;
    private int $ordersUpdated = 0;
    private int $sizeRows      = 0;
    private int $fabricRows    = 0;

    /** @var string[] */
    private array $errors = [];

    /* ------------------------------------------------------------------ */
    /*  Import                                                              */
    /* ------------------------------------------------------------------ */
    public function import(string $path): array
    {
        $spreadsheet = IOFactory::load($path);

        $ordersSheet  = $spreadsheet->getSheetByName('Orders') ?? $spreadsheet->getSheet(0);
        $fabricsSheet = $spreadsheet->getSheetByName('Fabrics');

        $orders  = $this->parseOrders($this->rows($ordersSheet));
        $fabrics = ($fabricsSheet && $fabricsSheet !== $ordersSheet)
            ? $this->parseFabrics($this->rows($fabricsSheet))
            : [];

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        if (! $this->errors && ! $orders && ! $fabrics) {
            $this->errors[] = 'ইমপোর্ট করার মতো কোনো ডাটা পাওয়া যায়নি।';
        }

        if (! $this->errors) {
            try {
                DB::transaction(function () use ($orders, $fabrics) {
                    $this->saveOrders($orders);
                    $this->saveFabrics($fabrics);
                });
            } catch (ImportRejected $e) {
                $this->errors[] = $e->getMessage();
                $this->buyersCreated = $this->ordersCreated = $this->ordersUpdated = 0;
                $this->sizeRows = $this->fabricRows = 0;
            }
        }

        return $this->summary();
    }

    /* ------------------------------------------------------------------ */
    /*  Template                                                            */
    /* ------------------------------------------------------------------ */
    public function templateResponse(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();

        $orders = $spreadsheet->getActiveSheet();
        $orders->setTitle('Orders');
        $this->writeHeader($orders, [...self::ORDER_COLUMNS, 'XS', 'S', 'M', 'L', 'XL', '2XL', '3XL']);
        // A–F Text (12/1 তারিখ হয়ে যাওয়া ঠেকাতে), G তারিখ
        $orders->getStyle('A:F')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $orders->getStyle('G:G')->getNumberFormat()->setFormatCode('yyyy-mm-dd');

        $fabrics = $spreadsheet->createSheet();
        $fabrics->setTitle('Fabrics');
        $this->writeHeader($fabrics, self::FABRIC_COLUMNS);
        $fabrics->getStyle('A:D')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        $spreadsheet->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'order-import-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function writeHeader(Worksheet $sheet, array $headers): void
    {
        $sheet->fromArray($headers, null, 'A1');

        $last = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle("A1:{$last}1")->getFont()->setBold(true);

        for ($i = 1; $i <= count($headers); $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Parse: Orders                                                       */
    /* ------------------------------------------------------------------ */
    private function parseOrders(array $rows): array
    {
        if (! $rows) {
            $this->errors[] = 'Orders শিট খালি।';
            return [];
        }

        $info     = []; // column => cell index
        $sizeCols = []; // cell index => size label

        foreach ($rows[0] as $idx => $cell) {
            $text = $this->text($cell);
            if ($text === '') {
                continue;
            }

            $key = $this->headerKey($text, self::ORDER_ALIASES);

            if (in_array($key, self::ORDER_COLUMNS, true)) {
                $info[$key] = $idx;
            } elseif (! in_array($key, self::IGNORED, true)) {
                $sizeCols[$idx] = strtoupper($text); // বাকি সব হেডার = size
            }
        }

        $missing = array_diff(self::ORDER_REQUIRED, array_keys($info));
        if ($missing) {
            $this->errors[] = 'Orders শিটে এই কলামগুলো পাওয়া যায়নি: ' . implode(', ', $missing);
        }
        if (! $sizeCols) {
            $this->errors[] = 'Orders শিটে কোনো size কলাম (XS, S, M ...) পাওয়া যায়নি।';
        }
        $dupSizes = array_keys(array_filter(array_count_values($sizeCols), fn ($c) => $c > 1));
        if ($dupSizes) {
            $this->errors[] = 'Orders শিটে একই size কলাম একাধিকবার আছে: ' . implode(', ', $dupSizes);
        }
        if ($this->errors) {
            return [];
        }

        $result = [];
        $seen   = [];

        foreach (array_slice($rows, 1, null, true) as $i => $row) {
            $n = $i + 1; // Excel row (header = 1)

            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $ok  = true;
            $rec = ['row' => $n];

            foreach (self::ORDER_COLUMNS as $col) {
                if ($col === 'shipment_date') {
                    continue;
                }

                $val = isset($info[$col]) ? $this->text($row[$info[$col]] ?? null) : '';

                if ($val === '' && in_array($col, self::ORDER_REQUIRED, true)) {
                    $this->errors[] = "Orders Row {$n}: {$col} ফাঁকা।";
                    $ok = false;
                }
                if (mb_strlen($val) > self::LIMITS[$col]) {
                    $this->errors[] = "Orders Row {$n}: {$col} অনেক লম্বা (সর্বোচ্চ " . self::LIMITS[$col] . ' অক্ষর)।';
                    $ok = false;
                }

                $rec[$col] = $val;
            }

            $rec['shipment_date'] = null;
            if (isset($info['shipment_date'])) {
                try {
                    $rec['shipment_date'] = $this->parseDate($row[$info['shipment_date']] ?? null);
                } catch (\InvalidArgumentException $e) {
                    $this->errors[] = "Orders Row {$n}: shipment_date সঠিক নয় (yyyy-mm-dd লিখুন)।";
                    $ok = false;
                }
            }

            $sizes = [];
            foreach ($sizeCols as $idx => $size) {
                $txt = $this->text($row[$idx] ?? null);
                if ($txt === '') {
                    continue;
                }

                if (! is_numeric($txt) || (float) $txt < 0 || floor((float) $txt) != (float) $txt) {
                    $this->errors[] = "Orders Row {$n}, size {$size}: '{$txt}' সঠিক সংখ্যা নয়।";
                    $ok = false;
                    continue;
                }
                if ((int) $txt === 0) {
                    continue; // 0 বাদ
                }
                if (mb_strlen($size) > 30) {
                    $this->errors[] = "Orders Row {$n}: size '{$size}' অনেক লম্বা।";
                    $ok = false;
                    continue;
                }

                $sizes[$size] = (int) $txt;
            }

            if ($ok && ! $sizes) {
                $this->errors[] = "Orders Row {$n}: কোনো size-এ quantity নেই।";
                $ok = false;
            }

            if (! $ok) {
                continue;
            }

            $key = mb_strtolower(implode('|', [$rec['skcl_no'], $rec['item_name'], $rec['color']]));
            if (isset($seen[$key])) {
                $this->errors[] = "Orders Row {$n}: {$rec['skcl_no']} / {$rec['item_name']} / {$rec['color']} "
                    . "আগেই Row {$seen[$key]}-এ আছে (ডুপ্লিকেট)।";
                continue;
            }
            $seen[$key] = $n;

            $rec['sizes'] = $sizes;
            $result[]     = $rec;
        }

        return $result;
    }

    /* ------------------------------------------------------------------ */
    /*  Parse: Fabrics (ঐচ্ছিক শিট)                                         */
    /* ------------------------------------------------------------------ */
    private function parseFabrics(array $rows): array
    {
        $hasAny = false;
        foreach ($rows as $r) {
            if (! $this->rowIsEmpty($r)) {
                $hasAny = true;
                break;
            }
        }
        if (! $hasAny) {
            return [];
        }

        $idxMap = [];
        foreach ($rows[0] as $idx => $cell) {
            $text = $this->text($cell);
            if ($text === '') {
                continue;
            }
            $key = $this->headerKey($text, self::FABRIC_ALIASES);
            if (in_array($key, self::FABRIC_COLUMNS, true)) {
                $idxMap[$key] = $idx;
            }
        }

        $missing = array_diff(self::FABRIC_REQUIRED, array_keys($idxMap));
        if ($missing) {
            $this->errors[] = 'Fabrics শিটে এই কলামগুলো পাওয়া যায়নি: ' . implode(', ', $missing);
            return [];
        }

        $result = [];

        foreach (array_slice($rows, 1, null, true) as $i => $row) {
            $n = $i + 1;

            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $ok  = true;
            $rec = ['row' => $n];

            foreach (['skcl_no', 'item_name', 'color', 'fabric_color'] as $col) {
                $val = $this->text($row[$idxMap[$col]] ?? null);

                if ($val === '') {
                    $this->errors[] = "Fabrics Row {$n}: {$col} ফাঁকা।";
                    $ok = false;
                }
                if (mb_strlen($val) > self::LIMITS[$col]) {
                    $this->errors[] = "Fabrics Row {$n}: {$col} অনেক লম্বা (সর্বোচ্চ " . self::LIMITS[$col] . ' অক্ষর)।';
                    $ok = false;
                }
                $rec[$col] = $val;
            }

            $rec['gsm'] = null;
            if (isset($idxMap['gsm'])) {
                $t = $this->text($row[$idxMap['gsm']] ?? null);
                if ($t !== '') {
                    if (! is_numeric($t) || (float) $t < 0 || floor((float) $t) != (float) $t) {
                        $this->errors[] = "Fabrics Row {$n}: gsm '{$t}' সঠিক পূর্ণসংখ্যা নয়।";
                        $ok = false;
                    } else {
                        $rec['gsm'] = (int) $t;
                    }
                }
            }

            $rec['width'] = null;
            if (isset($idxMap['width'])) {
                $t = $this->text($row[$idxMap['width']] ?? null);
                if ($t !== '') {
                    if (! is_numeric($t) || (float) $t < 0) {
                        $this->errors[] = "Fabrics Row {$n}: width '{$t}' সঠিক সংখ্যা নয়।";
                        $ok = false;
                    } else {
                        $rec['width'] = (float) $t;
                    }
                }
            }

            $kg = $this->text($row[$idxMap['quantity_kg']] ?? null);
            if ($kg === '') {
                $this->errors[] = "Fabrics Row {$n}: quantity_kg ফাঁকা।";
                $ok = false;
            } elseif (! is_numeric($kg) || (float) $kg < 0) {
                $this->errors[] = "Fabrics Row {$n}: quantity_kg '{$kg}' সঠিক সংখ্যা নয়।";
                $ok = false;
            } else {
                $rec['quantity_kg'] = (float) $kg;
            }

            if ($ok) {
                $result[] = $rec;
            }
        }

        return $result;
    }

    /* ------------------------------------------------------------------ */
    /*  Save                                                                */
    /* ------------------------------------------------------------------ */
    private function saveOrders(array $orders): void
    {
        $buyers = []; // name => id

        foreach ($orders as $o) {
            $buyerId = null;

            if ($o['buyer'] !== '') {
                if (! isset($buyers[$o['buyer']])) {
                    $buyer = Buyer::firstOrCreate(['name' => $o['buyer']]);
                    if ($buyer->wasRecentlyCreated) {
                        $this->buyersCreated++;
                    }
                    $buyers[$o['buyer']] = $buyer->id;
                }
                $buyerId = $buyers[$o['buyer']];
            }

            $order = Order::firstOrNew([
                'skcl_no'   => $o['skcl_no'],
                'item_name' => $o['item_name'],
                'color'     => $o['color'],
            ]);
            $isNew = ! $order->exists;

            $order->file_no = $o['file_no'];
            $order->style   = $o['style'];
            if ($buyerId !== null) {
                $order->buyer_id = $buyerId;           // ফাঁকা থাকলে আগের মান থাকে
            }
            if ($o['shipment_date'] !== null) {
                $order->shipment_date = $o['shipment_date'];
            }
            $order->save();

            // আগের মতোই merge: ফাইলের size আপডেট/যোগ, ফাইলে না থাকা size অপরিবর্তিত
            foreach ($o['sizes'] as $size => $qty) {
                OrderDetail::updateOrCreate(
                    ['order_id' => $order->id, 'size' => (string) $size],
                    ['quantity' => $qty]
                );
                $this->sizeRows++;
            }

            $order->update(['order_qty' => (int) $order->details()->sum('quantity')]);

            $isNew ? $this->ordersCreated++ : $this->ordersUpdated++;
        }
    }

    private function saveFabrics(array $fabrics): void
    {
        // order অনুযায়ী ভাগ: ফাইলে যে order আছে তার ফ্যাব্রিক নতুন করে বসে
        $byOrder = [];
        foreach ($fabrics as $f) {
            $byOrder[mb_strtolower(implode('|', [$f['skcl_no'], $f['item_name'], $f['color']]))][] = $f;
        }

        foreach ($byOrder as $rows) {
            $first = $rows[0];

            $order = Order::where('skcl_no', $first['skcl_no'])
                ->where('item_name', $first['item_name'])
                ->where('color', $first['color'])
                ->first();

            if (! $order) {
                throw new ImportRejected(
                    "Fabrics Row {$first['row']}: {$first['skcl_no']} / {$first['item_name']} / {$first['color']} "
                    . 'নামে কোনো order নেই (Orders শিটে বা সিস্টেমে আগে থাকতে হবে)।'
                );
            }

            $order->fabrics()->delete();

            foreach ($rows as $f) {
                $order->fabrics()->create([
                    'color'    => $f['fabric_color'],
                    'gsm'      => $f['gsm'],
                    'width'    => $f['width'],
                    'quantity' => $f['quantity_kg'],
                ]);
                $this->fabricRows++;
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                             */
    /* ------------------------------------------------------------------ */
    private function summary(): array
    {
        return [
            'ok'             => $this->errors === [],
            'buyers_created' => $this->buyersCreated,
            'orders_created' => $this->ordersCreated,
            'orders_updated' => $this->ordersUpdated,
            'size_rows'      => $this->sizeRows,
            'fabric_rows'    => $this->fabricRows,
            'error_count'    => count($this->errors),
            'errors'         => array_slice($this->errors, 0, 50),
        ];
    }

    private function rows(?Worksheet $sheet): array
    {
        // raw মান (তারিখ হলে Excel serial), কোনো ফরম্যাটিং ছাড়া
        return $sheet ? $sheet->toArray(null, true, false, false) : [];
    }

    private function headerKey(string $text, array $aliases): string
    {
        $key = strtolower(str_replace([' ', '-'], '_', $text));

        return $aliases[$key] ?? $key;
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if ($this->text($cell) !== '') {
                return false;
            }
        }

        return true;
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

    private function parseDate(mixed $v): ?string
    {
        if ($v === null || trim((string) $v) === '') {
            return null;
        }

        if (is_numeric($v)) {
            $n = (float) $v;
            if ($n < 1 || $n > 100000) {
                throw new \InvalidArgumentException('bad date');
            }

            return Date::excelToDateTimeObject($n)->format('Y-m-d');
        }

        try {
            return Carbon::parse((string) $v)->format('Y-m-d');
        } catch (\Throwable) {
            throw new \InvalidArgumentException('bad date');
        }
    }
}

class ImportRejected extends \RuntimeException
{
}
