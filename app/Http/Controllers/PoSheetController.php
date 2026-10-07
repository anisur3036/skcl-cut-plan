<?php

namespace App\Http\Controllers;

use App\Exports\PoSheetTemplateExport;
use App\Imports\PoSheetImport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class PoSheetController extends Controller
{
    public function index()
    {
        return Inertia::render('po-sheet/import', [
            'result'      => session('import_result'),
            'infoColumns' => PoSheetImport::INFO_COLUMNS,
        ]);
    }

    public function template()
    {
        return Excel::download(new PoSheetTemplateExport, 'po-sheet-template.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ], [
            'file.required' => 'File name is required',
            'file.mimes'    => 'Extension will be .xlsx or xls',
            'file.max'      => 'Max size 10M',
        ]);

        $import = new PoSheetImport();

        try {
            Excel::import($import, $request->file('file'));
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('po-sheets.index')->with('import_result', [
                'ok'          => false,
                'created'     => 0,
                'updated'     => 0,
                'unchanged'   => 0,
                'error_count' => 1,
                'errors'      => ['File not save!' . $e->getMessage()],
            ]);
        }

        return redirect()->route('po-sheets.index')->with('import_result', $import->summary());
    }
}
