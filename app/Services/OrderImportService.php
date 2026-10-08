<?php

namespace App\Services;

use App\Models\Buyer;
use App\Models\Order;
use App\Models\OrderDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderImportService
{
    public const ORDER_COLUMNS = [
        'skcl_no',
        'file_no',
        'buyer',
        'style',
        'item_name',
        'color',
        'shipment_date',
        'extra_cut_percent',
        'max_lay',
        'cad_consumption',
        'required_fabrics',
    ];
    private const ORDER_TEXT_COLUMNS = ['skcl_no', 'file_no', 'buyer', 'style', 'item_name', 'color'];
    private const ORDER_REQUIRED = ['skcl_no', 'file_no', 'style', 'item_name', 'color'];
    private const ORDER_ALIASES = [
        'buyer_name' => 'buyer',
        'style_no' => 'style',
        'color_name' => 'color',
        'shipment' => 'shipment_date',
        'extra_cut' => 'extra_cut_percent',
        'required_fabric' => 'required_fabrics',
    ];
    private const IGNORED = ['country', 'order_no', 'order_qty', 'id', 'created_at', 'updated_at'];

    private const TEMPLATE_SIZES = ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL'];

    // max size in database table column
    private const LIMITS = [
        'skcl_no' => 50,
        'file_no' => 50,
        'buyer' => 100,
        'style' => 100,
        'item_name' => 100,
        'color' => 60,
    ];

    private int $buyersCreated = 0;
    private int $ordersCreated = 0;
    private int $ordersUpdated = 0;
    private int $sizeRows = 0;

    /** @var string[] */
    private array $errors = [];

    /* ------------------------------------------------------------------ */
    /*  Import                                                              */
    /* ------------------------------------------------------------------ */
    public function import(string $path): array
    {
        $spreadsheet = IOFactory::load($path);

        $ordersSheet = $spreadsheet->getSheetByName('Orders') ?? $spreadsheet->getSheet(0);
        $orders = $this->parseOrders($this->rows($ordersSheet));

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        if (!$this->errors && !$orders) {
            $this->errors[] = 'There is no import file';
        }

        if (!$this->errors) {
            DB::transaction(fn() => $this->saveOrders($orders));
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
        $this->writeHeader($orders, [...self::ORDER_COLUMNS, ...self::TEMPLATE_SIZES]);
        $orders->freezePane('A2');

        $date = $this->colLetter('shipment_date');      // G
        $pct = $this->colLetter('extra_cut_percent');  // H
        $lay = $this->colLetter('max_lay');            // I
        $cad = $this->colLetter('cad_consumption');    // J
        $fab = $this->colLetter('required_fabrics');   // K

        $orders->getStyle('A:F')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $orders->getStyle("{$date}:{$date}")->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $orders->getStyle("{$pct}:{$lay}")->getNumberFormat()->setFormatCode('0');
        $orders->getStyle("{$cad}:{$cad}")->getNumberFormat()->setFormatCode('0.000');
        $orders->getStyle("{$fab}:{$fab}")->getNumberFormat()->setFormatCode('0.00');

        $this->addWholeNumberValidation(
            $orders,
            "{$pct}2:{$pct}5000",
            0,
            100,
            'extra_cut_percent',
            'Please give a whole number'
        );

        $this->addWholeNumberValidation($orders, "{$lay}2:{$lay}5000", 0, null, 'max_lay', 'Please give a whole number');

        $firstSize = Coordinate::stringFromColumnIndex(count(self::ORDER_COLUMNS) + 1);
        $lastSize = Coordinate::stringFromColumnIndex(count(self::ORDER_COLUMNS) + count(self::TEMPLATE_SIZES));
        $this->addWholeNumberValidation(
            $orders,
            "{$firstSize}2:{$lastSize}5000",
            0,
            null,
            'Size quantity',
            'Please give whole number'
        );

        $this->buildExampleSheet($spreadsheet->createSheet());

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

    private function colLetter(string $column): string
    {
        return Coordinate::stringFromColumnIndex(array_search($column, self::ORDER_COLUMNS, true) + 1);
    }

    private function addWholeNumberValidation(
        Worksheet $sheet,
        string $range,
        int $min,
        ?int $max,
        string $title,
        string $message
    ): void {
        $v = new DataValidation();
        $v->setType(DataValidation::TYPE_WHOLE);
        $v->setErrorStyle(DataValidation::STYLE_STOP);

        if ($max === null) {
            $v->setOperator(DataValidation::OPERATOR_GREATERTHANOREQUAL);
            $v->setFormula1((string) $min);
        } else {
            $v->setOperator(DataValidation::OPERATOR_BETWEEN);
            $v->setFormula1((string) $min);
            $v->setFormula2((string) $max);
        }

        $v->setAllowBlank(true);
        $v->setShowErrorMessage(true);
        $v->setErrorTitle($title);
        $v->setError($message);
        $v->setShowInputMessage(true);
        $v->setPromptTitle($title);
        $v->setPrompt($message);

        $sheet->setDataValidation($range, $v);
    }

    private function buildExampleSheet(Worksheet $sheet): void
    {
        $sheet->setTitle('Example');

        $sheet->setCellValue('A1', '');
        $sheet->getStyle('A1')->getFont()->setBold(true);

        $sizes = ['XS', 'S', 'M', 'L', 'XL'];
        $headers = [...self::ORDER_COLUMNS, ...$sizes];

        $sheet->fromArray($headers, null, 'A3');
        $sheet->getStyle('A3:' . Coordinate::stringFromColumnIndex(count($headers)) . '3')
            ->getFont()->setBold(true);

        $sheet->fromArray([
            ['22222/1', '22222', 'Buyer A', 'efgh', 'T-Shirt', 'White', '2026-12-15', 5, 120, 0.245, 1250.5, 168, 200, 300, 250, 250],
            ['22222/1', '22222', 'Buyer A', 'efgh', 'T-Shirt', 'Black', '2026-12-15', 3, 120, 0.25, 980, 250, 200, 230, 125, 125],
        ], null, 'A4');

        $percent = 5;
        $base = [168, 200, 300, 250, 250];
        $saved = array_map(fn(int $q) => $this->withExtra($q, $percent), $base);
        $extra = array_map(fn(int $q) => $this->withExtra($q, $percent) - $q, $base);

        $sheet->setCellValue('A8', "Example: White color, extra_cut_percent = {$percent}");
        $sheet->getStyle('A8')->getFont()->setBold(true);

        $sheet->fromArray(['', ...$sizes], null, 'A9');
        $sheet->fromArray(['Excel-এ লেখা quantity', ...$base], null, 'A10');
        $sheet->fromArray(["Extra ({$percent}%,  Whole number)", ...$extra], null, 'A11');
        $sheet->fromArray(['Save quantity', ...$saved], null, 'A12');
        $sheet->getStyle('A9:F9')->getFont()->setBold(true);
        $sheet->getStyle('A12:F12')->getFont()->setBold(true);

        $notes = [
            '',
            '',
            '',
            '',
        ];
        foreach ($notes as $i => $line) {
            $sheet->setCellValue('A' . (14 + $i), $line);
        }

        $sheet->getColumnDimension('A')->setWidth(34);
        foreach (range('B', 'P') as $col) {
            $sheet->getColumnDimension($col)->setWidth(14);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Parse: Orders                                                       */
    /* ------------------------------------------------------------------ */
    private function parseOrders(array $rows): array
    {
        if (!$rows) {
            $this->errors[] = 'Orders is empty';
            return [];
        }

        $info = []; // column => cell index
        $sizeCols = []; // cell index => size label

        foreach ($rows[0] as $idx => $cell) {
            $text = $this->text($cell);
            if ($text === '') {
                continue;
            }

            $key = $this->headerKey($text, self::ORDER_ALIASES);

            if (in_array($key, self::ORDER_COLUMNS, true)) {
                $info[$key] = $idx;
            } elseif (!in_array($key, self::IGNORED, true)) {
                $sizeCols[$idx] = strtoupper($text);
            }
        }

        $missing = array_diff(self::ORDER_REQUIRED, array_keys($info));
        if ($missing) {
            $this->errors[] = 'Not found these columns' . implode(', ', $missing);
        }
        if (!$sizeCols) {
            $this->errors[] = 'Not found any size';
        }
        $dupSizes = array_keys(array_filter(array_count_values($sizeCols), fn($c) => $c > 1));
        if ($dupSizes) {
            $this->errors[] = 'Same size multiple times ' . implode(', ', $dupSizes);
        }
        if ($this->errors) {
            return [];
        }

        $numeric = [
            'extra_cut_percent' => ['int', 100],
            'max_lay' => ['int', null],
            'cad_consumption' => ['decimal', null],
            'required_fabrics' => ['decimal', null],
        ];

        $result = [];
        $seen = [];

        foreach (array_slice($rows, 1, null, true) as $i => $row) {
            $n = $i + 1; // Excel row (header = 1)

            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $ok = true;
            $rec = ['row' => $n];

            foreach (self::ORDER_TEXT_COLUMNS as $col) {
                $val = isset($info[$col]) ? $this->text($row[$info[$col]] ?? null) : '';

                if ($val === '' && in_array($col, self::ORDER_REQUIRED, true)) {
                    $this->errors[] = "Orders Row {$n}: {$col} empty";
                    $ok = false;
                }
                if (mb_strlen($val) > self::LIMITS[$col]) {
                    $this->errors[] = "Orders Row {$n}: {$col} so long  (Max " . self::LIMITS[$col] . ' characher)';
                    $ok = false;
                }

                $rec[$col] = $val;
            }

            $rec['shipment_date'] = null;
            if (isset($info['shipment_date'])) {
                try {
                    $rec['shipment_date'] = $this->parseDate($row[$info['shipment_date']] ?? null);
                } catch (\InvalidArgumentException $e) {
                    $this->errors[] = "Orders Row {$n}: shipment_date not correct (yyyy-mm-dd)";
                    $ok = false;
                }
            }

            foreach ($numeric as $col => [$type, $max]) {
                $rec[$col] = null;

                if (!isset($info[$col])) {
                    continue;
                }

                $t = $this->text($row[$info[$col]] ?? null);
                if ($t === '') {
                    continue;
                }

                $valid = is_numeric($t)
                    && (float) $t >= 0
                    && ($type !== 'int' || floor((float) $t) == (float) $t)
                    && ($max === null || (float) $t <= $max);

                if (!$valid) {
                    $hint = $type === 'int' ? 'Whole number' : 'number';
                    $limit = $max !== null ? ", Max {$max}" : '';
                    $this->errors[] = "Orders Row {$n}: {$col} '{$t}' not correct ({$hint}{$limit})।";
                    $ok = false;
                    continue;
                }

                $rec[$col] = $type === 'int' ? (int) $t : (float) $t;
            }

            $sizes = [];
            foreach ($sizeCols as $idx => $size) {
                $txt = $this->text($row[$idx] ?? null);
                if ($txt === '') {
                    continue;
                }

                if (!is_numeric($txt) || (float) $txt < 0 || floor((float) $txt) != (float) $txt) {
                    $this->errors[] = "Orders Row {$n}, size {$size}: '{$txt}' number is incorrect";
                    $ok = false;
                    continue;
                }
                if ((int) $txt === 0) {
                    continue; // 0 বাদ
                }
                if (mb_strlen($size) > 30) {
                    $this->errors[] = "Orders Row {$n}: size '{$size}' so long";
                    $ok = false;
                    continue;
                }

                $sizes[$size] = (int) $txt;
            }

            if ($ok && !$sizes) {
                $this->errors[] = "Orders Row {$n}: size wise quanity missing";
                $ok = false;
            }

            if (!$ok) {
                continue;
            }

            $key = mb_strtolower(implode('|', [$rec['skcl_no'], $rec['item_name'], $rec['color']]));
            if (isset($seen[$key])) {
                $this->errors[] = "Orders Row {$n}: {$rec['skcl_no']} / {$rec['item_name']} / {$rec['color']} "
                    . "Row {$seen[$key]}-duplicate";
                continue;
            }
            $seen[$key] = $n;

            $rec['sizes'] = $sizes;
            $result[] = $rec;
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
                if (!isset($buyers[$o['buyer']])) {
                    $buyer = Buyer::firstOrCreate(['name' => $o['buyer']]);
                    if ($buyer->wasRecentlyCreated) {
                        $this->buyersCreated++;
                    }
                    $buyers[$o['buyer']] = $buyer->id;
                }
                $buyerId = $buyers[$o['buyer']];
            }

            $order = Order::firstOrNew([
                'skcl_no' => $o['skcl_no'],
                'item_name' => $o['item_name'],
                'color' => $o['color'],
            ]);
            $isNew = !$order->exists;

            $order->file_no = $o['file_no'];
            $order->style = $o['style'];
            if ($buyerId !== null) {
                $order->buyer_id = $buyerId;
            }
            if ($o['shipment_date'] !== null) {
                $order->shipment_date = $o['shipment_date'];
            }

            foreach (['extra_cut_percent', 'max_lay', 'cad_consumption', 'required_fabrics'] as $col) {
                if ($o[$col] !== null) {
                    $order->{$col} = $o[$col];
                }
            }

            $order->save();

            $percent = (int) ($order->extra_cut_percent ?? 0);

            foreach ($o['sizes'] as $size => $baseQty) {
                OrderDetail::updateOrCreate(
                    ['order_id' => $order->id, 'size' => (string) $size],
                    ['quantity' => $this->withExtra($baseQty, $percent)]
                );
                $this->sizeRows++;
            }

            $order->update(['order_qty' => array_sum($o['sizes'])]);

            $isNew ? $this->ordersCreated++ : $this->ordersUpdated++;
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                             */
    /* ------------------------------------------------------------------ */
    private function summary(): array
    {
        return [
            'ok' => $this->errors === [],
            'buyers_created' => $this->buyersCreated,
            'orders_created' => $this->ordersCreated,
            'orders_updated' => $this->ordersUpdated,
            'size_rows' => $this->sizeRows,
            'error_count' => count($this->errors),
            'errors' => array_slice($this->errors, 0, 50),
        ];
    }

    private function rows(?Worksheet $sheet): array
    {
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

    private function withExtra(int $qty, int $percent): int
    {
        return $qty + intdiv($qty * $percent + 99, 100);
    }
}
