import { type FormEvent, useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';

import OrderImportController from '@/actions/App/Http/Controllers/OrderImportController';
import FabricImportController from '@/actions/App/Http/Controllers/FabricImportController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';

type ImportResult = {
  ok: boolean;
  buyers_created: number;
  orders_created: number;
  orders_updated: number;
  size_rows: number;
  error_count: number;
  errors: string[];
};

type PageProps = {
  result: ImportResult | null;
};

const ORDER_HEADERS = [
  'skcl_no',
  'file_no',
  'buyer',
  'style',
  'item_name',
  'color',
  'shipment_date',
  'XS',
  'S',
  'M',
  'L',
  'XL',
];

const ORDER_ROWS: string[][] = [
  ['22222/1', '22222', 'Buyer A', 'efgh', 'T-Shirt', 'White', '2026-12-15', '168', '200', '300', '250', '250'],
  ['22222/1', '22222', 'Buyer A', 'efgh', 'T-Shirt', 'Black', '2026-12-15', '250', '200', '230', '125', '125'],
];

const EXTRA_HEADERS = ['White, extra_cut_percent = 5', 'XS', 'S', 'M', 'L', 'XL'];

const EXTRA_ROWS: string[][] = [
  ['Excel-এ লেখা quantity', '168', '200', '300', '250', '250'],
  ['Extra (5%, উপরের পূর্ণসংখ্যায়)', '9', '10', '15', '13', '13'],
  ['সেভ হওয়া quantity', '177', '210', '315', '263', '263'],
];


function SampleTable({ headers, rows }: { headers: string[]; rows: string[][] }) {
  return (
    <div className="overflow-x-auto rounded-md border">
      <Table>
        <TableHeader>
          <TableRow>
            {headers.map((h) => (
              <TableHead key={h} className="whitespace-nowrap">
                {h}
              </TableHead>
            ))}
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.map((row, i) => (
            <TableRow key={i}>
              {row.map((cell, j) => (
                <TableCell key={j} className="whitespace-nowrap">
                  {cell}
                </TableCell>
              ))}
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  );
}

export default function Index({ result }: PageProps) {
  const [inputKey, setInputKey] = useState<number>(0);

  const { data, setData, post, processing, errors } = useForm<{ file: File | null }>({
    file: null,
  });

  const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (!data.file) return;

    post(OrderImportController.import.url(), {
      forceFormData: true,
      preserveScroll: true,
      onFinish: () => {
        setData('file', null);
        setInputKey((k) => k + 1);
      },
    });
  };

  return (
    <>
      <Head title="Order Import" />
      <div className="mx-auto max-w-6xl space-y-6 p-6">
        {/* Upload */}
        <Card>
          <CardHeader className="flex flex-row items-center justify-between gap-3">
            <CardTitle>Import Orders (Excel)</CardTitle>
            <div className="flex gap-2">
              <Button asChild variant="outline">
                <Link href={FabricImportController.index.url()}>Fabrics Import</Link>
              </Button>
              <Button asChild variant="outline">
                <a href={OrderImportController.template.url()}>Download Template (.xlsx)</a>
              </Button>
            </div>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSubmit} className="flex flex-wrap items-end gap-3">
              <div className="grid gap-2">
                <Label htmlFor="file">Excel file (.xlsx, .xls, .csv)</Label>
                <Input
                  key={inputKey}
                  id="file"
                  type="file"
                  accept=".xlsx,.xls,.csv"
                  className="w-80"
                  onChange={(e) => setData('file', e.target.files?.[0] ?? null)}
                />
                {errors.file && <p className="text-sm text-destructive">{errors.file}</p>}
              </div>
              <Button type="submit" disabled={processing || !data.file}>
                {processing ? 'Importing...' : 'Import'}
              </Button>
            </form>
          </CardContent>
        </Card>

        {/* Result */}
        {result && result.ok && (
          <Card className="border-green-600">
            <CardContent className="space-y-1 pt-6 text-sm">
              <p className="font-semibold text-green-600">Import সফল হয়েছে ✔</p>
              <p>New Buyer: {result.buyers_created} pcs</p>
              <p>New Order: {result.orders_created} pcs</p>
              <p>Update Order: {result.orders_updated} pcs</p>
              <p>Size add or update: {result.size_rows} pcs</p>
            </CardContent>
          </Card>
        )}

        {result && !result.ok && (
          <Card className="border-destructive">
            <CardContent className="space-y-2 pt-6 text-sm">
              <p className="font-semibold text-destructive">
               No data will import: {result.error_count}
              </p>
              <ul className="list-disc space-y-1 pl-5">
                {result.errors.map((err, i) => (
                  <li key={i}>{err}</li>
                ))}
              </ul>
              {result.error_count > result.errors.length && (
                <p className="text-muted-foreground">
                  More error: {result.error_count - result.errors.length} please check the error.
                </p>
              )}
            </CardContent>
          </Card>
        )}
      </div>
    </>
  );
}
