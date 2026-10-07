import { type FormEvent, useEffect } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import DeleteRefButton from '@/components/size-quantity/delete-ref-button';
import SizeQuantityController from '@/actions/App/Http/Controllers/SizeQuantityController';
import PivotForm from '@/components/size-quantity/pivot-form';
import TableSelect from '@/components/size-quantity/table-select';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { FormShape, Meta, PivotChange, PivotRow, TableOption } from '@/types/size-quantity';

type PageProps = {
  refNo: string;
  skclNo: string;
  tableNoId: number | null;
  fixedQty: string;
  ratios: Record<string, string>;
  tables: TableOption[];
  meta: Meta;
  sizes: string[];
  rows: PivotRow[];
};

export default function Edit({
  refNo,
  skclNo,
  tableNoId,
  fixedQty,
  ratios,
  tables,
  meta,
  sizes,
  rows,
}: PageProps) {
  const { data, setData, put, processing, errors } = useForm<FormShape>({
    skcl_no: skclNo,
    table_no_id: tableNoId ? String(tableNoId) : '',
    fixed_qty: fixedQty,
    ratios,
    rows,
  });
  const rowsSig = JSON.stringify(rows);
  const ratiosSig = JSON.stringify(ratios);

  useEffect(() => {
    setData({
      skcl_no: skclNo,
      table_no_id: tableNoId ? String(tableNoId) : '',
      fixed_qty: fixedQty,
      ratios,
      rows,
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [skclNo, refNo, tableNoId, fixedQty, ratiosSig, rowsSig]);

  const handlePivotChange = (next: PivotChange) =>
    setData((d) => ({ ...d, rows: next.rows, fixed_qty: next.fixedQty, ratios: next.ratios }));

  const handleSave = (e: React.SyntheticEvent) => {
    e.preventDefault();
    put(SizeQuantityController.update.url({ ref: refNo }), { preserveScroll: true });
  };

  return (
    <>
      <Head title={`Edit ${refNo}`} />
      <div className="mx-auto max-w-7xl space-y-6 p-6">
        <form onSubmit={handleSave}>
          <Card>
            <CardHeader className="flex flex-row items-center justify-between gap-3">
              <CardTitle className="text-base">
                Edit Ref: {refNo} | SKCL: {skclNo} | File: {meta.file_no} | Order:{' '}
                {meta.order_no} | Style: {meta.style_no}
              </CardTitle>
              <div className="flex gap-2">
                <DeleteRefButton refNo={refNo} skclNo={skclNo} />
                <Button asChild variant="outline" size="sm">
                  <a
                    href={SizeQuantityController.pdf.url(
                      { ref: refNo },
                      { query: { skcl_no: skclNo } },
                    )}
                    target="_blank"
                    rel="noreferrer"
                  >
                    Print PDF
                  </a>
                </Button>
                <Button asChild variant="outline" size="sm">
                  <Link href={SizeQuantityController.index.url()}>← Back to List</Link>
                </Button>
              </div>
            </CardHeader>
            <CardContent className="space-y-4">
              <PivotForm
                key={refNo}
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
              </PivotForm>

              {errors.rows && <p className="text-sm text-destructive">{errors.rows}</p>}
              {errors.fixed_qty && (
                <p className="text-sm text-destructive">{errors.fixed_qty}</p>
              )}

              <Button type="submit" disabled={processing}>
                {processing ? 'Updating...' : 'Update'}
              </Button>
            </CardContent>
          </Card>
        </form>
      </div>
    </>
  );
}
