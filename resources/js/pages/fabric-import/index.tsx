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
        setInputKey((k) => k + 1); // ফাইল ইনপুট খালি করে
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
              <p className="font-semibold text-green-600">Import সফল হয়েছে ✔</p>
              <p>ফ্যাব্রিক বসেছে এমন Order: {result.orders_affected}টি</p>
              <p>নতুন ফ্যাব্রিক রো: {result.fabric_rows}টি</p>
              <p>আগের মুছে যাওয়া ফ্যাব্রিক রো: {result.replaced_rows}টি</p>
            </CardContent>
          </Card>
        )}

        {result && !result.ok && (
          <Card className="border-destructive">
            <CardContent className="space-y-2 pt-6 text-sm">
              <p className="font-semibold text-destructive">
                কোনো ডাটা ইমপোর্ট হয়নি। ভুল পাওয়া গেছে: {result.error_count}টি
              </p>
              <ul className="list-disc space-y-1 pl-5">
                {result.errors.map((err, i) => (
                  <li key={i}>{err}</li>
                ))}
              </ul>
              {result.error_count > result.errors.length && (
                <p className="text-muted-foreground">
                  ... আরও {result.error_count - result.errors.length}টি ভুল আছে। আগে এগুলো ঠিক করে
                  আবার চেষ্টা করুন।
                </p>
              )}
            </CardContent>
          </Card>
        )}

        {/* Format guide */}
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Excel কীভাবে সাজাবেন</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4 text-sm">
            <ul className="list-disc space-y-1 pl-5">
              <li>
                ১ম সারি হলো হেডার। আবশ্যক কলাম:{' '}
                <code>skcl_no, item_name, color, fabric_color, quantity_kg</code>। ঐচ্ছিক:{' '}
                <code>gsm, width</code>।
              </li>
              <li>
                <code>skcl_no + item_name + color</code> দিয়ে কোন Order-এর ফ্যাব্রিক তা বোঝানো হয়। সেই Order
                আগে <strong>Orders Import</strong> দিয়ে সিস্টেমে থাকতে হবে, নইলে সারি নম্বরসহ এরর আসবে।
              </li>
              <li>
                <code>fabric_color</code> ফ্যাব্রিকের রং, এটি Order-এর <code>color</code> থেকে আলাদা হতে পারে।{' '}
                <code>quantity_kg</code> কেজিতে (দশমিক চলবে), <code>gsm</code> পূর্ণসংখ্যা, <code>width</code>{' '}
                সংখ্যা।
              </li>
              <li>একটি Order-এ একাধিক ফ্যাব্রিক রো দেওয়া যায় (নিচের উদাহরণে White-এ দুটি)।</li>
              <li>
                <strong>ফাইলে যে Order আছে, তার আগের সব ফ্যাব্রিক মুছে ফাইলের রোগুলো বসে।</strong> ফাইলে না
                থাকা Order-এর ফ্যাব্রিক অপরিবর্তিত থাকে। একটি ভুল থাকলে কিছুই সেভ হয় না।
              </li>
            </ul>

            <div className="overflow-x-auto rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
                    {HEADERS.map((h) => (
                      <TableHead key={h} className="whitespace-nowrap">
                        {h}
                      </TableHead>
                    ))}
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {ROWS.map((row, i) => (
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
          </CardContent>
        </Card>
      </div>
    </>
  );
}
