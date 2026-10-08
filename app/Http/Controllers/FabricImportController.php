<?php

namespace App\Http\Controllers;

use App\Services\FabricImportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FabricImportController extends Controller
{
    public function index()
    {
        return Inertia::render('fabric-import/index', [
            'result' => session('import_result'),
        ]);
    }

    public function template(FabricImportService $service)
    {
        return $service->templateResponse();
    }

    public function import(Request $request, FabricImportService $service)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ], [
            'file.required' => 'Select a excel file',
            'file.mimes' => 'File extension will be xlsx, xls, csv',
            'file.max' => 'Max size 10 M',
        ]);

        try {
            $result = $service->import($request->file('file')->getRealPath());
        } catch (\Throwable $e) {
            report($e);

            $result = [
                'ok' => false,
                'orders_affected' => 0,
                'fabric_rows' => 0,
                'replaced_rows' => 0,
                'error_count' => 1,
                'errors' => ['File not read or save' . $e->getMessage()],
            ];
        }

        return redirect()->route('fabric-import.index')->with('import_result', $result);
    }
}
