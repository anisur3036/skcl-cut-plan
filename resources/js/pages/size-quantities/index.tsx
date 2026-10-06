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

type Meta = {
  file_no: string;
  order_no: string;
  style_no: string;
};

type PageProps = {
  skclNo: string;
  found: boolean | null;
  meta: Meta | null;
  sizes: string[];
  rows: PivotRow[];
};

type FormShape = {
  skcl_no: string;
  rows: PivotRow[];
};

const toNum = (v: string | undefined): number => parseInt(v ?? '', 10) || 0;

export default function Index({ skclNo, found, meta, sizes, rows }: PageProps) {
  const [search, setSearch] = useState<string>(skclNo ?? '');
  const [saved, setSaved] = useState<boolean>(false);

  const { data, setData, post, processing, errors } = useForm<FormShape>({
    skcl_no: skclNo ?? '',
    rows: rows ?? [],
  });

  // Reset the form with server data after a new search or a save
  useEffect(() => {
    setData({ skcl_no: skclNo ?? '', rows: rows ?? [] });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [skclNo, rows]);

  const handleSearch = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setSaved(false);
    router.get(
      SizeQuantityController.index.url(),
      { skcl_no: search.trim() },
      { preserveState: true },
    );
  };

  const setQty = (rowIdx: number, size: string, value: string) => {
    if (value !== '' && !/^\d+$/.test(value)) return; // digits only
    setData(
      'rows',
      data.rows.map((r, i) =>
        i === rowIdx
          ? { ...r, quantities: { ...r.quantities, [size]: value } }
          : r,
      ),
    );
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
      <div className="mx-auto max-w-5xl space-y-6 p-6">
        {/* Search */}
        <Card>
          <CardHeader>
            <CardTitle>Size wise Quantity Entry</CardTitle>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSearch} className="flex items-end gap-3">
              <div className="grid gap-2">
                <Label htmlFor="skcl_no">SKCL No</Label>
                <Input
                  id="skcl_no"
                  placeholder="e.g. 22222/1"
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  className="w-64"
                />
              </div>
              <Button type="submit">Search</Button>
            </form>
            {found === false && (
              <p className="mt-3 text-sm text-destructive">
                No data found for this SKCL No.
              </p>
            )}
          </CardContent>
        </Card>

        {/* Pivot form */}
        {found && meta && (
          <form onSubmit={handleSave}>
            <Card>
              <CardHeader>
                <CardTitle className="text-base">
                  SKCL: {skclNo} | File: {meta.file_no} | Order:{' '}
                  {meta.order_no} | Style: {meta.style_no}
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
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
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {data.rows.map((r, idx) => (
                        <TableRow
                          key={`${r.country}-${r.item_name}-${r.color_name}`}
                        >
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
                                onChange={(e) =>
                                  setQty(idx, sz, e.target.value)
                                }
                              />
                            </TableCell>
                          ))}
                          <TableCell className="text-right font-medium">
                            {rowTotal(r)}
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
                          <TableCell
                            key={sz}
                            className="text-center font-semibold"
                          >
                            {colTotal(sz)}
                          </TableCell>
                        ))}
                        <TableCell className="text-right font-bold">
                          {grandTotal}
                        </TableCell>
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
                  {saved && (
                    <span className="text-sm text-green-600">Saved ✔</span>
                  )}
                </div>
              </CardContent>
            </Card>
          </form>
        )}
      </div>
    </>
  );
}
