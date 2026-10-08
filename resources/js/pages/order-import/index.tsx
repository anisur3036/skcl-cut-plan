import { type FormEvent, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';

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
  buyers_created: number;
  orders_created: number;
  orders_updated: number;
  size_rows: number;
  fabric_rows: number;
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

const FABRIC_HEADERS = ['skcl_no', 'item_name', 'color', 'fabric_color', 'gsm', 'width', 'quantity_kg'];

const FABRIC_ROWS: string[][] = [
  ['22222/1', 'T-Shirt', 'White', 'White', '180', '72', '1250.50'],
  ['22222/1', 'T-Shirt', 'Black', 'Black', '180', '72', '980'],
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
        setInputKey((k) => k + 1); // ফাইল ইনপুট খালি করে
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
            <Button asChild variant="outline">
              <a href={OrderImportController.template.url()}>Download Template (.xlsx)</a>
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
              <p>নতুন Buyer: {result.buyers_created}টি</p>
              <p>নতুন Order: {result.orders_created}টি</p>
              <p>আপডেট হওয়া Order: {result.orders_updated}টি</p>
              <p>Size রো (যোগ/আপডেট): {result.size_rows}টি</p>
              <p>Fabric রো: {result.fabric_rows}টি</p>
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
          <CardContent className="space-y-6 text-sm">
            <div className="space-y-3">
              <p className="font-semibold">শিট ১: Orders</p>
              <ul className="list-disc space-y-1 pl-5">
                <li>
                  <strong>Extra cut:</strong> size-এর ঘরে <strong>মূল quantity</strong> লিখুন এবং{' '}
                  <code>extra_cut_percent</code> পূর্ণসংখ্যায় দিন (<code>5</code>, <code>5%</code> নয়)। আপলোডের সময় প্রতিটি
                  size-এর quantity-র সঙ্গে extra (quantity × percent ÷ 100, উপরের পূর্ণসংখ্যায়) যোগ হয়ে সেই মান quantity হিসেবে
                  সেভ হয়। যেমন ৫%-এ 168 → 177। একই ফাইল আবার আপলোড করলে একই ফল হয় (দ্বিগুণ হয় না)। percent ফাঁকা রাখলে
                  Order-এর আগের percent ব্যবহার হয় (নতুন Order-এ 0)।
                </li>
                <li>
                  ১ম সারি হলো হেডার। আবশ্যক কলাম: <code>skcl_no, file_no, style, item_name, color</code>।
                  ঐচ্ছিক: <code>buyer, shipment_date</code> (তারিখ <code>yyyy-mm-dd</code>)।
                </li>
                <li>
                  বাকি <strong>প্রতিটি হেডার একটি size</strong> (XS, S, M, L, XL, 2XL, 28 ...)। ঘরে সেই
                  size-এর quantity লিখুন। ফাঁকা বা <code>0</code> ঘর বাদ যায়।
                </li>
                <li>
                  একটি সারি = একটি <code>skcl_no + item_name + color</code>। এটি আগে থাকলে আপডেট হবে, না
                  থাকলে নতুন Order তৈরি হবে। একই ফাইলে একই কম্বিনেশন দুইবার দেওয়া যাবে না।
                </li>
                <li>
                  Buyer না থাকলে নতুন তৈরি হয়। <code>buyer</code> বা <code>shipment_date</code> ফাঁকা রাখলে
                  আগের মান অপরিবর্তিত থাকে। <code>order_qty</code> সাইজগুলোর যোগফল থেকে নিজে হিসাব হয়।
                </li>
                <li>
                  <code>skcl_no</code> (যেমন <code>22222/1</code>) Text হিসেবে রাখুন। টেমপ্লেটে আগে থেকেই Text
                  করা আছে।
                </li>
              </ul>
              <SampleTable headers={ORDER_HEADERS} rows={ORDER_ROWS} />
              <p className="font-medium">Extra cut-এর হিসাব:</p>
              <SampleTable headers={EXTRA_HEADERS} rows={EXTRA_ROWS} />
            </div>

            <div className="space-y-3">
              <p className="font-semibold">শিট ২: Fabrics (ঐচ্ছিক)</p>
              <ul className="list-disc space-y-1 pl-5">
                <li>
                  <code>skcl_no, item_name, color</code> দিয়ে কোন Order-এর ফ্যাব্রিক তা বোঝানো হয়। সেই Order
                  Orders শিটে বা আগে থেকে সিস্টেমে থাকতে হবে।
                </li>
                <li>
                  <code>fabric_color</code> ফ্যাব্রিকের রং, <code>gsm</code> ও <code>width</code> ঐচ্ছিক,{' '}
                  <code>quantity_kg</code> কেজিতে (দশমিক চলবে)।
                </li>
                <li>
                  ফাইলে যে Order-এর ফ্যাব্রিক আছে, তার আগের ফ্যাব্রিক রো মুছে নতুনগুলো বসে। একটি Order-এ
                  একাধিক ফ্যাব্রিক রো দেওয়া যায়।
                </li>
              </ul>
              <SampleTable headers={FABRIC_HEADERS} rows={FABRIC_ROWS} />
            </div>
          </CardContent>
        </Card>
      </div>
    </>
  );
}
