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
            'file.required' => 'একটি Excel ফাইল বাছুন।',
            'file.mimes'    => 'শুধু .xlsx, .xls বা .csv ফাইল দেওয়া যাবে।',
            'file.max'      => 'ফাইলের সাইজ ১০ MB-এর বেশি হতে পারবে না।',
        ]);

        try {
            $result = $service->import($request->file('file')->getRealPath());
        } catch (\Throwable $e) {
            report($e);

            $result = [
                'ok'              => false,
                'orders_affected' => 0,
                'fabric_rows'     => 0,
                'replaced_rows'   => 0,
                'error_count'     => 1,
                'errors'          => ['ফাইলটি পড়া বা সেভ করা যায়নি: ' . $e->getMessage()],
            ];
        }

        return redirect()->route('fabric-import.index')->with('import_result', $result);
    }
}
