import { type FormEvent, useEffect, useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';

import SizeQuantityController from '@/actions/App/Http/Controllers/SizeQuantityController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Table,
  TableBody,
  TableCell,
  TableFooter,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';

type Quantities = Record<string, string>;

type PivotRow = {
  country: string;
  item_name: string;
  color_name: string;
  available: string[];
  quantities: Quantities;
};

type Meta = { file_no: string; order_no: string; style_no: string };

type PageProps = {
  skclNo: string;
  colorName: string;
  colors: string[];
  found: boolean | null;
  meta: Meta | null;
  sizes: string[];
  rows: PivotRow[];
};

type FormShape = { skcl_no: string; rows: PivotRow[] };

const toNum = (v: string | undefined): number => parseInt(v ?? '', 10) || 0;
const digitsOnly = (v: string) => v === '' || /^\d+$/.test(v);

export default function Index({ skclNo, colorName, colors, found, meta, sizes, rows }: PageProps) {
  const [search, setSearch] = useState<string>(skclNo ?? '');
  const [color, setColor] = useState<string>(colorName ?? '');
  const [saved, setSaved] = useState<boolean>(false);

  // Helpers (not saved): fixed qty and ratio per size
  const [fixedQty, setFixedQty] = useState<string>('');
  const [ratios, setRatios] = useState<Record<string, string>>({});

  const { data, setData, post, processing, errors } = useForm<FormShape>({
    skcl_no: skclNo ?? '',
    rows: rows ?? [],
  });

  useEffect(() => {
    setData({ skcl_no: skclNo ?? '', rows: rows ?? [] });
    setRatios(Object.fromEntries(sizes.map((s) => [s, '1'])));
    setSearch(skclNo ?? '');
    setColor(colorName ?? '');
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [skclNo, colorName, rows]);

  const handleSearch = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setSaved(false);
    router.get(
      SizeQuantityController.index.url(),
      { skcl_no: search.trim(), color_name: color.trim() },
      { preserveState: true },
    );
  };

  const setQty = (rowIdx: number, size: string, value: string) => {
    if (!digitsOnly(value)) return;
    setData(
      'rows',
      data.rows.map((r, i) =>
        i === rowIdx ? { ...r, quantities: { ...r.quantities, [size]: value } } : r,
      ),
    );
  };

  // quantity = fixedQty × ratio (for every valid size)
  const fillRow = (r: PivotRow, fixed: number): PivotRow => {
    const quantities = { ...r.quantities };
    r.available.forEach((s) => {
      quantities[s] = String(fixed * toNum(ratios[s]));
    });
    return { ...r, quantities };
  };

  const applyToAll = () => {
    if (fixedQty === '') return;
    const fixed = toNum(fixedQty);
    setData('rows', data.rows.map((r) => fillRow(r, fixed)));
  };

  const applyToRow = (idx: number) => {
    if (fixedQty === '') return;
    const fixed = toNum(fixedQty);
    setData('rows', data.rows.map((r, i) => (i === idx ? fillRow(r, fixed) : r)));
  };

  const handleSave = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    post(SizeQuantityController.store.url(), {
      preserveScroll: true,
      onSuccess: () => setSaved(true),
    });
  };

  const rowTotal = (r: PivotRow): number =>
    sizes.reduce((sum, sz) => sum + toNum(r.quantities[sz]), 0);
  const colTotal = (sz: string): number =>
    data.rows.reduce((sum, r) => sum + toNum(r.quantities[sz]), 0);
  const grandTotal = data.rows.reduce((sum, r) => sum + rowTotal(r), 0);

  return (
    <>
      <Head title="Size Quantity Entry" />
      <div className="mx-auto max-w-6xl space-y-6 p-6">
        {/* Search: SKCL No + Color */}
        <Card>
          <CardHeader>
            <CardTitle>Size wise Quantity Entry</CardTitle>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSearch} className="flex flex-wrap items-end gap-3">
              <div className="grid gap-2">
                <Label htmlFor="skcl_no">SKCL No</Label>
                <Input
                  id="skcl_no"
                  placeholder="e.g. 22222/1"
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  className="w-56"
                />
              </div>
              <div className="grid gap-2">
                <Label htmlFor="color_name">Color (optional)</Label>
                <Input
                  id="color_name"
                  list="color-options"
                  placeholder="e.g. White"
                  value={color}
                  onChange={(e) => setColor(e.target.value)}
                  className="w-48"
                />
                <datalist id="color-options">
                  {colors.map((c) => (
                    <option key={c} value={c} />
                  ))}
                </datalist>
              </div>
              <Button type="submit">Search</Button>
            </form>
            {found === false && (
              <p className="mt-3 text-sm text-destructive">
                No data found for this SKCL No / Color.
              </p>
            )}
          </CardContent>
        </Card>

        {found && meta && (
          <form onSubmit={handleSave}>
            <Card>
              <CardHeader>
                <CardTitle className="text-base">
                  SKCL: {skclNo} | File: {meta.file_no} | Order: {meta.order_no} |
                  Style: {meta.style_no}
                  {colorName && ` | Color: ${colorName}`}
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                {/* Fixed qty toolbar */}
                <div className="flex flex-wrap items-end gap-3 rounded-md border bg-muted/40 p-3">
                  <div className="grid gap-2">
                    <Label htmlFor="fixed_qty">Fixed Qty (× ratio)</Label>
                    <Input
                      id="fixed_qty"
                      inputMode="numeric"
                      placeholder="e.g. 40"
                      className="w-32"
                      value={fixedQty}
                      onChange={(e) =>
                        digitsOnly(e.target.value) && setFixedQty(e.target.value)
                      }
                    />
                  </div>
                  <Button type="button" variant="secondary" onClick={applyToAll}>
                    Apply to all rows
                  </Button>
                  <p className="text-sm text-muted-foreground">
                    Quantity = Fixed Qty × Ratio (e.g. 40 × 3 = 120)
                  </p>
                </div>

                <div className="overflow-x-auto rounded-md border">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Country</TableHead>
                        <TableHead>Item</TableHead>
                        <TableHead>Color</TableHead>
                        {sizes.map((sz) => (
                          <TableHead key={sz} className="text-center">
                            {sz}
                          </TableHead>
                        ))}
                        <TableHead className="text-right">Total</TableHead>
                        <TableHead />
                      </TableRow>
                      {/* Ratio row */}
                      <TableRow className="bg-muted/40">
                        <TableHead colSpan={3} className="text-right">
                          Ratio
                        </TableHead>
                        {sizes.map((sz) => (
                          <TableHead key={sz} className="p-1">
                            <Input
                              inputMode="numeric"
                              className="w-20 text-center"
                              value={ratios[sz] ?? ''}
                              onChange={(e) =>
                                digitsOnly(e.target.value) &&
                                setRatios((p) => ({ ...p, [sz]: e.target.value }))
                              }
                            />
                          </TableHead>
                        ))}
                        <TableHead colSpan={2} />
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {data.rows.map((r, idx) => (
                        <TableRow key={`${r.country}-${r.item_name}-${r.color_name}`}>
                          <TableCell>{r.country}</TableCell>
                          <TableCell>{r.item_name}</TableCell>
                          <TableCell>{r.color_name}</TableCell>
                          {sizes.map((sz) => (
                            <TableCell key={sz} className="p-1">
                              <Input
                                inputMode="numeric"
                                className="w-20 text-center"
                                value={r.quantities[sz] ?? ''}
                                disabled={!r.available.includes(sz)}
                                onChange={(e) => setQty(idx, sz, e.target.value)}
                              />
                            </TableCell>
                          ))}
                          <TableCell className="text-right font-medium">
                            {rowTotal(r)}
                          </TableCell>
                          <TableCell className="p-1">
                            <Button
                              type="button"
                              size="sm"
                              variant="outline"
                              onClick={() => applyToRow(idx)}
                            >
                              Apply
                            </Button>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                    <TableFooter>
                      <TableRow>
                        <TableCell colSpan={3} className="font-semibold">
                          Total
                        </TableCell>
                        {sizes.map((sz) => (
                          <TableCell key={sz} className="text-center font-semibold">
                            {colTotal(sz)}
                          </TableCell>
                        ))}
                        <TableCell className="text-right font-bold">{grandTotal}</TableCell>
                        <TableCell />
                      </TableRow>
                    </TableFooter>
                  </Table>
                </div>

                {Object.keys(errors).length > 0 && (
                  <p className="text-sm text-destructive">
                    There are errors in the input. Please check the numbers.
                  </p>
                )}

                <div className="flex items-center gap-3">
                  <Button type="submit" disabled={processing}>
                    {processing ? 'Saving...' : 'Save'}
                  </Button>
                  {saved && <span className="text-sm text-green-600">Saved ✔</span>}
                </div>
              </CardContent>
            </Card>
          </form>
        )}
      </div>
    </>
  );
}
