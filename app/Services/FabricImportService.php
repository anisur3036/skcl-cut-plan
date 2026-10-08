<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderFabric;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FabricImportService
{
    // skcl_no + item_name + color = কোন Order-এর ফ্যাব্রিক, বাকিগুলো ফ্যাব্রিকের তথ্য
    public const COLUMNS = ['skcl_no', 'item_name', 'color', 'fabric_color', 'gsm', 'width', 'quantity_kg'];
    private const REQUIRED = ['skcl_no', 'item_name', 'color', 'fabric_color', 'quantity_kg'];
    private const ALIASES = [
        'color_name'  => 'color',
        'order_color' => 'color',
        'quantity'    => 'quantity_kg',
        'kg'          => 'quantity_kg',
    ];

    // ডাটাবেস কলামের সর্বোচ্চ দৈর্ঘ্য
    private const LIMITS = [
        'skcl_no'      => 50,
        'item_name'    => 100,
        'color'        => 60,
        'fabric_color' => 60,
    ];

    private int $ordersAffected = 0;
    private int $fabricRows     = 0;
    private int $replacedRows   = 0;

    /** @var string[] */
    private array $errors = [];

    /* ------------------------------------------------------------------ */
    /*  Import                                                              */
    /* ------------------------------------------------------------------ */
    public function import(string $path): array
    {
        $spreadsheet = IOFactory::load($path);

        // "Fabrics" নামের শিট, না থাকলে প্রথম শিট (Example শিট কখনো পড়া হয় না)
        $sheet   = $spreadsheet->getSheetByName('Fabrics') ?? $spreadsheet->getSheet(0);
        $records = $this->parse($this->rows($sheet));

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        if (! $this->errors && ! $records) {
            $this->errors[] = 'ইমপোর্ট করার মতো কোনো ডাটা পাওয়া যায়নি।';
        }

        if (! $this->errors) {
            $records = $this->attachOrders($records);
        }

        if (! $this->errors) {
            DB::transaction(fn() => $this->save($records));
        }

        return $this->summary();
    }

    /* ------------------------------------------------------------------ */
    /*  Template                                                            */
    /* ------------------------------------------------------------------ */
    public function templateResponse(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();

        /* ---------------- শিট ১: Fabrics ---------------- */
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Fabrics');
        $this->writeHeader($sheet, self::COLUMNS);
        $sheet->freezePane('A2');

        // A–D Text (12/1 তারিখ হয়ে যাওয়া ঠেকাতে)
        $sheet->getStyle('A:D')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('E:E')->getNumberFormat()->setFormatCode('0');
        $sheet->getStyle('F:F')->getNumberFormat()->setFormatCode('0.00');
        $sheet->getStyle('G:G')->getNumberFormat()->setFormatCode('0.00');

        $this->addMinZeroValidation($sheet, 'E2:E5000', DataValidation::TYPE_WHOLE, 'gsm', 'gsm পূর্ণসংখ্যা লিখুন।');
        $this->addMinZeroValidation($sheet, 'F2:F5000', DataValidation::TYPE_DECIMAL, 'width', 'width সংখ্যা লিখুন।');
        $this->addMinZeroValidation(
            $sheet,
            'G2:G5000',
            DataValidation::TYPE_DECIMAL,
            'quantity_kg',
            'কেজিতে সংখ্যা লিখুন (দশমিক চলবে)।'
        );

        /* ---------------- শিট ২: Example (ইমপোর্টে পড়া হয় না) ---------------- */
        $this->buildExampleSheet($spreadsheet->createSheet());

        $spreadsheet->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'fabric-import-template.xlsx', [
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

    /** সংখ্যার কলামে "≥ 0" যাচাই ($type: TYPE_WHOLE বা TYPE_DECIMAL) */
    private function addMinZeroValidation(
        Worksheet $sheet,
        string $range,
        string $type,
        string $title,
        string $message
    ): void {
        $v = new DataValidation();
        $v->setType($type);
        $v->setErrorStyle(DataValidation::STYLE_STOP);
        $v->setOperator(DataValidation::OPERATOR_GREATERTHANOREQUAL);
        $v->setFormula1('0');
        $v->setAllowBlank(true);
        $v->setShowErrorMessage(true);
        $v->setErrorTitle($title);
        $v->setError($message);
        $v->setShowInputMessage(true);
        $v->setPromptTitle($title);
        $v->setPrompt($message);

        $sheet->setDataValidation($range, $v);
    }

    /** নমুনা শিট: ইমপোর্টে পড়া হয় না, শুধু দেখার জন্য */
    private function buildExampleSheet(Worksheet $sheet): void
    {
        $sheet->setTitle('Example');

        $sheet->setCellValue('A1', 'নমুনা শিট: ইমপোর্টে এই শিট পড়া হয় না। আপনার ডাটা "Fabrics" শিটে দিন।');
        $sheet->getStyle('A1')->getFont()->setBold(true);

        $sheet->fromArray(self::COLUMNS, null, 'A3');
        $sheet->getStyle('A3:G3')->getFont()->setBold(true);

        $sheet->fromArray([
            ['22222/1', 'T-Shirt', 'White', 'White', 180, 72, 1250.5],
            ['22222/1', 'T-Shirt', 'White', 'Rib White', 220, 60, 85],
            ['22222/1', 'T-Shirt', 'Black', 'Black', 180, 72, 980],
        ], null, 'A4');

        $notes = [
            'skcl_no, item_name, color দিয়ে বোঝানো হয় কোন Order-এর ফ্যাব্রিক। সেই Order আগে Order Import দিয়ে থাকতে হবে।',
            'fabric_color হলো ফ্যাব্রিকের রং (Order-এর color থেকে আলাদা হতে পারে)। একটি Order-এ একাধিক ফ্যাব্রিক রো দেওয়া যায়।',
            'quantity_kg কেজিতে (দশমিক চলবে)। gsm ও width ঐচ্ছিক।',
            'ফাইলে যে Order আছে তার আগের সব ফ্যাব্রিক মুছে ফাইলের রোগুলো বসবে। ফাইলে না থাকা Order অপরিবর্তিত থাকবে।',
        ];
        foreach ($notes as $i => $line) {
            $sheet->setCellValue('A' . (8 + $i), $line);
        }

        $sheet->getColumnDimension('A')->setWidth(14);
        foreach (range('B', 'G') as $col) {
            $sheet->getColumnDimension($col)->setWidth(16);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Parse                                                               */
    /* ------------------------------------------------------------------ */
    private function parse(array $rows): array
    {
        $hasAny = false;
        foreach ($rows as $r) {
            if (! $this->rowIsEmpty($r)) {
                $hasAny = true;
                break;
            }
        }
        if (! $hasAny) {
            $this->errors[] = 'ফাইলটি খালি।';
            return [];
        }

        $idxMap = [];
        foreach ($rows[0] as $idx => $cell) {
            $text = $this->text($cell);
            if ($text === '') {
                continue;
            }
            $key = $this->headerKey($text);
            if (in_array($key, self::COLUMNS, true)) {
                $idxMap[$key] = $idx;
            }
        }

        $missing = array_diff(self::REQUIRED, array_keys($idxMap));
        if ($missing) {
            $this->errors[] = 'এই কলামগুলো পাওয়া যায়নি: ' . implode(', ', $missing);
            return [];
        }

        $result = [];

        foreach (array_slice($rows, 1, null, true) as $i => $row) {
            $n = $i + 1; // Excel row (header = 1)

            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $ok  = true;
            $rec = ['row' => $n];

            foreach (['skcl_no', 'item_name', 'color', 'fabric_color'] as $col) {
                $val = $this->text($row[$idxMap[$col]] ?? null);

                if ($val === '') {
                    $this->errors[] = "Row {$n}: {$col} ফাঁকা।";
                    $ok = false;
                }
                if (mb_strlen($val) > self::LIMITS[$col]) {
                    $this->errors[] = "Row {$n}: {$col} অনেক লম্বা (সর্বোচ্চ " . self::LIMITS[$col] . ' অক্ষর)।';
                    $ok = false;
                }
                $rec[$col] = $val;
            }

            $rec['gsm'] = null;
            if (isset($idxMap['gsm'])) {
                $t = $this->text($row[$idxMap['gsm']] ?? null);
                if ($t !== '') {
                    if (! is_numeric($t) || (float) $t < 0 || floor((float) $t) != (float) $t) {
                        $this->errors[] = "Row {$n}: gsm '{$t}' সঠিক পূর্ণসংখ্যা নয়।";
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
                        $this->errors[] = "Row {$n}: width '{$t}' সঠিক সংখ্যা নয়।";
                        $ok = false;
                    } else {
                        $rec['width'] = (float) $t;
                    }
                }
            }

            $kg = $this->text($row[$idxMap['quantity_kg']] ?? null);
            if ($kg === '') {
                $this->errors[] = "Row {$n}: quantity_kg ফাঁকা।";
                $ok = false;
            } elseif (! is_numeric($kg) || (float) $kg < 0) {
                $this->errors[] = "Row {$n}: quantity_kg '{$kg}' সঠিক সংখ্যা নয়।";
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

    private function attachOrders(array $records): array
    {
        $skclList = array_values(array_unique(array_column($records, 'skcl_no')));

        $orders = Order::whereIn('skcl_no', $skclList)
            ->get(['id', 'skcl_no', 'item_name', 'color'])
            ->keyBy(fn($o) => $this->orderKey($o->skcl_no, $o->item_name, $o->color));

        foreach ($records as $idx => $r) {
            $order = $orders->get($this->orderKey($r['skcl_no'], $r['item_name'], $r['color']));

            if (! $order) {
                $this->errors[] = "Row {$r['row']}: {$r['skcl_no']} / {$r['item_name']} / {$r['color']} "
                    . 'নামে কোনো Order নেই (আগে Order Import করুন)।';
                continue;
            }

            $records[$idx]['order_id'] = $order->id;
        }

        return $records;
    }

    /* ------------------------------------------------------------------ */
    /*  Save                                                                */
    /* ------------------------------------------------------------------ */
    private function save(array $records): void
    {
        // order অনুযায়ী ভাগ: ফাইলে যে Order আছে তার ফ্যাব্রিক নতুন করে বসে
        $byOrder = [];
        foreach ($records as $r) {
            $byOrder[$r['order_id']][] = $r;
        }

        foreach ($byOrder as $orderId => $rows) {
            $this->replacedRows += OrderFabric::where('order_id', $orderId)->delete();

            OrderFabric::insert(array_map(fn($r) => [
                'order_id'   => $orderId,
                'color'      => $r['fabric_color'],
                'gsm'        => $r['gsm'],
                'width'      => $r['width'],
                'quantity'   => $r['quantity_kg'],
                'created_at' => now(),
                'updated_at' => now(),
            ], $rows));

            $this->fabricRows += count($rows);
            $this->ordersAffected++;
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                             */
    /* ------------------------------------------------------------------ */
    private function summary(): array
    {
        return [
            'ok'              => $this->errors === [],
            'orders_affected' => $this->ordersAffected,
            'fabric_rows'     => $this->fabricRows,
            'replaced_rows'   => $this->replacedRows,
            'error_count'     => count($this->errors),
            'errors'          => array_slice($this->errors, 0, 50),
        ];
    }

    private function rows(?Worksheet $sheet): array
    {
        return $sheet ? $sheet->toArray(null, true, false, false) : [];
    }

    private function headerKey(string $text): string
    {
        $key = strtolower(str_replace([' ', '-'], '_', $text));

        return self::ALIASES[$key] ?? $key;
    }

    private function orderKey(string $skcl, string $item, string $color): string
    {
        return mb_strtolower($skcl . '|' . $item . '|' . $color);
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
}
