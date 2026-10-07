import { type FormEvent, useEffect, useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';

import MarkerPlanController from '@/actions/App/Http/Controllers/MarkerPlanController';
import PivotForm from '@/components/size-quantity/pivot-form';
import StatusSelect from '@/components/size-quantity/status-select';
import TableSelect from '@/components/size-quantity/table-select';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
  FormShape,
  Meta,
  PivotChange,
  PivotRow,
  StatusOption,
  TableOption,
} from '@/types/size-quantity';

type PageProps = {
  skclNo: string;
  colorName: string;
  tables: TableOption[];
  statuses: StatusOption[];
  colors: string[];
  found: boolean | null;
  meta: Meta | null;
  sizes: string[];
  rows: PivotRow[];
};

const defaultRatios = (sizes: string[]): Record<string, string> =>
  Object.fromEntries(sizes.map((s) => [s, '1']));

export default function Create({
  skclNo,
  colorName,
  tables,
  statuses,
  colors,
  found,
  meta,
  sizes,
  rows,
}: PageProps) {
  const [search, setSearch] = useState<string>(skclNo ?? '');
  const [color, setColor] = useState<string>(colorName ?? '');

  const { data, setData, post, processing, errors } = useForm<FormShape>({
    skcl_no: skclNo ?? '',
    table_no_id: '',
    status: statuses[0]?.value ?? 'draft',
    fixed_qty: '',
    ratios: defaultRatios(sizes),
    rows: rows ?? [],
  });

  // rows-এর কনটেন্ট বদলালেই শুধু ফর্ম রিসেট (validation error-এ ইনপুট মুছবে না)
  const rowsSig = JSON.stringify(rows ?? []);

  useEffect(() => {
    setData((d) => ({
      skcl_no: skclNo ?? '',
      table_no_id: d.table_no_id, // নতুন সার্চেও Table ও Status থাকবে
      status: d.status,
      fixed_qty: '',
      ratios: defaultRatios(sizes),
      rows: rows ?? [],
    }));
    setSearch(skclNo ?? '');
    setColor(colorName ?? '');
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [skclNo, colorName, rowsSig]);

  const handleSearch = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    router.get(
      MarkerPlanController.create.url(),
      { skcl_no: search.trim(), color_name: color.trim() },
      { preserveState: true },
    );
  };

  const handlePivotChange = (next: PivotChange) =>
    setData((d) => ({
      ...d,
      rows: next.rows,
      fixed_qty: next.fixedQty,
      ratios: next.ratios,
    }));

  const lockOnSave = statuses.find((s) => s.value === data.status)?.locked ?? false;

  const handleSave = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();

    if (
      lockOnSave &&
      !window.confirm(
        'এই status-এ সেভ করলে marker plan(গুলো) লক হয়ে যাবে, আর edit বা delete করা যাবে না। চালিয়ে যাবেন?',
      )
    ) {
      return;
    }

    post(MarkerPlanController.store.url(), { preserveScroll: true });
  };
  return (
    <>
      <Head title="New Marker Plan" />
      <div className="mx-auto max-w-6xl space-y-6 p-6">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between gap-3">
            <CardTitle>New Marker Plan</CardTitle>
            <Button asChild variant="outline" size="sm">
              <Link href={MarkerPlanController.index.url()}>← Back to List</Link>
            </Button>
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
                <PivotForm
                  key={`${skclNo}|${colorName}`}
                  sizes={sizes}
                  rows={data.rows}
                  fixedQty={data.fixed_qty}
                  ratios={data.ratios}
                  onChange={handlePivotChange}
                >
                  <TableSelect
                    tables={tables}
                    value={data.table_no_id}
                    onChange={(v) => setData('table_no_id', v)}
                    error={errors.table_no_id}
                  />
                  <StatusSelect
                    statuses={statuses}
                    value={data.status}
                    onChange={(v) => setData('status', v)}
                    error={errors.status}
                  />
                </PivotForm>

                {errors.rows && <p className="text-sm text-destructive">{errors.rows}</p>}
                {errors.fixed_qty && (
                  <p className="text-sm text-destructive">{errors.fixed_qty}</p>
                )}

                {lockOnSave && (
                  <p className="text-sm text-amber-600">
                    ⚠ এই status-এ সেভ করলে marker plan(গুলো) লক হয়ে যাবে। এরপর আর edit বা delete করা যাবে না।
                  </p>
                )}

                <div className="flex flex-wrap items-center gap-3">
                  <Button type="submit" disabled={processing}>
                    {processing ? 'Saving...' : 'Save Marker Plan'}
                  </Button>
                  <p className="text-sm text-muted-foreground">
                    যেসব সারিতে quantity আছে, প্রতিটির জন্য আলাদা marker plan (আলাদা ref) তৈরি হবে।
                  </p>
                </div>
              </CardContent>
            </Card>
          </form>
        )}
      </div>
    </>
  );
}
