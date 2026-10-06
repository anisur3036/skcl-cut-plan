<?php

namespace App\Http\Controllers;

use App\Models\PoSheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PoSheetController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'country']);

        $poSheets = PoSheet::query()
            ->search($filters['search'] ?? null)
            ->when($filters['country'] ?? null, fn ($query, $country) => $query->where('country', $country))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('po-sheets/index', [
            'poSheets' => $poSheets,
            'filters' => $filters,
            'countries' => PoSheet::query()
                ->select('country')
                ->distinct()
                ->orderBy('country')
                ->pluck('country'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('po-sheets/create');
    }

    public function store(Request $request): RedirectResponse
    {
        $poSheet = PoSheet::create($this->validated($request));

        return to_route('po-sheets.show', $poSheet)
            ->with('success', 'PO sheet created.');
    }

    public function show(PoSheet $poSheet): Response
    {
        return Inertia::render('po-sheets/show', [
            'poSheet' => $poSheet,
        ]);
    }

    public function edit(PoSheet $poSheet): Response
    {
        return Inertia::render('po-sheets/edit', [
            'poSheet' => $poSheet,
        ]);
    }

    public function update(Request $request, PoSheet $poSheet): RedirectResponse
    {
        $poSheet->update($this->validated($request));

        return to_route('po-sheets.show', $poSheet)
            ->with('success', 'PO sheet updated.');
    }

    public function destroy(PoSheet $poSheet): RedirectResponse
    {
        $poSheet->delete();

        return to_route('po-sheets.index')
            ->with('success', 'PO sheet deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'file_no' => ['required', 'string', 'max:255'],
            'skcl_no' => ['required', 'string', 'max:255'],
            'order_no' => ['required', 'string', 'max:255'],
            'style_no' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'item_name' => ['required', 'string', 'max:255'],
            'color_name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:0'],
        ]);
    }
}
