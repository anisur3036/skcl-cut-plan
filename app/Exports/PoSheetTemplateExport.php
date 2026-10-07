<?php

namespace App\Exports;

use App\Imports\PoSheetImport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PoSheetTemplateExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles
{
    public function headings(): array
    {
        return [...PoSheetImport::INFO_COLUMNS, 'XS', 'S', 'M', 'L', 'XL', '2XL', '3XL'];
    }

    public function array(): array
    {
        return [];
    }

    public function styles(Worksheet $sheet): array
    {
        // প্রথম ৭টি কলাম (A–G) Text ফরম্যাটে, যাতে 12/1 তারিখ না হয়ে যায়
        $sheet->getStyle('A2:G5000')->getNumberFormat()->setFormatCode('@');

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
