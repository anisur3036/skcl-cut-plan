import { type FormEvent, useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';

import FabricImportController from '@/actions/App/Http/Controllers/FabricImportController';
import OrderImportController from '@/actions/App/Http/Controllers/OrderImportController';
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
  orders_affected: number;
  fabric_rows: number;
  replaced_rows: number;
  error_count: number;
  errors: string[];
};

type PageProps = {
  result: ImportResult | null;
};

const HEADERS = ['skcl_no', 'item_name', 'color', 'fabric_color', 'gsm', 'width', 'quantity_kg'];

const ROWS: string[][] = [
  ['22222/1', 'T-Shirt', 'White', 'White', '180', '72', '1250.50'],
  ['22222/1', 'T-Shirt', 'White', 'Rib White', '220', '60', '85'],
  ['22222/1', 'T-Shirt', 'Black', 'Black', '180', '72', '980'],
];

export default function Index({ result }: PageProps) {
  const [inputKey, setInputKey] = useState<number>(0);

  const { data, setData, post, processing, errors } = useForm<{ file: File | null }>({
    file: null,
  });

  const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (!data.file) return;

    post(FabricImportController.import.url(), {
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
      <Head title="Fabrics Import" />
      <div className="mx-auto max-w-6xl space-y-6 p-6">
        {/* Upload */}
        <Card>
          <CardHeader className="flex flex-row items-center justify-between gap-3">
            <CardTitle>Import Fabrics (Excel)</CardTitle>
            <div className="flex gap-2">
              <Button asChild variant="outline">
                <Link href={OrderImportController.index.url()}>← Orders Import</Link>
              </Button>
              <Button asChild variant="outline">
                <a href={FabricImportController.template.url()}>Download Template (.xlsx)</a>
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
              <p className="font-semibold text-green-600">Import done ✔</p>
              <p>Order: {result.orders_affected} pcs</p>
              <p>New row: {result.fabric_rows} pcs</p>
            </CardContent>
          </Card>
        )}

        {result && !result.ok && (
          <Card className="border-destructive">
            <CardContent className="space-y-2 pt-6 text-sm">
              <p className="font-semibold text-destructive">
                No data import: {result.error_count}
              </p>
              <ul className="list-disc space-y-1 pl-5">
                {result.errors.map((err, i) => (
                  <li key={i}>{err}</li>
                ))}
              </ul>
              {result.error_count > result.errors.length && (
                <p className="text-muted-foreground">
                  more errors {result.error_count - result.errors.length}. Please fix these.
                </p>
              )}
            </CardContent>
          </Card>
        )}
      </div>
    </>
  );
}
