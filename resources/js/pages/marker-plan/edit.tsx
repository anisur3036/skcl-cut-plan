import { type FormEvent, useEffect } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';

import MarkerPlanController from '@/actions/App/Http/Controllers/MarkerPlanController';
import DeleteMarkerPlanButton from '@/components/size-quantity/delete-marker-plan-button';
import PivotForm from '@/components/size-quantity/pivot-form';
import StatusSelect from '@/components/size-quantity/status-select';
import TableSelect from '@/components/size-quantity/table-select';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type {
  FormShape,
  PivotChange,
  PivotRow,
  PlanInfo,
  StatusOption,
  TableOption,
} from '@/types/size-quantity';

type PageProps = {
  plan: PlanInfo;
  tableNoId: number | null;
  status: string;
  fixedQty: string;
  ratios: Record<string, string>;
  tables: TableOption[];
  statuses: StatusOption[];
  sizes: string[];
  rows: PivotRow[];
};

export default function Edit({
  plan,
  tableNoId,
  status,
  fixedQty,
  ratios,
  tables,
  statuses,
  sizes,
  rows,
}: PageProps) {
  const { data, setData, put, processing, errors } = useForm<FormShape>({
    skcl_no: plan.skcl_no,
    table_no_id: tableNoId ? String(tableNoId) : '',
    status,
    fixed_qty: fixedQty,
    ratios,
    rows,
  });

  // কনটেন্ট বদলালেই শুধু ফর্ম রিসেট
  const rowsSig = JSON.stringify(rows);
  const ratiosSig = JSON.stringify(ratios);

  useEffect(() => {
    setData({
      skcl_no: plan.skcl_no,
      table_no_id: tableNoId ? String(tableNoId) : '',
      status,
      fixed_qty: fixedQty,
      ratios,
      rows,
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [plan.id, tableNoId, status, fixedQty, ratiosSig, rowsSig]);

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
        'এই status-এ Update করলে marker plan লক হয়ে যাবে, আর edit বা delete করা যাবে না। চালিয়ে যাবেন?',
      )
    ) {
      return;
    }

    put(MarkerPlanController.update.url(plan.id), { preserveScroll: true });
  };
  return (
    <>
      <Head title={`Edit ${plan.ref_no}`} />
      <div className="mx-auto max-w-6xl space-y-6 p-6">
        <form onSubmit={handleSave}>
          <Card>
            <CardHeader className="flex flex-row items-center justify-between gap-3">
              <CardTitle className="text-base">
                Edit Ref: {plan.ref_no} | SKCL: {plan.skcl_no} | File: {plan.file_no} |
                Order: {plan.order_no} | Style: {plan.style_no} | {plan.country} /{' '}
                {plan.item_name} / {plan.color_name}
              </CardTitle>
              <div className="flex gap-2">
                <DeleteMarkerPlanButton planId={plan.id} refNo={plan.ref_no} />
                <Button asChild variant="outline" size="sm">
                  <a
                    href={MarkerPlanController.pdf.url(plan.id)}
                    target="_blank"
                    rel="noreferrer"
                  >
                    Print PDF
                  </a>
                </Button>
                <Button asChild variant="outline" size="sm">
                  <Link href={MarkerPlanController.index.url()}>← Back to List</Link>
                </Button>
              </div>
            </CardHeader>
            <CardContent className="space-y-4">
              <PivotForm
                key={plan.id}
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
                  ⚠ এই status-এ Update করলে marker plan লক হয়ে যাবে। এরপর আর edit বা delete করা যাবে না (শুধু PDF
                  প্রিন্ট করা যাবে)।
                </p>
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
