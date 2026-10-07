import { type FormEvent, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';

import PoSheetController from '@/actions/App/Http/Controllers/PoSheetController';
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
  created: number;
  updated: number;
  unchanged: number;
  error_count: number;
  errors: string[];
};

type PageProps = {
  result: ImportResult | null;
  infoColumns: string[];
};

const SAMPLE_SIZES = ['XS', 'S', 'M', 'L', 'XL'];

const SAMPLE_ROWS: string[][] = [
  ['22222', '22222/1', 'abc', 'efgh', 'BD', 'T-Shirt', 'White', '168', '200', '300', '250', '250'],
  ['22222', '22222/1', 'abc', 'efgh', 'India', 'T-Shirt', 'Black', '250', '200', '230', '125', '125'],
];

export default function Import({ result, infoColumns }: PageProps) {
  const [inputKey, setInputKey] = useState<number>(0);

  const { data, setData, post, processing, errors } = useForm<{ file: File | null }>({
    file: null,
  });

  const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (!data.file) return;

    post(PoSheetController.import.url(), {
      forceFormData: true,
      preserveScroll: true,
      onFinish: () => {
        setData('file', null);
        setInputKey((k) => k + 1);
      },
    });
  };

  const headers = [...infoColumns, ...SAMPLE_SIZES];

  return (
    <>
      <Head title="PO Sheet Import" />
      <div className="mx-auto max-w-6xl space-y-6 p-6">
        {/* Upload */}
        <Card>
          <CardHeader className="flex flex-row items-center justify-between gap-3">
            <CardTitle>Import PO Sheet (Excel)</CardTitle>
            <Button asChild variant="outline">
              <a href={PoSheetController.template.url()}>Download Template (.xlsx)</a>
            </Button>
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
                {errors.file && (
                  <p className="text-sm text-destructive">{errors.file}</p>
                )}
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
              <p className="font-semibold text-green-600">Import done.</p>
              <p>  New row added: {result.created} row</p>
              <p>quantity added: {result.updated} row</p>
              <p>Unchange: {result.unchanged} row</p>
            </CardContent>
          </Card>
        )}

        {result && !result.ok && (
          <Card className="border-destructive">
            <CardContent className="space-y-2 pt-6 text-sm">
              <p className="font-semibold text-destructive">
                Error: {result.error_count}
              </p>
              <ul className="list-disc space-y-1 pl-5">
                {result.errors.map((err, i) => (
                  <li key={i}>{err}</li>
                ))}
              </ul>
              {result.error_count > result.errors.length && (
                <p className="text-muted-foreground">
                  Error coutn: {result.error_count - result.errors.length}
                </p>
              )}
            </CardContent>
          </Card>
        )}

        {/* Format guide */}
        <Card>
          <CardHeader>
            <CardTitle className="text-base">How Excel arrange</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4 text-sm">
            <ul className="list-disc space-y-1 pl-5">
            </ul>

            <div>
              <p className="mb-2 font-medium">Example:</p>
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
                    {SAMPLE_ROWS.map((row, i) => (
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
            </div>
          </CardContent>
        </Card>
      </div>
    </>
  );
}
