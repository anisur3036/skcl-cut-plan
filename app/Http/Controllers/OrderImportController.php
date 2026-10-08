<?php

namespace App\Http\Controllers;

use App\Services\OrderImportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderImportController extends Controller
{
    public function index()
    {
        return Inertia::render('order-import/index', [
            'result' => session('import_result'),
        ]);
    }

    public function template(OrderImportService $service)
    {
        return $service->templateResponse();
    }

    public function import(Request $request, OrderImportService $service)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ], [
            'file.required' => 'Select a excel file',
            'file.mimes'    => 'Extension will be xls, xlsx',
            'file.max'      => 'Max file size 10M',
        ]);

        try {
            $result = $service->import($request->file('file')->getRealPath());
        } catch (\Throwable $e) {
            report($e);

            $result = [
                'ok'             => false,
                'buyers_created' => 0,
                'orders_created' => 0,
                'orders_updated' => 0,
                'size_rows'      => 0,
                'error_count'    => 1,
                'errors'         => ['Unreadable file: ' . $e->getMessage()],
            ];
        }

        return redirect()->route('order-import.index')->with('import_result', $result);
    }
}
